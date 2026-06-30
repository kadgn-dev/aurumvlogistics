<?php

declare(strict_types=1);

namespace GOLS\Services;

use GOLS\Result;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Aurum Vault Logistics Platform (AVL)
 * EmailService - Transactional email delivery with template rendering
 *
 * Handles sending HTML emails via PHPMailer with retry logic,
 * template rendering with placeholder replacement, and email
 * preference management (subscribe/unsubscribe).
 *
 * Requirements: 19.1, 19.2, 19.3, 19.4, 19.5
 */
class EmailService
{
  private \PDO $pdo;

  /** @var string Path to email templates directory */
  private string $templateDir;

  /** @var callable|null Factory function to create PHPMailer instances (for testing) */
  private $mailerFactory;

  /** @var int Interval between retry attempts in seconds */
  private int $retryInterval;

  /**
   * Critical email categories that cannot be unsubscribed from.
   */
  private const CRITICAL_CATEGORIES = [
    'registration_confirmation',
    'password_reset',
    'transaction_confirmation',
  ];

  private const DEFAULT_RETRY_INTERVAL = 30;
  private const DEFAULT_MAX_RETRIES = 3;

  /**
   * @param \PDO $pdo Database connection for email_preferences table
   * @param string|null $templateDir Path to email templates directory
   * @param callable|null $mailerFactory Optional factory for creating PHPMailer instances
   * @param int $retryInterval Seconds between retry attempts (default 30)
   */
  public function __construct(
    \PDO $pdo,
    ?string $templateDir = null,
    ?callable $mailerFactory = null,
    int $retryInterval = self::DEFAULT_RETRY_INTERVAL
  ) {
    $this->pdo = $pdo;
    $this->templateDir = $templateDir ?? dirname(__DIR__) . '/templates/email';
    $this->mailerFactory = $mailerFactory;
    $this->retryInterval = $retryInterval;
  }

  /**
   * Send an email using an HTML template with placeholder replacement.
   *
   * Templates are loaded from the templates/email directory. Placeholders
   * in the format {{key}} are replaced with corresponding data values.
   * The platform logo URL and platform name are automatically injected.
   *
   * @param string $to Recipient email address
   * @param string $subject Email subject line
   * @param string $template Template filename (without path, e.g. "registration.html")
   * @param array $data Key-value pairs for template placeholder replacement
   * @return Result Success or error with details
   */
  public function send(string $to, string $subject, string $template, array $data = []): Result
  {
    // Load and render the template
    $templatePath = $this->templateDir . '/' . $template;

    if (!file_exists($templatePath)) {
      return Result::error('TEMPLATE_NOT_FOUND', "Email template not found: {$template}");
    }

    $html = file_get_contents($templatePath);
    if ($html === false) {
      return Result::error('TEMPLATE_READ_ERROR', "Failed to read email template: {$template}");
    }

    // Inject standard branding data
    $data = array_merge([
      'logo_url' => $this->getLogoUrl(),
      'platform_name' => $this->getPlatformName(),
      'app_url' => defined('APP_URL') ? APP_URL : 'https://www.aurumvlogistics.com',
      'year' => date('Y'),
    ], $data);

    // Replace placeholders {{key}} with data values
    $html = $this->renderTemplate($html, $data);

    // Create and configure mailer
    try {
      $mailer = $this->createMailerInstance();
      $mailer->addAddress($to);
      $mailer->Subject = $subject;
      $mailer->Body = $html;
      $mailer->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));

      $mailer->send();

      return Result::success(['recipient' => $to, 'subject' => $subject]);
    } catch (PHPMailerException $e) {
      error_log(sprintf(
        '[EmailService] Send failed. To: %s, Subject: %s, Error: %s',
        $to,
        $subject,
        $e->getMessage()
      ));

      return Result::error('EMAIL_SEND_FAILED', 'Failed to send email: ' . $e->getMessage());
    }
  }

  /**
   * Send an email with retry logic.
   *
   * Retries up to $maxRetries times with a configurable interval between attempts.
   * Logs all failures with recipient, subject, timestamp, and error details.
   * Does not expose errors to the end user.
   *
   * @param string $to Recipient email address
   * @param string $subject Email subject line
   * @param string $template Template filename
   * @param array $data Template placeholder data
   * @param int $maxRetries Maximum number of retry attempts (default 3)
   * @return Result Success or error after all retries exhausted
   */
  public function sendWithRetry(
    string $to,
    string $subject,
    string $template,
    array $data = [],
    int $maxRetries = self::DEFAULT_MAX_RETRIES
  ): Result {
    $lastResult = null;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
      $lastResult = $this->send($to, $subject, $template, $data);

      if ($lastResult->success) {
        return $lastResult;
      }

      // Log each failed attempt
      error_log(sprintf(
        '[EmailService] Retry %d/%d failed. To: %s, Subject: %s, Time: %s, Error: %s',
        $attempt,
        $maxRetries,
        $to,
        $subject,
        date('Y-m-d H:i:s'),
        $lastResult->errorMessage ?? 'Unknown error'
      ));

      // Wait before retrying (skip wait on last attempt)
      if ($attempt < $maxRetries) {
        sleep($this->retryInterval);
      }
    }

    // All retries exhausted - log final failure
    error_log(sprintf(
      '[EmailService] All %d attempts failed. To: %s, Subject: %s, Time: %s',
      $maxRetries,
      $to,
      $subject,
      date('Y-m-d H:i:s')
    ));

    return Result::error(
      'EMAIL_DELIVERY_FAILED',
      'Email delivery failed after ' . $maxRetries . ' attempts.'
    );
  }

  /**
   * Check if a user has unsubscribed from a specific email category.
   *
   * Critical categories (registration_confirmation, password_reset,
   * transaction_confirmation) always return false (cannot be unsubscribed).
   *
   * @param int $userId The user ID to check
   * @param string $category The email category to check
   * @return bool True if the user has unsubscribed from this category
   */
  public function isUnsubscribed(int $userId, string $category): bool
  {
    // Critical categories cannot be unsubscribed
    if ($this->isCriticalCategory($category)) {
      return false;
    }

    $stmt = $this->pdo->prepare(
      'SELECT subscribed FROM email_preferences WHERE user_id = ? AND category = ?'
    );
    $stmt->execute([$userId, $category]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($row === false) {
      // No preference record means subscribed by default
      return false;
    }

    return (int) $row['subscribed'] === 0;
  }

  /**
   * Unsubscribe a user from a specific email category.
   *
   * Critical categories cannot be unsubscribed from. If the user has no
   * existing preference record, one is created with subscribed=0.
   *
   * @param int $userId The user ID to unsubscribe
   * @param string $category The email category to unsubscribe from
   * @return void
   */
  public function unsubscribe(int $userId, string $category): void
  {
    // Critical categories cannot be unsubscribed
    if ($this->isCriticalCategory($category)) {
      return;
    }

    $this->upsertPreference($userId, $category, 0);
  }

  /**
   * Resubscribe a user to a specific email category.
   *
   * If the user has no existing preference record, one is created with subscribed=1.
   *
   * @param int $userId The user ID to resubscribe
   * @param string $category The email category to resubscribe to
   * @return void
   */
  public function resubscribe(int $userId, string $category): void
  {
    $this->upsertPreference($userId, $category, 1);
  }

  /**
   * Insert or update an email preference record.
   *
   * Uses MySQL ON DUPLICATE KEY UPDATE syntax for atomic upsert.
   * Falls back to SELECT + INSERT/UPDATE for other database drivers.
   *
   * @param int $userId The user ID
   * @param string $category The email category
   * @param int $subscribed 1 for subscribed, 0 for unsubscribed
   * @return void
   */
  private function upsertPreference(int $userId, string $category, int $subscribed): void
  {
    $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
      $stmt = $this->pdo->prepare(
        'INSERT INTO email_preferences (user_id, category, subscribed, updated_at)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE subscribed = VALUES(subscribed), updated_at = NOW()'
      );
      $stmt->execute([$userId, $category, $subscribed]);
    } else {
      // Portable approach for SQLite and other drivers
      $stmt = $this->pdo->prepare(
        'SELECT id FROM email_preferences WHERE user_id = ? AND category = ?'
      );
      $stmt->execute([$userId, $category]);
      $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

      if ($existing) {
        $stmt = $this->pdo->prepare(
          'UPDATE email_preferences SET subscribed = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND category = ?'
        );
        $stmt->execute([$subscribed, $userId, $category]);
      } else {
        $stmt = $this->pdo->prepare(
          'INSERT INTO email_preferences (user_id, category, subscribed, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([$userId, $category, $subscribed]);
      }
    }
  }

  /**
   * Check if an email category is critical (cannot be unsubscribed from).
   *
   * Critical categories: registration_confirmation, password_reset, transaction_confirmation.
   *
   * @param string $category The category to check
   * @return bool True if the category is critical
   */
  public function isCriticalCategory(string $category): bool
  {
    return in_array($category, self::CRITICAL_CATEGORIES, true);
  }

  /**
   * Replace template placeholders with data values.
   *
   * Placeholders use the format {{key}} and are replaced with the
   * corresponding value from the data array.
   *
   * @param string $html The template HTML content
   * @param array $data Key-value pairs for replacement
   * @return string Rendered HTML with placeholders replaced
   */
  private function renderTemplate(string $html, array $data): string
  {
    foreach ($data as $key => $value) {
      $html = str_replace('{{' . $key . '}}', (string) $value, $html);
    }

    return $html;
  }

  /**
   * Create a PHPMailer instance using the factory or the global createMailer function.
   *
   * @return PHPMailer Configured mailer instance
   */
  private function createMailerInstance(): PHPMailer
  {
    if ($this->mailerFactory !== null) {
      return ($this->mailerFactory)();
    }

    // Fall back to the global createMailer function from includes/mailer.php
    if (!function_exists('createMailer')) {
      require_once dirname(__DIR__) . '/mailer.php';
    }

    return createMailer();
  }

  /**
   * Get the platform logo URL for email templates.
   *
   * @return string Logo URL
   */
  private function getLogoUrl(): string
  {
    $appUrl = defined('APP_URL') ? APP_URL : 'https://www.aurumvlogistics.com';
    return $appUrl . '/assets/img/logo.png';
  }

  /**
   * Get the platform name for email templates.
   *
   * @return string Platform name
   */
  private function getPlatformName(): string
  {
    return defined('APP_NAME') ? APP_NAME : 'Aurum Vault Logistics';
  }
}
