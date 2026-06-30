<?php

declare(strict_types=1);

/**
 * Shared utility helpers for the Aurum Vault Logistics (AVL) platform.
 *
 * Provides sanitization, formatting, pagination, and general-purpose
 * utility functions used across the application.
 */

/**
 * Sanitize output for safe HTML rendering (XSS prevention).
 *
 * Encodes special characters using htmlspecialchars with ENT_QUOTES
 * and UTF-8 encoding to prevent cross-site scripting attacks.
 *
 * @param string $value The raw value to sanitize for output.
 * @return string The HTML-safe encoded string.
 */
function sanitizeOutput(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize user input by trimming whitespace and stripping null bytes.
 *
 * @param string $value The raw input value to sanitize.
 * @return string The sanitized input string.
 */
function sanitizeInput(string $value): string
{
  $value = trim($value);
  $value = str_replace("\0", '', $value);

  return $value;
}

/**
 * Format a numeric amount as a currency string.
 *
 * @param float $amount  The monetary amount to format.
 * @param string $currency The ISO 4217 currency code (default: 'USD').
 * @return string The formatted currency string (e.g., "$1,234.56").
 */
function formatCurrency(float $amount, string $currency = 'USD'): string
{
  $symbols = [
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'JPY' => '¥',
    'CHF' => 'CHF ',
    'AUD' => 'A$',
    'CAD' => 'C$',
  ];

  $symbol = $symbols[$currency] ?? $currency . ' ';

  return $symbol . number_format($amount, 2, '.', ',');
}

/**
 * Format a datetime string into a human-readable date.
 *
 * @param string $datetime The datetime string to format (any strtotime-compatible format).
 * @param string $format  The desired output format (default: 'M d, Y').
 * @return string The formatted date string, or empty string on invalid input.
 */
function formatDate(string $datetime, string $format = 'M d, Y'): string
{
  $timestamp = strtotime($datetime);

  if ($timestamp === false) {
    return '';
  }

  return date($format, $timestamp);
}

/**
 * Format a datetime string with both date and time.
 *
 * @param string $datetime The datetime string to format.
 * @return string The formatted datetime string (e.g., "Jan 15, 2024 2:30 PM").
 */
function formatDateTime(string $datetime): string
{
  return formatDate($datetime, 'M d, Y g:i A');
}

/**
 * Calculate the SQL OFFSET for a given page and items-per-page.
 *
 * @param int $page  The current page number (1-based).
 * @param int $perPage The number of items per page.
 * @return int The calculated offset (0-based).
 */
function getPaginationOffset(int $page, int $perPage): int
{
  $page = max(1, $page);
  $perPage = max(1, $perPage);

  return ($page - 1) * $perPage;
}

/**
 * Calculate the total number of pages for a given record count.
 *
 * @param int $totalRecords The total number of records.
 * @param int $perPage   The number of items per page.
 * @return int The total number of pages (minimum 1).
 */
function getTotalPages(int $totalRecords, int $perPage): int
{
  $totalRecords = max(0, $totalRecords);
  $perPage = max(1, $perPage);

  return (int) ceil($totalRecords / $perPage) ?: 1;
}

/**
 * Generate a random filename for file uploads (e.g., KYC documents).
 *
 * Produces a unique filename using random bytes to prevent filename
 * collisions and information disclosure from original filenames.
 *
 * @param string $extension The file extension (without leading dot).
 * @return string The generated filename (e.g., "a1b2c3d4e5f6...abc.pdf").
 */
function generateRandomFilename(string $extension): string
{
  $extension = ltrim($extension, '.');
  $randomName = bin2hex(random_bytes(16));

  return $randomName . '.' . $extension;
}

/**
 * Validate an email address format.
 *
 * @param string $email The email address to validate.
 * @return bool True if the email format is valid, false otherwise.
 */
function isValidEmail(string $email): bool
{
  return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Perform a safe HTTP redirect and terminate script execution.
 *
 * @param string $url The URL to redirect to.
 * @return void
 */
function redirect(string $url): void
{
  header('Location: ' . $url);
  exit;
}


/**
 * Generate a unique serial number in the format GC##-####-####-####
 * 
 * Each segment is numeric. The first segment starts with GC followed by 2 digits.
 * The remaining 3 segments are 4 random digits each.
 *
 * @return string Generated serial number (e.g., GC47-8291-0364-5182)
 */
function generateSerialNumber(): string
{
  $seg1 = 'GC' . str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT);
  $seg2 = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
  $seg3 = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
  $seg4 = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

  return $seg1 . '-' . $seg2 . '-' . $seg3 . '-' . $seg4;
}


/**
 * Get site settings (name, logo, tagline) from the database.
 * Caches the result for the duration of the request.
 *
 * @return array Site settings with keys: site_name, site_tagline, logo_path, footer_text
 */
function getSiteSettings(): array
{
  static $settings = null;

  if ($settings === null) {
    try {
      $pdo = getDbConnection();
      $stmt = $pdo->prepare("SELECT content FROM content_pages WHERE page_key = 'site_settings'");
      $stmt->execute();
      $row = $stmt->fetch(\PDO::FETCH_ASSOC);

      if ($row && !empty($row['content'])) {
        $settings = json_decode($row['content'], true) ?: [];
      }
    } catch (\Exception $e) {
      error_log('[getSiteSettings] Database error: ' . $e->getMessage());
    }

    // Defaults
    $settings = array_merge([
      'site_name' => 'AURUM VAULT LOGISTICS',
      'site_tagline' => 'Secure Gold Storage & Insured Logistics Services',
      'logo_path' => '',
      'footer_text' => '',
    ], $settings ?? []);
  }

  return $settings;
}

/**
 * Get the Bootstrap badge CSS class and display label for an entity status.
 *
 * Maps entity types and their statuses to consistent Bootstrap badge styling.
 * Supports 'kyc', 'invoice', 'shipment', and 'user_status' entity types.
 *
 * @param string $entityType One of: 'kyc', 'invoice', 'shipment', 'user_status'.
 * @param string $status     The status value to map.
 * @return array{label: string, css_class: string} Associative array with badge label and CSS class.
 */
function getStatusBadge(string $entityType, string $status): array
{
    $map = [
        'kyc' => [
            'not_submitted'  => ['label' => 'Not Submitted', 'css_class' => 'bg-secondary'],
            'pending_review' => ['label' => 'Pending Review', 'css_class' => 'bg-warning text-dark'],
            'approved'       => ['label' => 'Approved', 'css_class' => 'bg-success'],
            'rejected'       => ['label' => 'Rejected', 'css_class' => 'bg-danger'],
        ],
        'invoice' => [
            'unpaid' => ['label' => 'Unpaid', 'css_class' => 'bg-warning text-dark'],
            'paid'   => ['label' => 'Paid', 'css_class' => 'bg-success'],
        ],
        'shipment' => [
            'pending_approval'   => ['label' => 'Pending Approval', 'css_class' => 'bg-warning text-dark'],
            'approved'           => ['label' => 'Approved', 'css_class' => 'bg-info text-dark'],
            'ready_for_shipment' => ['label' => 'Ready', 'css_class' => 'bg-primary'],
            'in_transit'         => ['label' => 'In Transit', 'css_class' => 'bg-info'],
            'delivered'          => ['label' => 'Delivered', 'css_class' => 'bg-success'],
            'rejected'           => ['label' => 'Rejected', 'css_class' => 'bg-danger'],
            'cancelled'          => ['label' => 'Cancelled', 'css_class' => 'bg-secondary'],
        ],
        'user_status' => [
            'pending'   => ['label' => 'Pending', 'css_class' => 'bg-warning text-dark'],
            'active'    => ['label' => 'Active', 'css_class' => 'bg-success'],
            'suspended' => ['label' => 'Suspended', 'css_class' => 'bg-danger'],
        ],
    ];

    if (isset($map[$entityType][$status])) {
        return $map[$entityType][$status];
    }

    return ['label' => $status, 'css_class' => 'bg-secondary'];
}
