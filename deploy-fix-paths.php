<?php
/**
 * Post-deploy path fixer for cPanel.
 * Patches admin/ and client/ PHP files to use correct include paths.
 *
 * On cPanel: admin/ and client/ are inside public_html/
 * So '../includes/' needs to become '../../includes/'
 * And '../vendor/' needs to become '../../vendor/'
 */

$basePath = '/home/auruwlzj/public_html';

$dirs = [
    $basePath . '/admin',
    $basePath . '/client',
    $basePath . '/client/api',
];

$replacements = [
    "__DIR__ . '/../includes/" => "__DIR__ . '/../../includes/",
    "__DIR__ . '/../vendor/"  => "__DIR__ . '/../../vendor/",
];

$count = 0;

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    
    foreach (glob($dir . '/*.php') as $file) {
        $content = file_get_contents($file);
        $original = $content;
        
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }
        
        if ($content !== $original) {
            file_put_contents($file, $content);
            $count++;
        }
    }
}

echo "Patched {$count} files.\n";
