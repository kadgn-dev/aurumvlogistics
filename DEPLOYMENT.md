# cPanel Deployment Guide - Aurum Vault Logistics

## Directory Structure on cPanel

On a typical cPanel hosting account, the structure looks like:

```
/home/yourusername/
├── public_html/          ← Document root (web-accessible)
│   ├── index.php
│   ├── login.php
│   ├── register.php
│   ├── about.php
│   ├── contact.php
│   ├── faq.php
│   ├── pricing.php
│   ├── shipping.php
│   ├── vault-storage.php
│   ├── logout.php
│   ├── .htaccess
│   ├── assets/           ← CSS, JS, images
│   ├── admin/            ← Admin panel pages
│   ├── client/           ← Client portal pages
│   └── uploads/          ← User uploads (KYC docs, etc.)
├── includes/             ← PHP logic (NOT web-accessible)
├── database/             ← Schema files (NOT web-accessible)
├── vendor/               ← Composer dependencies (NOT web-accessible)
├── composer.json
└── .htaccess             ← Root-level protection (belt & suspenders)
```

## Step-by-Step Deployment

### 1. Create MySQL Database

1. In cPanel → **MySQL Databases**
2. Create a new database (e.g., `yourusername_goldbodvault`)
3. Create a database user with a strong password
4. Add the user to the database with **All Privileges**
5. Import the schema:
   - Go to **phpMyAdmin**
   - Select your database
   - Click **Import** → choose `database/schema.sql` → click **Go**

### 2. Configure Production Settings

Edit `includes/config.php` with your cPanel MySQL credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'auruwlzj_aurumvault');
define('DB_USER', 'auruwlzj_lyfe');
define('DB_PASSWORD', 'oCCeans3484@');
```

Update the APP_URL:
```php
define('APP_URL', 'https://www.aurumvlogistics.com');
```

### 3. Remove Local Development Config

Delete or rename `includes/config.local.php` on the server so it doesn't override
the MySQL config with SQLite settings.

### 4. Upload Files via File Manager or FTP

Upload to your cPanel account like this:

| Local Path          | cPanel Path                        |
|---------------------|------------------------------------|
| `public_html/*`     | `/home/user/public_html/`          |
| `admin/*`           | `/home/user/public_html/admin/`    |
| `client/*`          | `/home/user/public_html/client/`   |
| `includes/*`        | `/home/user/includes/`             |
| `vendor/*`          | `/home/user/vendor/`               |
| `database/`         | `/home/user/database/`             |
| `.htaccess` (root)  | `/home/user/public_html/.htaccess` |

**Important:** The `includes/`, `vendor/`, and `database/` directories go
ABOVE `public_html/` so they're not directly accessible from the web.

### 5. Install Composer Dependencies (if not uploading vendor/)

If your cPanel has SSH access (Terminal):
```bash
cd /home/yourusername
composer install --no-dev --optimize-autoloader
```

If no SSH, upload the entire `vendor/` folder via FTP.

### 6. Create Uploads Directory

```bash
mkdir -p /home/yourusername/public_html/uploads/kyc
chmod 755 /home/yourusername/public_html/uploads
chmod 755 /home/yourusername/public_html/uploads/kyc
```

### 7. Set File Permissions

```bash
# Directories: 755
find /home/yourusername/public_html -type d -exec chmod 755 {} \;
find /home/yourusername/includes -type d -exec chmod 755 {} \;

# PHP files: 644
find /home/yourusername/public_html -type f -exec chmod 644 {} \;
find /home/yourusername/includes -type f -exec chmod 644 {} \;

# Config file with credentials: more restrictive
chmod 640 /home/yourusername/includes/config.php
```

### 8. SSL Certificate

1. In cPanel → **SSL/TLS** or **Let's Encrypt**
2. Install a free Let's Encrypt certificate for your domain
3. The `.htaccess` already enforces HTTPS redirect

### 9. PHP Version

Ensure PHP 8.0+ is selected:
1. cPanel → **MultiPHP Manager** or **Select PHP Version**
2. Set your domain to PHP 8.0, 8.1, or 8.2
3. Enable these extensions: `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`

## Security Checklist

- [x] `.htaccess` blocks access to sensitive files
- [x] `includes/` directory is above document root
- [x] `vendor/` directory is above document root
- [x] `database/` directory is above document root
- [x] HTTPS enforced via `.htaccess`
- [x] Security headers configured
- [x] Directory listing disabled
- [x] PHP errors hidden in production
- [ ] Remove `config.local.php` from server
- [ ] Remove `phpunit.xml` and `tests/` from server
- [ ] Remove `.git/` directory from server
- [ ] Set strong DB password

## Email Configuration (Optional)

If you need the platform to send emails (registration, notifications):
1. Set up an email account in cPanel
2. Update `includes/mailer.php` with SMTP settings:
   - Host: `mail.yourdomain.com` or `localhost`
   - Port: 465 (SSL) or 587 (TLS)
   - Username/Password: your cPanel email credentials

## Troubleshooting

- **500 Error**: Check `.htaccess` syntax, PHP version, or error logs in cPanel → Error Log
- **Database connection failed**: Verify credentials in `config.php`, ensure user has privileges
- **Blank page**: Enable error display temporarily in PHP settings or check `error_log`
- **Permission denied on uploads**: Ensure `uploads/` dir is writable (755 or 775)
