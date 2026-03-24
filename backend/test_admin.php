<?php
require 'includes/db.php';
$stmt = $pdo->query("SELECT email FROM users WHERE role = 'admin'");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($admins) > 0) {
    foreach ($admins as $admin) {
        echo "Admin Email Found: " . $admin['email'] . "\n";
    }
} else {
    echo "No admin found. Creating one...\n";
    $email = 'admin@careerflow.com';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (email, password, role, is_active) VALUES (?, ?, 'admin', 1)")->execute([$email, $password]);
    echo "Created new admin: Email: admin@careerflow.com | Password: admin123\n";
}
