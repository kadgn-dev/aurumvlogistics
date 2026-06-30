<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/database/local.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$hash = password_hash('oCCeans3484@', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("UPDATE users SET email = :email, password_hash = :hash WHERE id = 1");
$stmt->execute(['email' => 'admin@aurumvlogistics.com', 'hash' => $hash]);
echo "Admin updated!\n  Email: admin@aurumvlogistics.com\n  Password: oCCeans3484@\n";
