<?php
/**
 * Common utility functions (Shared)
 */

require_once __DIR__ . '/db.php';

// ── Notifications ────────────────────────────────────────────
function sendNotification($pdo, $userId, $title, $message, $type = 'Info') {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $title, $message, $type]);
}

function getUnreadNotifications($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getUnreadCount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetch()['cnt'];
}

function markAllRead($pdo, $userId) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
}

// ── File Upload ──────────────────────────────────────────────
function uploadFile($file, $allowedTypes, $uploadDir, $maxSize = 5242880) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error.'];
    }
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File too large (max 5MB).'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $validMimes = ['application/pdf','image/jpeg','image/png','image/jpg'];
    if (!in_array($mime, $validMimes) && !in_array($ext, ['pdf','jpg','jpeg','png'])) {
        return ['success' => false, 'message' => 'Invalid file content.'];
    }
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $newName = uniqid('', true) . '_' . time() . '.' . $ext;
    $dest = rtrim($uploadDir, '/') . '/' . $newName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'message' => 'Failed to save file.'];
    }
    return ['success' => true, 'filename' => $newName, 'path' => $dest, 'original' => $file['name']];
}

// ── Student helpers ──────────────────────────────────────────
function getStudentByUserId($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function getCompanyByUserId($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function getProfileCompletion($student) {
    $fields = ['first_name','last_name','phone','dob','gender','address','branch','year','cgpa','skills','linkedin','github'];
    $filled = 0;
    foreach ($fields as $f) {
        if (!empty($student[$f])) $filled++;
    }
    return round(($filled / count($fields)) * 100);
}

// ── Admin stats ──────────────────────────────────────────────
function getDashboardStats($pdo) {
    $stats = [];
    $stats['total_students']     = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $stats['total_companies']    = $pdo->query("SELECT COUNT(*) FROM companies WHERE is_approved=1")->fetchColumn();
    $stats['active_internships'] = $pdo->query("SELECT COUNT(*) FROM internships WHERE status='Active'")->fetchColumn();
    $stats['placed_students']    = $pdo->query("SELECT COUNT(*) FROM students WHERE placement_status='Placed'")->fetchColumn();
    $stats['open_opportunities'] = $pdo->query("SELECT COUNT(*) FROM opportunities WHERE status='Open' AND is_approved=1")->fetchColumn();
    $stats['total_applications'] = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
    return $stats;
}

// ── Flash messages ───────────────────────────────────────────
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function showFlash() {
    $flash = getFlash();
    if ($flash) {
        $cls = $flash['type'] === 'success' ? 'alert-success' : ($flash['type'] === 'error' ? 'alert-error' : 'alert-info');
        echo "<div class='alert $cls'>{$flash['message']}</div>";
    }
}
