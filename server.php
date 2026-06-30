<?php
/**
 * PHP Built-in Server Router
 * 
 * Usage: php -S localhost:8000 server.php
 * 
 * Routes requests to the appropriate PHP files based on URL path.
 * Serves static assets (CSS, JS, images) directly.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly if they exist on disk
// Check both project root and public_html/ for the file
$filePath = __DIR__ . $uri;
if (!file_exists($filePath) || !is_file($filePath)) {
  $filePath = __DIR__ . '/public_html' . $uri;
}
if ($uri !== '/' && file_exists($filePath) && is_file($filePath)) {
  $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
  
  // Set proper MIME types for static assets
  $mimeTypes = [
    'css' => 'text/css',
    'js'  => 'application/javascript',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
    'woff' => 'font/woff',
    'woff2'=> 'font/woff2',
    'ttf' => 'font/ttf',
    'json' => 'application/json',
  ];
  
  if (isset($mimeTypes[$ext])) {
    header('Content-Type: ' . $mimeTypes[$ext]);
    readfile($filePath);
    return true;
  }
  
  // For PHP files, include them
  if ($ext === 'php') {
    require $filePath;
    return true;
  }
  
  // Let PHP's built-in server handle other file types
  return false;
}

// Route / to homepage
if ($uri === '/' || $uri === '') {
  require __DIR__ . '/public_html/index.php';
  return true;
}

// Try the exact path as a PHP file (check project root first, then public_html)
if (file_exists(__DIR__ . $uri . '.php')) {
  require __DIR__ . $uri . '.php';
  return true;
}
if (file_exists(__DIR__ . '/public_html' . $uri . '.php')) {
  require __DIR__ . '/public_html' . $uri . '.php';
  return true;
}

// 404
http_response_code(404);
echo '<!DOCTYPE html><html><head><title>404</title></head><body style="background:#1a1a2e;color:#e0e0e0;font-family:sans-serif;text-align:center;padding:50px;"><h1 style="color:#c9a227;">404 - Page Not Found</h1><p>The requested page was not found.</p><a href="/index.php" style="color:#c9a227;">Go to Homepage</a></body></html>';
return true;
