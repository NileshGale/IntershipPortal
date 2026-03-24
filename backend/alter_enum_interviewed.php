<?php
require 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE applications MODIFY COLUMN status ENUM('Applied','Reviewed','Shortlisted','Selected','Rejected','Interview Scheduled','Approved','Interviewed') DEFAULT 'Applied'");
    echo "Success: Added Interviewed to Enum.";
} catch(Exception $e) {
    echo $e->getMessage();
}
