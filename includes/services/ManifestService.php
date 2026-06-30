<?php

declare(strict_types=1);

namespace GOLS\Services;

use GOLS\Repositories\ShipmentRepository;
use GOLS\Repositories\UserRepository;
use GOLS\Result;

/**
 * Aurum Vault Logistics Platform (AVL)
 * ManifestService - Builds and persists shipment manifest PDF documents.
 *
 * Responsible for generating branded PDF manifests when shipments are approved,
 * assembling manifest data from shipment/item/user records, and managing
 * manifest file storage.
 *
 * Requirements: 1.1, 1.2, 1.4, 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 4.1, 4.2, 4.3
 */
class ManifestService
{
  private ShipmentRepository $shipmentRepository;
  private UserRepository $userRepository;

  public function __construct(
    ShipmentRepository $shipmentRepository,
    UserRepository $userRepository
  ) {
    $this->shipmentRepository = $shipmentRepository;
    $this->userRepository = $userRepository;
  }

  /**
   * Calculate total weight from an array of inventory items.
   *
   * @param array $items Array of inventory item records (each with a 'weight' key)
   * @return float Total weight
   */
  public function calculateTotalWeight(array $items): float
  {
    $total = 0.0;

    foreach ($items as $item) {
      $total += (float) ($item['weight'] ?? 0);
    }

    return $total;
  }

  /**
   * Generate the manifest filename.
   *
   * @param int $shipmentId Shipment ID
   * @param string $timestamp Timestamp string (e.g. unix timestamp)
   * @return string Filename in pattern manifest_{id}_{timestamp}.pdf
   */
  public function generateFilename(int $shipmentId, string $timestamp): string
  {
    return sprintf('manifest_%d_%s.pdf', $shipmentId, $timestamp);
  }

  /**
   * Build and write the manifest PDF file to disk.
   *
   * Uses gols_create_pdf() helper to create a TCPDF instance configured with
   * A4 portrait orientation, helvetica font, and Aurum Vault Logistics branding.
   * Renders shipment details, client info, destination address, gold items table,
   * totals, and insurance status.
   *
   * @param array $manifestData Structured manifest data from buildManifestData()
   * @param string $filePath Full output file path
   * @return void
   * @throws \RuntimeException If PDF generation fails
   *
   * Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 5.1, 5.2, 5.3, 5.4, 5.5
   */
  public function buildPdf(array $manifestData, string $filePath): void
  {
    require_once __DIR__ . '/../pdf.php';

    $pdf = gols_create_pdf(
      'Shipment Manifest #' . $manifestData['shipment_id'],
      'Shipment Manifest'
    );

    // Override creator metadata per requirement 5.5
    $pdf->SetCreator('Aurum Vault Logistics Platform');

    // Add a page
    $pdf->AddPage();

    // ─── Company Header (Req 2.8, 5.2) ─────────────────────────────────────
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 12, 'Aurum Vault Logistics', 0, 1, 'C');

    // ─── Subtitle (Req 5.3) ─────────────────────────────────────────────────
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'SHIPMENT MANIFEST', 0, 1, 'C');
    $pdf->Ln(10);

    // ─── Shipment ID and Approval Date (Req 2.1) ────────────────────────────
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Shipment Details', 0, 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(50, 7, 'Shipment ID:', 0, 0);
    $pdf->Cell(0, 7, (string) $manifestData['shipment_id'], 0, 1);
    $pdf->Cell(50, 7, 'Approval Date:', 0, 0);
    $pdf->Cell(0, 7, $manifestData['approval_date'], 0, 1);
    $pdf->Ln(6);

    // ─── Client Information (Req 2.2) ───────────────────────────────────────
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Client Information', 0, 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(50, 7, 'Name:', 0, 0);
    $pdf->Cell(0, 7, $manifestData['client_name'], 0, 1);
    $pdf->Cell(50, 7, 'Email:', 0, 0);
    $pdf->Cell(0, 7, $manifestData['client_email'], 0, 1);
    $pdf->Cell(50, 7, 'Phone:', 0, 0);
    $pdf->Cell(0, 7, $manifestData['client_phone'], 0, 1);
    $pdf->Ln(6);

    // ─── Destination Address (Req 2.3) ──────────────────────────────────────
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Destination Address', 0, 1);
    $pdf->SetFont('helvetica', '', 11);
    $dest = $manifestData['destination'];
    $pdf->Cell(50, 7, 'Street:', 0, 0);
    $pdf->Cell(0, 7, $dest['street'], 0, 1);
    $pdf->Cell(50, 7, 'City:', 0, 0);
    $pdf->Cell(0, 7, $dest['city'], 0, 1);
    $pdf->Cell(50, 7, 'State/Province:', 0, 0);
    $pdf->Cell(0, 7, $dest['state_province'], 0, 1);
    $pdf->Cell(50, 7, 'Postal Code:', 0, 0);
    $pdf->Cell(0, 7, $dest['postal_code'], 0, 1);
    $pdf->Cell(50, 7, 'Country:', 0, 0);
    $pdf->Cell(0, 7, $dest['country'], 0, 1);
    $pdf->Ln(6);

    // ─── Gold Items Table (Req 2.4) ─────────────────────────────────────────
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Gold Items', 0, 1);

    // Table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(30, 7, 'Type', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Weight (oz)', 1, 0, 'C', true);
    $pdf->Cell(25, 7, 'Purity', 1, 0, 'C', true);
    $pdf->Cell(45, 7, 'Serial Number', 1, 0, 'C', true);
    $pdf->Cell(50, 7, 'Vault Location', 1, 1, 'C', true);

    // Table rows
    $pdf->SetFont('helvetica', '', 10);
    foreach ($manifestData['items'] as $item) {
      $pdf->Cell(30, 7, ucfirst($item['gold_type']), 1, 0, 'C');
      $pdf->Cell(30, 7, number_format($item['weight'], 4), 1, 0, 'C');
      $pdf->Cell(25, 7, number_format($item['purity'], 4), 1, 0, 'C');
      $pdf->Cell(45, 7, $item['serial_number'], 1, 0, 'C');
      $pdf->Cell(50, 7, $item['vault_location'], 1, 1, 'C');
    }
    $pdf->Ln(6);

    // ─── Totals and Insurance (Req 2.5, 2.6, 2.7) ──────────────────────────
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Summary', 0, 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(50, 7, 'Total Weight:', 0, 0);
    $pdf->Cell(0, 7, number_format($manifestData['total_weight'], 4) . ' oz', 0, 1);
    $pdf->Cell(50, 7, 'Insured Value:', 0, 0);
    $pdf->Cell(0, 7, '$' . number_format($manifestData['insured_value'], 2), 0, 1);
    $pdf->Cell(50, 7, 'Insurance Status:', 0, 0);
    $insuranceStatus = $manifestData['insurance_selected'] ? 'Insured' : 'Not Insured';
    $pdf->Cell(0, 7, $insuranceStatus, 0, 1);

    // Output PDF to file
    $pdf->Output($filePath, 'F');
  }

  /**
   * Generate a manifest PDF for an approved shipment.
   *
   * Orchestrates the full manifest generation workflow: validates TCPDF availability,
   * loads shipment/items/user data, generates the PDF, and persists the file path.
   *
   * @param int $shipmentId The shipment ID
   * @param string $approvalDate The approval timestamp (Y-m-d H:i:s)
   * @return Result Success with file_path or error
   */
  public function generateManifest(int $shipmentId, string $approvalDate): Result
  {
    // Ensure pdf.php helpers are loaded
    require_once dirname(__DIR__) . '/pdf.php';

    // Step 1: Check TCPDF availability
    if (!gols_pdf_available()) {
      return Result::error('PDF_UNAVAILABLE', 'TCPDF library is not available.');
    }

    // Step 2: Load shipment
    $shipment = $this->shipmentRepository->findById($shipmentId);
    if ($shipment === null) {
      return Result::error('SHIPMENT_NOT_FOUND', 'Shipment not found.');
    }

    // Step 3: Load shipment items
    $items = $this->shipmentRepository->getShipmentItems($shipmentId);

    // Step 4: Load user
    $user = $this->userRepository->findById((int) $shipment['user_id']);
    if ($user === null) {
      return Result::error('USER_NOT_FOUND', 'Shipment owner not found.');
    }

    // Step 5: Ensure uploads/manifests/ directory exists
    $projectRoot = dirname(__DIR__, 2);
    $dir = $projectRoot . '/uploads/manifests';
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }

    // Step 6: Generate filename
    $filename = $this->generateFilename($shipmentId, (string) time());

    // Step 7: Build full file path and relative path
    $fullPath = $dir . '/' . $filename;
    $relativePath = 'uploads/manifests/' . $filename;

    // Step 8: Build manifest data
    $manifestData = $this->buildManifestData($shipment, $items, $user, $approvalDate);

    // Step 9: Build PDF
    $this->buildPdf($manifestData, $fullPath);

    // Step 10: Update manifest path in database
    $this->shipmentRepository->updateManifestPath($shipmentId, $relativePath);

    // Step 11: Return success
    return Result::success(['file_path' => $relativePath]);
  }

  /**
   * Build the manifest data array from shipment, items, and user records.
   *
   * Assembles a structured array containing all information needed to render
   * the manifest PDF: shipment details, client info, destination address,
   * gold items, totals, and insurance status.
   *
   * @param array $shipment Shipment record from the database
   * @param array $items Array of inventory item records
   * @param array $user User record (client who owns the shipment)
   * @param string $approvalDate Approval timestamp (Y-m-d H:i:s)
   * @return array Structured manifest data
   */
  public function buildManifestData(
    array $shipment,
    array $items,
    array $user,
    string $approvalDate
  ): array {
    $manifestItems = [];

    foreach ($items as $item) {
      $manifestItems[] = [
        'gold_type' => $item['gold_type'] ?? '',
        'weight' => (float) ($item['weight'] ?? 0),
        'purity' => (float) ($item['purity'] ?? 0),
        'serial_number' => $item['serial_number'] ?? '',
        'vault_location' => $item['vault_location'] ?? '',
      ];
    }

    return [
      'shipment_id' => (int) ($shipment['id'] ?? 0),
      'approval_date' => $approvalDate,
      'client_name' => $user['name'] ?? '',
      'client_email' => $user['email'] ?? '',
      'client_phone' => $user['phone'] ?? '',
      'destination' => [
        'street' => $shipment['street'] ?? '',
        'city' => $shipment['city'] ?? '',
        'state_province' => $shipment['state_province'] ?? '',
        'postal_code' => $shipment['postal_code'] ?? '',
        'country' => $shipment['country'] ?? '',
      ],
      'items' => $manifestItems,
      'total_weight' => $this->calculateTotalWeight($items),
      'insured_value' => (float) ($shipment['insured_value'] ?? 0),
      'insurance_selected' => (bool) ($shipment['insurance_selected'] ?? false),
    ];
  }
}
