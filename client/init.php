<?php
/**
 * Client Portal - Path Initialization
 *
 * Auto-detects the correct path to includes/ regardless of hosting layout.
 * Works in both local development and cPanel production.
 *
 * Local:  client/ and includes/ are siblings  → ../includes/
 * cPanel: client/ is inside public_html/      → ../../includes/
 */

if (!defined('INCLUDES_PATH')) {
    if (is_dir(__DIR__ . '/../includes') && file_exists(__DIR__ . '/../includes/db.php')) {
        // Local development: client/ is a sibling of includes/
        define('INCLUDES_PATH', __DIR__ . '/../includes');
    } elseif (is_dir(__DIR__ . '/../../includes') && file_exists(__DIR__ . '/../../includes/db.php')) {
        // cPanel: client/ is inside public_html/, includes/ is above public_html/
        define('INCLUDES_PATH', __DIR__ . '/../../includes');
    } else {
        die('Configuration error: cannot locate includes/ directory.');
    }
}

require_once INCLUDES_PATH . '/bootstrap.php';
