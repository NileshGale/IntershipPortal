<?php
require 'includes/db.php';
try {
    $stmt = $pdo->prepare("
        SELECT a.*, a.id as application_id, 
               CONCAT(s.first_name,' ',s.last_name) as student_name, 
               s.branch, s.year, s.cgpa, s.skills, s.roll_number,
               u.email as student_email,
               o.title as job_title, o.title,
               (SELECT d.file_name FROM documents d WHERE d.student_id=s.id AND d.doc_type='Resume' ORDER BY d.uploaded_at DESC LIMIT 1) as resume_file
        FROM applications a 
        JOIN students s ON a.student_id=s.id 
        JOIN users u ON s.user_id=u.id 
        JOIN opportunities o ON a.opportunity_id=o.id
    ");
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $json = json_encode(['applications' => $data]);
    if ($json === false) {
        echo "JSON Encode failed: " . json_last_error_msg() . "\n";
    } else {
        echo "JSON Encode Success! Length: " . strlen($json) . "\n";
    }
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
