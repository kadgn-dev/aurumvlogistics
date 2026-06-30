-- ============================================================================
-- Aurum Vault Logistics Platform (AVL) - Database Schema
-- MySQL 8.0+ compatible
-- ============================================================================
-- This migration creates all 14 tables required for the GOLS platform:
-- users, vault_inventory, shipments, shipment_items, shipment_status_history,
-- invoices, payment_transactions, notifications, kyc_documents, login_attempts,
-- rate_limits, email_preferences, content_pages, faq_entries, invoice_sequence
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Table: users
-- Core user accounts for clients and admins
-- Requirements: 1.1, 11.1
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(15) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('client', 'admin') NOT NULL DEFAULT 'client',
    status ENUM('pending', 'active', 'suspended') NOT NULL DEFAULT 'pending',
    kyc_status ENUM('not_submitted', 'pending_review', 'approved', 'rejected') NOT NULL DEFAULT 'not_submitted',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: vault_inventory
-- Gold holdings stored in vaults, associated with client users
-- Requirements: 5.1
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vault_inventory (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    gold_type ENUM('bar', 'coin', 'grain', 'round') NOT NULL,
    weight DECIMAL(9,3) NOT NULL,
    purity DECIMAL(5,4) NOT NULL,
    carat DECIMAL(4,1) NOT NULL DEFAULT 24.0,
    serial_number VARCHAR(50) NOT NULL UNIQUE,
    vault_location VARCHAR(255) NOT NULL,
    insurance_status TINYINT(1) NOT NULL DEFAULT 0,
    item_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    date_acquired DATE NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_serial (serial_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: shipments
-- Shipment requests from clients for gold delivery
-- Requirements: 6.1, 7.3
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    street VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state_province VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(100) NOT NULL,
    insurance_selected TINYINT(1) NOT NULL DEFAULT 0,
    insured_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending_approval', 'approved', 'ready_for_shipment', 'in_transit', 'delivered', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending_approval',
    tracking_number VARCHAR(50) DEFAULT NULL,
    carrier ENUM('dhl', 'fedex', 'brinks') DEFAULT NULL,
    rejection_reason TEXT DEFAULT NULL,
    manifest_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_user_status (user_id, status),
    INDEX idx_tracking (tracking_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: shipment_items
-- Junction table linking shipments to inventory items (many-to-many)
-- Requirements: 6.1
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipment_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT UNSIGNED NOT NULL,
    inventory_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    FOREIGN KEY (inventory_id) REFERENCES vault_inventory(id) ON DELETE RESTRICT,
    UNIQUE KEY uk_shipment_inventory (shipment_id, inventory_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: shipment_status_history
-- Tracks all status transitions for shipments with timestamps and actors
-- Requirements: 7.3
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipment_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT UNSIGNED NOT NULL,
    status ENUM('pending_approval', 'approved', 'ready_for_shipment', 'in_transit', 'delivered', 'rejected', 'cancelled') NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed_by INT UNSIGNED NOT NULL,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_shipment_history (shipment_id, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: invoices
-- Billing records for storage fees, shipping costs, and other services
-- Requirements: 10.1
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(20) NOT NULL UNIQUE,
    amount DECIMAL(12,2) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid',
    payment_date DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_user_invoices (user_id, created_at DESC),
    INDEX idx_invoice_number (invoice_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: payment_transactions
-- Records of payment gateway interactions for invoice payments
-- Requirements: 10.1
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    gateway ENUM('paypal', 'stripe') NOT NULL,
    transaction_id VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status VARCHAR(50) NOT NULL,
    transaction_date DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
    INDEX idx_invoice_transactions (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: notifications
-- System-generated messages for users about account activity
-- Requirements: 13.1
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    reference_id INT UNSIGNED DEFAULT NULL,
    reference_type VARCHAR(50) DEFAULT NULL,
    read_status ENUM('unread', 'read') NOT NULL DEFAULT 'unread',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, read_status, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: kyc_documents
-- Know Your Customer documents uploaded by clients for identity verification
-- Requirements: 1.1
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kyc_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_type ENUM('pdf', 'jpg', 'png') NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_kyc (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: login_attempts
-- Tracks login attempts for account lockout enforcement
-- Requirements: 1.1
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time (email, attempted_at),
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: rate_limits
-- Tracks rate-limited actions by IP address (contact form, etc.)
-- Requirements: 1.1
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_action_time (ip_address, action_type, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: email_preferences
-- User email subscription preferences for non-critical notifications
-- Requirements: 1.1
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_preferences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    category VARCHAR(50) NOT NULL,
    subscribed TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_category (user_id, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: content_pages
-- CMS content storage for admin-managed public pages (home, pricing)
-- Requirements: 1.1
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS content_pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_key VARCHAR(50) NOT NULL UNIQUE,
    content JSON NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED DEFAULT NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: faq_entries
-- FAQ content managed by admins, displayed on public FAQ page
-- Requirements: 1.1
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS faq_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(200) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: invoice_sequence
-- Tracks sequential invoice numbers per year for atomic generation
-- Requirements: 10.1
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoice_sequence (
    year SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
    last_number INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: audit_log
-- Stores timestamped records of admin actions and security events
-- Requirements: 1.1, 1.2, 1.3
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    actor_id INT UNSIGNED DEFAULT NULL,
    target_type VARCHAR(50) DEFAULT NULL,
    target_id INT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    metadata JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_created (event_type, created_at),
    INDEX idx_actor (actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
