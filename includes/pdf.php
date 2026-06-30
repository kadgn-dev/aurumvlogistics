<?php

/**
 * Aurum Vault Logistics Platform (AVL)
 * PDF Configuration & Helper
 *
 * Provides shared PDF generation configuration for TCPDF.
 * Used by InvoiceService::buildPdf() and any future PDF generation needs.
 *
 * Requirement 9.2: Client downloads invoice as PDF.
 */

declare(strict_types=1);

// ─── PDF Configuration Constants ────────────────────────────────────────────

/** Default page orientation: Portrait */
define('GOLS_PDF_ORIENTATION', 'P');

/** Default page size */
define('GOLS_PDF_PAGE_SIZE', 'A4');

/** Default unit of measurement */
define('GOLS_PDF_UNIT', 'mm');

/** Left margin in mm */
define('GOLS_PDF_MARGIN_LEFT', 15);

/** Right margin in mm */
define('GOLS_PDF_MARGIN_RIGHT', 15);

/** Top margin in mm */
define('GOLS_PDF_MARGIN_TOP', 15);

/** Bottom margin (auto page break) in mm */
define('GOLS_PDF_MARGIN_BOTTOM', 15);

/** Default font family */
define('GOLS_PDF_FONT_FAMILY', 'helvetica');

/** Company name displayed on invoices */
define('GOLS_PDF_COMPANY_NAME', 'Aurum Vault Logistics');

/** Creator metadata for PDF documents */
define('GOLS_PDF_CREATOR', 'Aurum Vault Logistics Platform');

/** Author metadata for PDF documents */
define('GOLS_PDF_AUTHOR', 'GOLS');

// ─── Helper Functions ───────────────────────────────────────────────────────

/**
 * Check if TCPDF is available for PDF generation.
 *
 * Ensures the Composer autoloader has loaded TCPDF.
 * Returns true if the TCPDF class is available, false otherwise.
 *
 * @return bool
 */
function gols_pdf_available(): bool
{
  // Ensure Composer autoloader is loaded
  $autoloader = __DIR__ . '/../vendor/autoload.php';
  if (file_exists($autoloader)) {
    require_once $autoloader;
  }

  return class_exists(\TCPDF::class);
}

/**
 * Create a pre-configured TCPDF instance with GOLS defaults.
 *
 * Returns a TCPDF object with standard margins, metadata, and settings
 * applied. Callers can further customize before adding pages.
 *
 * @param string $title Document title for metadata
 * @param string $subject Document subject for metadata
 * @return \TCPDF
 * @throws \RuntimeException If TCPDF is not available
 */
function gols_create_pdf(string $title = '', string $subject = ''): \TCPDF
{
  if (!gols_pdf_available()) {
    throw new \RuntimeException('TCPDF library is not available. Run composer install.');
  }

  $pdf = new \TCPDF(
    GOLS_PDF_ORIENTATION,
    GOLS_PDF_UNIT,
    GOLS_PDF_PAGE_SIZE,
    true,
    'UTF-8',
    false
  );

  // Set document metadata
  $pdf->SetCreator(GOLS_PDF_CREATOR);
  $pdf->SetAuthor(GOLS_PDF_AUTHOR);
  if ($title !== '') {
    $pdf->SetTitle($title);
  }
  if ($subject !== '') {
    $pdf->SetSubject($subject);
  }

  // Remove default header/footer
  $pdf->setPrintHeader(false);
  $pdf->setPrintFooter(false);

  // Set margins
  $pdf->SetMargins(GOLS_PDF_MARGIN_LEFT, GOLS_PDF_MARGIN_TOP, GOLS_PDF_MARGIN_RIGHT);
  $pdf->SetAutoPageBreak(true, GOLS_PDF_MARGIN_BOTTOM);

  return $pdf;
}
