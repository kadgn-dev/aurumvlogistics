<?php
/**
 * Local Development Setup Script
 * 
 * Creates the SQLite database, applies the schema, and seeds
 * test data (admin user + sample client).
 * 
 * Run: php setup-local.php
 */

echo "=== Gold Vault Platform - Local Setup ===\n\n";

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = getDbConnection();

echo "Database: SQLite at " . DB_SQLITE_PATH . "\n\n";

// Create tables (SQLite-compatible version)
echo "Creating tables...\n";

$pdo->exec('
  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(15) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role TEXT NOT NULL DEFAULT "client",
    status TEXT NOT NULL DEFAULT "pending",
    kyc_status TEXT NOT NULL DEFAULT "not_submitted",
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  )
');

$pdo->exec('
  CREATE TABLE IF NOT EXISTS vault_inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    gold_type TEXT NOT NULL,
    weight DECIMAL(9,3) NOT NULL,
    purity DECIMAL(5,4) NOT NULL,
    serial_number VARCHAR(50) NOT NULL UNIQUE,
    vault_location VARCHAR(255) NOT NULL,
    insurance_status INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
  )
');

$pdo->exec('
  CREATE TABLE IF NOT EXISTS shipments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    street VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state_province VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(100) NOT NULL,
    insurance_selected INTEGER NOT NULL DEFAULT 0,
    insured_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status TEXT NOT NULL DEFAULT "pending_approval",
    tracking_number VARCHAR(50) DEFAULT NULL,
    carrier TEXT DEFAULT NULL,
    rejection_reason TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
  )
');

$pdo->exec('
  CREATE TABLE IF NOT EXISTS shipment_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    shipment_id INTEGER NOT NULL,
    inventory_id INTEGER NOT NULL,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id),
    FOREIGN KEY (inventory_id) REFERENCES vault_inventory(id),
    UNIQUE (shipment_id, inventory_id)
  )
');

$pdo->exec('
  CREATE TABLE IF NOT EXISTS shipment_status_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    shipment_id INTEGER NOT NULL,
    status TEXT NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed_by INTEGER NOT NULL,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id),
    FOREIGN KEY (changed_by) REFERENCES users(id)
  )
');

$pdo->exec('
  CREATE TABLE IF NOT EXISTS invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    invoice_number VARCHAR(20) NOT NULL UNIQUE,
    amount DECIMAL(12,2) NOT NULL,
    description TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT "unpaid",
    payment_date DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
  )
');

$pdo->exec('
  CREATE TABLE IF NOT EXISTS payment_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INTEGER NOT NULL,
    gateway TEXT NOT NULL,
    transaction_id VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status VARCHAR(50) NOT NULL,
    transaction_date DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
  )
');

$pdo->exec('
  CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    reference_id INTEGER DEFAULT NULL,
    reference_type VARCHAR(50) DEFAULT NULL,
    read_status TEXT NOT NULL DEFAULT "unread",
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
  )
');

$pdo->exec('
  CREATE TABLE IF NOT EXISTS kyc_documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_type TEXT NOT NULL,
    file_size INTEGER NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
  )
');

$pdo->exec('
  CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success INTEGER NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  )
');

$pdo->exec('
  CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  )
');

$pdo->exec('
  CREATE TABLE IF NOT EXISTS email_preferences (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    category VARCHAR(50) NOT NULL,
    subscribed INTEGER NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, category),
    FOREIGN KEY (user_id) REFERENCES users(id)
  )
');

$pdo->exec('
  CREATE TABLE IF NOT EXISTS content_pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_key VARCHAR(50) NOT NULL UNIQUE,
    content TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER DEFAULT NULL
  )
');

$pdo->exec('
  CREATE TABLE IF NOT EXISTS faq_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    question VARCHAR(200) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  )
');

$pdo->exec('
  CREATE TABLE IF NOT EXISTS invoice_sequence (
    year INTEGER NOT NULL PRIMARY KEY,
    last_number INTEGER NOT NULL DEFAULT 0
  )
');

echo "Tables created successfully.\n\n";

// Seed test data
echo "Seeding test data...\n";

// Check if admin already exists
$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
$stmt->execute(['email' => 'admin@goldvault.com']);
$adminExists = (int) $stmt->fetchColumn() > 0;

if (!$adminExists) {
  // Create admin user (password: Admin123!)
  $adminHash = password_hash('Admin123!', PASSWORD_BCRYPT);
  $stmt = $pdo->prepare(
    'INSERT INTO users (name, email, phone, password_hash, role, status, kyc_status)
     VALUES (:name, :email, :phone, :password_hash, :role, :status, :kyc_status)'
  );
  $stmt->execute([
    'name' => 'Admin User',
    'email' => 'admin@goldvault.com',
    'phone' => '1234567890',
    'password_hash' => $adminHash,
    'role' => 'admin',
    'status' => 'active',
    'kyc_status' => 'approved',
  ]);
  echo " Created admin user: admin@goldvault.com / Admin123!\n";

  // Create test client (password: Client123!)
  $clientHash = password_hash('Client123!', PASSWORD_BCRYPT);
  $stmt->execute([
    'name' => 'John Gold',
    'email' => 'client@goldvault.com',
    'phone' => '9876543210',
    'password_hash' => $clientHash,
    'role' => 'client',
    'status' => 'active',
    'kyc_status' => 'approved',
  ]);
  $clientId = (int) $pdo->lastInsertId();
  echo " Created client user: client@goldvault.com / Client123!\n";

  // Add sample inventory for the client
  $items = [
    ['bar', 100.000, 0.9999, 'BAR001', 'Vault A', 1],
    ['coin', 31.103, 0.9167, 'COIN001', 'Vault A', 1],
    ['bar', 500.000, 0.9999, 'BAR002', 'Vault B', 1],
    ['grain', 1.000, 0.9999, 'GRAIN001', 'Vault A', 0],
    ['round', 31.103, 0.9999, 'ROUND001', 'Vault B', 1],
  ];

  $itemStmt = $pdo->prepare(
    'INSERT INTO vault_inventory (user_id, gold_type, weight, purity, serial_number, vault_location, insurance_status)
     VALUES (:user_id, :gold_type, :weight, :purity, :serial_number, :vault_location, :insurance_status)'
  );

  foreach ($items as $item) {
    $itemStmt->execute([
      'user_id' => $clientId,
      'gold_type' => $item[0],
      'weight' => $item[1],
      'purity' => $item[2],
      'serial_number' => $item[3],
      'vault_location' => $item[4],
      'insurance_status' => $item[5],
    ]);
  }
  echo " Added 5 sample inventory items for client.\n";

  // Add sample FAQ entries
  $faqs = [
    ['How do I store gold with Gold Vault?', 'Register an account, complete KYC verification, and our team will guide you through the storage process. We accept gold bars, coins, grains, and rounds.', 1],
    ['Is my gold insured?', 'Yes, all gold stored in our vaults is eligible for comprehensive insurance coverage. You can view your insurance status for each item in your dashboard.', 2],
    ['How do I request a shipment?', 'Navigate to the Shipment Request page in your dashboard, select the items you want shipped, provide a destination address, and submit your request for admin approval.', 3],
    ['What carriers do you use?', 'We partner with DHL, FedEx, and Brinks for secure, insured shipping of precious metals worldwide.', 4],
  ];

  $faqStmt = $pdo->prepare(
    'INSERT INTO faq_entries (question, answer, sort_order) VALUES (:question, :answer, :sort_order)'
  );
  foreach ($faqs as $faq) {
    $faqStmt->execute(['question' => $faq[0], 'answer' => $faq[1], 'sort_order' => $faq[2]]);
  }
  echo " Added 4 sample FAQ entries.\n";

  // Add sample notifications
  $notifStmt = $pdo->prepare(
    'INSERT INTO notifications (user_id, event_type, message, read_status) VALUES (:user_id, :event_type, :message, :read_status)'
  );
  $notifStmt->execute(['user_id' => $clientId, 'event_type' => 'system', 'message' => 'Welcome to Gold Vault! Your account has been activated.', 'read_status' => 'read']);
  $notifStmt->execute(['user_id' => $clientId, 'event_type' => 'kyc_approved', 'message' => 'Your KYC verification has been approved.', 'read_status' => 'unread']);
  echo " Added sample notifications.\n";

} else {
  echo " Test data already exists, skipping seed.\n";
}

echo "\n=== Setup Complete! ===\n\n";
echo "To start the development server, run:\n";
echo " php -S localhost:8000 server.php\n\n";
echo "Then open: http://localhost:8000/index.php\n\n";
echo "Test accounts:\n";
echo " Admin: admin@goldvault.com / Admin123!\n";
echo " Client: client@goldvault.com / Client123!\n\n";
