<?php
require_once 'includes/db.php';
require_once 'includes/repositories/ContentRepository.php';

use GOLS\Repositories\ContentRepository;

$pdo = getDbConnection();
$contentRepo = new ContentRepository($pdo);

$settings = [
  'site_name' => 'Aurum Vault Logistics',
  'site_tagline' => 'Secure. Insured. Worldwide.',
  'logo_path' => '/assets/img/logo.png',
  'footer_text' => '',
];

$contentRepo->upsert('site_settings', $settings, 1);
echo "Done. Logo set to /assets/img/logo.png\n";
