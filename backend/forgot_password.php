<?php
/**
 * Forgot Password - OTP-based reset via PHPMailer
 */
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/mailer.php';

startSecureSession();
if (isLoggedIn()) redirectToDashboard($_SESSION['role']);

$step  = $_SESSION['fp_step'] ?? 'email';   // email → otp → reset
$error = '';
$info  = '';

// Step 1: Enter email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_otp') {
        $email = trim($_POST['email'] ?? '');
        if (!validateEmail($email)) {
            $error = 'Enter a valid email.';
        } else {
            $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user) {
                $error = 'No active account found with this email.';
            } else {
                $otp = generateOTP();
                $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                $pdo->prepare("UPDATE users SET otp=?, otp_expiry=? WHERE id=?")->execute([$otp, $expiry, $user['id']]);
                sendOTPEmail($email, $email, $otp);
                $_SESSION['fp_email'] = $email;
                $_SESSION['fp_step']  = 'otp';
                $step = 'otp';
                $info = 'OTP has been sent to your email.';
            }
        }
    }

    if ($_POST['action'] === 'verify_otp') {
        $enteredOtp = trim($_POST['otp'] ?? '');
        $email = $_SESSION['fp_email'] ?? '';
        if (strlen($enteredOtp) !== 6) {
            $error = 'Enter a valid 6-digit OTP.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND otp = ? AND otp_expiry > NOW()");
            $stmt->execute([$email, $enteredOtp]);
            if ($stmt->fetch()) {
                $_SESSION['fp_step'] = 'reset';
                $step = 'reset';
            } else {
                $error = 'Invalid or expired OTP.';
            }
        }
    }

    if ($_POST['action'] === 'reset_password') {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $email    = $_SESSION['fp_email'] ?? '';
        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hashed = hashPassword($password);
            $pdo->prepare("UPDATE users SET password=?, otp=NULL, otp_expiry=NULL WHERE email=?")->execute([$hashed, $email]);
            unset($_SESSION['fp_step'], $_SESSION['fp_email']);
            header("Location: login.php?reset=1");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | CareerFlow</title>
    <link rel="stylesheet" href="/CareerFlow/frontend/css/style.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <div class="auth-logo">
        <div class="logo-icon">🔐</div>
        <h1>CareerFlow</h1>
        <p>Reset Your Password</p>
    </div>

    <?php if ($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($info): ?><div class="alert alert-success">✅ <?= htmlspecialchars($info) ?></div><?php endif; ?>

    <?php if ($step === 'email'): ?>
    <form method="POST">
        <input type="hidden" name="action" value="send_otp">
        <div class="form-group">
            <label class="form-label">Email Address</label>
            <input class="form-control" type="email" name="email" required placeholder="Enter your registered email">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Send OTP</button>
    </form>

    <?php elseif ($step === 'otp'): ?>
    <p style="color:var(--text-muted);font-size:.88rem;margin-bottom:16px;">OTP sent to <strong><?= htmlspecialchars($_SESSION['fp_email'] ?? '') ?></strong></p>
    <form method="POST">
        <input type="hidden" name="action" value="verify_otp">
        <div class="form-group">
            <label class="form-label">Enter OTP</label>
            <input class="form-control" type="text" name="otp" maxlength="6" required placeholder="6-digit OTP" style="letter-spacing:8px;font-size:1.2rem;text-align:center;">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Verify OTP</button>
    </form>
    <div style="margin-top:12px;text-align:center;">
        <span id="otpTimer" style="color:var(--text-muted);font-size:.82rem;"></span>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="send_otp">
            <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['fp_email'] ?? '') ?>">
            <button type="submit" id="resendBtn" class="btn btn-outline btn-sm" disabled>Resend OTP</button>
        </form>
    </div>
    <script src="/CareerFlow/frontend/js/main.js"></script>
    <script>startOTPTimer(60, 'otpTimer', 'resendBtn');</script>

    <?php elseif ($step === 'reset'): ?>
    <form method="POST" id="resetForm" onsubmit="return validateForm('resetForm')">
        <input type="hidden" name="action" value="reset_password">
        <div class="form-group">
            <label class="form-label">New Password</label>
            <input class="form-control" type="password" id="password" name="password" required placeholder="Min 8 characters">
            <span class="form-error"></span>
        </div>
        <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input class="form-control" type="password" id="confirm_password" name="confirm_password" required placeholder="Repeat password">
            <span class="form-error"></span>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Reset Password</button>
    </form>
    <?php endif; ?>

    <div class="auth-footer"><a href="login.php">← Back to Login</a></div>
</div>
<script src="/CareerFlow/frontend/js/main.js"></script>
</body>
</html>
