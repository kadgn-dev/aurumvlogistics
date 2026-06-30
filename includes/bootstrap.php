<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Bootstrap - Path Resolution
 *
 * Resolves the correct path to includes/ regardless of hosting layout.
 * Include this file from any PHP page and use INCLUDES_PATH for all requires.
 *
 * Local development structure:
 *   goldbodvault/
 *     ├── public_html/   (document root)
 *     ├── admin/         (sibling to public_html)
 *     ├── client/        (sibling to public_html)
 *     └── includes/      (sibling to public_html)
 *
 * cPanel production structure:
 *   /home/user/
 *     ├── public_html/          (document root)
 *     │   ├── admin/            (inside public_html)
 *     │   └── client/           (inside public_html)
 *     └── includes/             (above public_html)
 *
 * Usage:
 *   require_once __DIR__ . '/../includes/bootstrap.php';   (from public_html pages)
 *   require_once __DIR__ . '/../includes/bootstrap.php';   (from admin/ or client/ in local dev)
 *   require_once __DIR__ . '/../../includes/bootstrap.php'; (from admin/ or client/ on cPanel)
 *
 * After including bootstrap.php, use:
 *   require_once INCLUDES_PATH . '/db.php';
 *   require_once INCLUDES_PATH . '/auth.php';
 */

if (!defined('INCLUDES_PATH')) {
    define('INCLUDES_PATH', __DIR__);
}

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Auto-load composer autoloader if available
$autoloadPaths = [
    ROOT_PATH . '/vendor/autoload.php',
];
foreach ($autoloadPaths as $autoload) {
    if (file_exists($autoload)) {
        require_once $autoload;
        break;
    }
}
