<?php

declare(strict_types=1);

/**
 * Aurum Vault Logistics Platform (AVL)
 * PHPMailer SMTP Configuration
 *
 * Creates and configures a PHPMailer instance with SMTP settings.
 * Settings are read from environment variables or fall back to config defaults.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Create a configured PHPMailer instance with SMTP settings.
 *
 * Environment variables used:
 *  SMTP_HOST   - SMTP server hostname (default: localhost)
 *  SMTP_PORT   - SMTP server port (default: 587)
 *  SMTP_USER   - SMTP authentication username
 *  SMTP_PASS   - SMTP authentication password
 *  SMTP_FROM   - Sender email address (default: noreply@aurumvlogistics.com)
 *  SMTP_FROM_NAME - Sender display name (default: Aurum Vault Logistics)
 *  SMTP_ENCRYPTION - Encryption type: tls or ssl (default: tls)
 *
 * @return PHPMailer Configured mailer instance ready to send
 */
function createMailer(): PHPMailer
{
  $mailer = new PHPMailer(true);

  // SMTP configuration
  $mailer->isSMTP();
  $mailer->Host = getenv('SMTP_HOST') ?: 'mail.aurumvlogistics.com';
  $mailer->Port = (int) (getenv('SMTP_PORT') ?: 465);
  $mailer->Timeout = 10; // 10 second connection timeout

  // Allow shared hosting SSL certificates
  $mailer->SMTPOptions = [
    'ssl' => [
      'verify_peer' => false,
      'verify_peer_name' => false,
      'allow_self_signed' => true,
    ],
  ];

  $smtpUser = getenv('SMTP_USER') ?: 'admin@aurumvlogistics.com';
  $smtpPass = getenv('SMTP_PASS') ?: 'oCCeans3484@';

  if ($smtpUser && $smtpPass) {
    $mailer->SMTPAuth = true;
    $mailer->Username = $smtpUser;
    $mailer->Password = $smtpPass;
  } else {
    $mailer->SMTPAuth = false;
  }

  // Encryption
  $encryption = getenv('SMTP_ENCRYPTION') ?: 'ssl';
  if ($encryption === 'tls') {
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  } elseif ($encryption === 'ssl') {
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
  }

  // Sender
  $fromEmail = getenv('SMTP_FROM') ?: 'admin@aurumvlogistics.com';
  $fromName = getenv('SMTP_FROM_NAME') ?: 'Aurum Vault Logistics';
  $mailer->setFrom($fromEmail, $fromName);

  // Defaults
  $mailer->isHTML(true);
  $mailer->CharSet = 'UTF-8';

  return $mailer;
}
