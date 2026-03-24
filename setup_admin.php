<?php
require_once __DIR__ . '/backend/includes/db.php';
$email = 'parinitapaigwar@gmail.com';
$password = '12345678';
$hashed = password_hash($password, PASSWORD_DEFAULT);
$pdo->query("DELETE FROM users WHERE role='admin'");
$stmt = $pdo->prepare("INSERT INTO users (email, password, role, is_active) VALUES (?, ?, 'admin', 1)");
$stmt->execute([$email, $hashed]);
echo "ADMIN CREATED";
