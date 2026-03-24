<?php
/**
 * Login Page
 */
require_once 'includes/auth.php';
require_once 'includes/db.php';

startSecureSession();

// Already logged in → redirect
if (isLoggedIn()) {
    redirectToDashboard($_SESSION['role']);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($password)) {
        $error = 'Password is required.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && verifyPassword($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['email']         = $user['email'];
            $_SESSION['last_activity'] = time();
            redirectToDashboard($user['role']);
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}

$timeout_msg = isset($_GET['timeout']) ? 'Your session expired. Please log in again.' : '';
$unauth_msg  = isset($_GET['error']) && $_GET['error'] === 'unauthorized' ? 'Access denied. Please login with the correct role.' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | CareerFlow</title>
    <link rel="stylesheet" href="/CareerFlow/frontend/css/style.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <div class="auth-logo">
        <div class="logo-icon">🎓</div>
        <h1>CareerFlow</h1>
        <p>Internship & Placement Tracking System</p>
    </div>

    <h2>Sign In</h2>

    <?php if ($error):      ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($timeout_msg):?><div class="alert alert-warning">⏱️ <?= htmlspecialchars($timeout_msg) ?></div><?php endif; ?>
    <?php if ($unauth_msg): ?><div class="alert alert-error">🚫 <?= htmlspecialchars($unauth_msg) ?></div><?php endif; ?>
    <?php if (isset($_GET['registered'])): ?><div class="alert alert-success">✅ Registration successful! Please log in.</div><?php endif; ?>
    <?php if (isset($_GET['reset'])): ?><div class="alert alert-success">✅ Password reset successful! Please log in.</div><?php endif; ?>

    <form method="POST" id="loginForm" onsubmit="return validateForm('loginForm')">
        <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <input class="form-control" type="email" id="email" name="email"
                   placeholder="you@example.com" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            <span class="form-error"></span>
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" type="password" id="password" name="password"
                   placeholder="Enter your password" required>
            <span class="form-error"></span>
        </div>
        <div style="text-align:right;margin-bottom:16px;">
            <a href="forgot_password.php" style="font-size:.85rem;">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Sign In</button>
    </form>

    <div class="divider"><span>New here?</span></div>
    <a href="register.php" class="btn btn-outline" style="width:100%;justify-content:center;">Create Account</a>

    <div class="auth-footer" style="margin-top:24px;font-size:.78rem;">
        <strong>Demo:</strong> admin@college.edu / Admin@123
    </div>
</div>
<script src="/CareerFlow/frontend/js/main.js"></script>
</body>
</html>
