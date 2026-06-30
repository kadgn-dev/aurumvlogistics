<?php

/**
 * Aurum Vault Logistics Platform (AVL)
 * Database Connection - PDO Singleton
 */

// Load config: local (SQLite dev) > production (server credentials) > default
if (file_exists(__DIR__ . '/config.local.php')) {
  require_once __DIR__ . '/config.local.php';
} elseif (file_exists(__DIR__ . '/config.production.php')) {
  require_once __DIR__ . '/config.production.php';
} else {
  require_once __DIR__ . '/config.php';
}

/**
 * Returns a singleton PDO database connection.
 *
 * Supports both MySQL (production) and SQLite (local development).
 * Uses prepared statements with emulation disabled and exception error mode
 * as required by Requirement 16.3.
 *
 * @return PDO
 */
function getDbConnection(): PDO
{
  static $pdo = null;

  if ($pdo === null) {
    $options = [
      PDO::ATTR_ERRMODE      => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES  => false,
    ];

    if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') {
      $dbPath = DB_SQLITE_PATH;
      $dbDir = dirname($dbPath);
      if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
      }
      $pdo = new PDO('sqlite:' . $dbPath, null, null, $options);
      $pdo->exec('PRAGMA journal_mode=WAL');
      $pdo->exec('PRAGMA foreign_keys=ON');
    } else {
      $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_NAME
      );
      $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
    }
  }

  return $pdo;
}
