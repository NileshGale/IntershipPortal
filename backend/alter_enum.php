<?php
require 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE applications MODIFY COLUMN status ENUM('Applied','Reviewed','Shortlisted','Selected','Rejected','Interview Scheduled','Approved') DEFAULT 'Applied'");
    echo "Success";
} catch(Exception $e) {
    echo $e->getMessage();
}
