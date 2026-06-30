<?php
/**
 * Aurum Vault Logistics - Production Admin Setup (cPanel)
 *
 * Run this ONCE on cPanel to create the initial admin account.
 * Access via: https://www.aurumvlogistics.com/setup-admin.php
 *
 * ⚠️  DELETE THIS FILE IMMEDIATELY AFTER USE ⚠️
 */

// ============================================================
// DATABASE CREDENTIALS - Fill in your cPanel MySQL details
// ============================================================
$dbHost     = 'localhost';
$dbName     = 'auruwlzj_aurumvault';
$dbUser     = 'auruwlzj_lyfe';
$dbPassword = 'oCCeans3484@';
// ============================================================

// ============================================================
// ADMIN CREDENTIALS - Change these before running
// ============================================================
$adminName     = 'Admin';
$adminEmail    = 'admin@aurumvlogistics.com';
$adminPassword = 'oCCeans3484@';
$adminPhone    = '0000000000';
// ============================================================

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Check if admin already exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
    $stmt->execute(['email' => $adminEmail]);

    if ((int) $stmt->fetchColumn() > 0) {
        echo "⚠️  Admin account already exists with email: {$adminEmail}\n";
        echo "No changes made.\n";
        exit;
    }

    // Hash the password
    $passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    // Insert admin user
    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, phone, password_hash, role, status, kyc_status, created_at, updated_at)
         VALUES (:name, :email, :phone, :password_hash, :role, :status, :kyc_status, NOW(), NOW())'
    );

    $stmt->execute([
        'name'          => $adminName,
        'email'         => $adminEmail,
        'phone'         => $adminPhone,
        'password_hash' => $passwordHash,
        'role'          => 'admin',
        'status'        => 'active',
        'kyc_status'    => 'approved',
    ]);

    echo "✅ Admin account created successfully!\n\n";
    echo "Login credentials:\n";
    echo "  Email:    {$adminEmail}\n";
    echo "  Password: {$adminPassword}\n\n";
    echo "⚠️  IMPORTANT: Delete this file from the server NOW!\n";
    echo "    rm /home/auruwlzj/public_html/setup-admin.php\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
