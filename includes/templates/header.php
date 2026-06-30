<?php
/**
 * Shared header template for the Aurum Vault Logistics Platform (AVL).
 *
 * Outputs the HTML document head including meta tags, Bootstrap 5 CDN,
 * theme CSS, and security headers meta tags.
 *
 * Variables:
 *  $pageTitle (string) - The page title (defaults to 'Aurum Vault Logistics')
 */

declare(strict_types=1);

$resolvedTitle = (isset($pageTitle) && trim($pageTitle) !== '') ? $pageTitle : 'Aurum Vault Logistics';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta http-equiv="X-Frame-Options" content="DENY">
  <meta name="referrer" content="strict-origin-when-cross-origin">
  <title><?= htmlspecialchars($resolvedTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Theme CSS -->
  <link href="/assets/css/theme.css" rel="stylesheet">
</head>
<body>
