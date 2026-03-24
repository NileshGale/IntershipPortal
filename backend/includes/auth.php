<?php
/**
 * Authentication & Session Helpers (Shared)
 */

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Base URL constant
define('BASE_URL', '/CareerFlow/backend');

function isApiRequest() {
    return (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) || 
           (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false);
}

function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        
        if (isApiRequest()) {
            // For API requests, don't redirect. Let the script handle the "not logged in" state.
            return;
        }
        
        header("Location: " . BASE_URL . "/login.php?timeout=1");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function requireLogin($redirect = null) {
    if ($redirect === null) $redirect = BASE_URL . '/login.php';
    if (!isLoggedIn()) {
        header("Location: $redirect");
        exit();
    }
}

function requireRole($role, $redirect = null) {
    if ($redirect === null) $redirect = BASE_URL . '/login.php';
    requireLogin($redirect);
    if ($_SESSION['role'] !== $role) {
        header("Location: " . BASE_URL . "/login.php?error=unauthorized");
        exit();
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentRole() {
    return $_SESSION['role'] ?? null;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function redirectToDashboard($role) {
    switch ($role) {
        case 'admin':   header("Location: " . BASE_URL . "/admin/dashboard.php");   break;
        case 'student': header("Location: " . BASE_URL . "/student/dashboard.php"); break;
        case 'company': header("Location: " . BASE_URL . "/company/dashboard.php"); break;
        default:        header("Location: " . BASE_URL . "/login.php");             break;
    }
    exit();
}
