<?php
/**
 * Registration Page - Students & Companies
 */
require_once 'includes/auth.php';
require_once 'includes/db.php';

startSecureSession();
if (isLoggedIn()) redirectToDashboard($_SESSION['role']);

$error   = '';
$success = '';
$role    = sanitizeInput($_GET['role'] ?? 'student');
if (!in_array($role, ['student', 'company'])) $role = 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role      = sanitizeInput($_POST['role'] ?? 'student');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if (!validateEmail($email)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'This email is already registered.';
        } else {
            $pdo->beginTransaction();
            try {
                $hashed = hashPassword($password);
                $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
                $stmt->execute([$email, $hashed, $role]);
                $userId = $pdo->lastInsertId();

                if ($role === 'student') {
                    $fn   = sanitizeInput($_POST['first_name'] ?? '');
                    $ln   = sanitizeInput($_POST['last_name'] ?? '');
                    $roll = sanitizeInput($_POST['roll_number'] ?? '');
                    $br   = sanitizeInput($_POST['branch'] ?? '');
                    $yr   = sanitizeInput($_POST['year'] ?? '');
                    $stmt = $pdo->prepare("INSERT INTO students (user_id, first_name, last_name, roll_number, branch, year) VALUES (?,?,?,?,?,?)");
                    $stmt->execute([$userId, $fn, $ln, $roll, $br, $yr]);
                } else {
                    $cname = sanitizeInput($_POST['company_name'] ?? '');
                    $hrn   = sanitizeInput($_POST['hr_name'] ?? '');
                    $stmt  = $pdo->prepare("INSERT INTO companies (user_id, company_name, hr_name, hr_email) VALUES (?,?,?,?)");
                    $stmt->execute([$userId, $cname, $hrn, $email]);
                }

                $pdo->commit();
                header("Location: login.php?registered=1");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Registration failed. Please try again.';
                error_log($e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | CareerFlow</title>
    <link rel="stylesheet" href="/CareerFlow/frontend/css/style.css">
</head>
<body class="auth-page">
<div class="auth-card" style="max-width:520px;">
    <div class="auth-logo">
        <div class="logo-icon">🎓</div>
        <h1>CareerFlow</h1>
        <p>Create your account</p>
    </div>

    <!-- Role selector -->
    <div style="display:flex;gap:8px;margin-bottom:20px;">
        <a href="?role=student" class="btn <?= $role==='student'?'btn-primary':'btn-outline' ?>" style="flex:1;justify-content:center;">🧑‍🎓 Student</a>
        <a href="?role=company" class="btn <?= $role==='company'?'btn-primary':'btn-outline' ?>" style="flex:1;justify-content:center;">🏢 Company</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" id="regForm" onsubmit="return validateForm('regForm')">
        <input type="hidden" name="role" value="<?= $role ?>">

        <?php if ($role === 'student'): ?>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">First Name *</label>
                <input class="form-control" type="text" name="first_name" required placeholder="First Name">
                <span class="form-error"></span>
            </div>
            <div class="form-group">
                <label class="form-label">Last Name *</label>
                <input class="form-control" type="text" name="last_name" required placeholder="Last Name">
                <span class="form-error"></span>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Roll Number</label>
                <input class="form-control" type="text" name="roll_number" placeholder="e.g. CS2021001">
            </div>
            <div class="form-group">
                <label class="form-label">Branch *</label>
                <select class="form-select" name="branch" required>
                    <option value="">-- Select Branch --</option>
                    <option value="Computer Science">Computer Science</option>
                    <option value="IT">Information Technology</option>
                    <option value="Electronics">Electronics</option>
                    <option value="Mechanical">Mechanical</option>
                    <option value="Civil">Civil</option>
                    <option value="Electrical">Electrical</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Current Year *</label>
            <select class="form-select" name="year" required>
                <option value="">-- Select Year --</option>
                <option value="1st">1st Year</option>
                <option value="2nd">2nd Year</option>
                <option value="3rd">3rd Year</option>
                <option value="4th">4th Year</option>
            </select>
            <span class="form-error"></span>
        </div>

        <?php else: ?>
        <div class="form-group">
            <label class="form-label">Company Name *</label>
            <input class="form-control" type="text" name="company_name" required placeholder="Company Name">
            <span class="form-error"></span>
        </div>
        <div class="form-group">
            <label class="form-label">HR Contact Name *</label>
            <input class="form-control" type="text" name="hr_name" required placeholder="HR Contact Name">
            <span class="form-error"></span>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Email Address *</label>
            <input class="form-control" type="email" name="email" required placeholder="your@email.com">
            <span class="form-error"></span>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Password *</label>
                <input class="form-control" type="password" id="password" name="password" required placeholder="Min 8 characters">
                <span class="form-error"></span>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm Password *</label>
                <input class="form-control" type="password" id="confirm_password" name="confirm_password" required placeholder="Repeat password">
                <span class="form-error"></span>
            </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Create Account</button>
    </form>

    <div class="auth-footer">Already have an account? <a href="login.php">Sign In</a></div>
</div>
<script src="/CareerFlow/frontend/js/main.js"></script>
</body>
</html>
