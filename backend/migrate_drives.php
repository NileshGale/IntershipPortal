<?php
require_once __DIR__ . '/api/../includes/db.php';
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `drive_registrations` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `drive_id` INT NOT NULL,
          `student_id` INT NOT NULL,
          `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`drive_id`) REFERENCES `placement_drives`(`id`) ON DELETE CASCADE,
          FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
          UNIQUE KEY `unique_registration` (`drive_id`, `student_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Migration successful.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
