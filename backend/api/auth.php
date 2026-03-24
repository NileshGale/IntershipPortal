<?php
/**
 * Auth API — JSON endpoints for login, register, logout, forgot password, session check
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ── Check Session ────────────────────────────────────────
    case 'check_session':
        if (isLoggedIn()) {
            $data = ['loggedIn' => true, 'role' => $_SESSION['role'], 'user_id' => $_SESSION['user_id'], 'email' => $_SESSION['email'] ?? ''];
            // Get display name
            if ($_SESSION['role'] === 'student') {
                $s = getStudentByUserId($pdo, $_SESSION['user_id']);
                $data['name'] = $s ? trim($s['first_name'] . ' ' . $s['last_name']) : 'Student';
                $data['profile_completion'] = $s ? getProfileCompletion($s) : 0;
            } elseif ($_SESSION['role'] === 'company') {
                $c = getCompanyByUserId($pdo, $_SESSION['user_id']);
                $data['name'] = $c ? $c['company_name'] : 'Company';
                $data['is_approved'] = $c ? (bool)$c['is_approved'] : false;
            } else {
                $data['name'] = 'Admin';
            }
            $data['unread_count'] = getUnreadCount($pdo, $_SESSION['user_id']);
            $data['notifications'] = getUnreadNotifications($pdo, $_SESSION['user_id']);
            echo json_encode($data);
        } else {
            echo json_encode(['loggedIn' => false]);
        }
        break;

    // ── Login ────────────────────────────────────────────────
    case 'login':
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!validateEmail($email)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
            break;
        }
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Password is required.']);
            break;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !verifyPassword($password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
            break;
        }

        if ($user['role'] !== 'admin' && $user['is_active'] == 0) {
            echo json_encode(['success' => false, 'message' => 'Your account is pending admin approval.']);
            break;
        }

        session_regenerate_id(true);
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['email']         = $user['email'];
        $_SESSION['last_activity'] = time();
        echo json_encode(['success' => true, 'role' => $user['role']]);
        break;

    // ── Register - Send OTP ──────────────────────────────────
    case 'register_send_otp':
        $role      = sanitizeInput($_POST['role'] ?? 'student');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        if (!in_array($role, ['student', 'company'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid role.']); break;
        }
        if (!validateEmail($email)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email.']); break;
        }
        if (strlen($password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']); break;
        }
        if ($password !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match.']); break;
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already registered.']); break;
        }

        $otp = generateOTP();
        $_SESSION['temp_reg'] = $_POST;
        $_SESSION['temp_reg']['otp'] = $otp;
        $_SESSION['temp_reg']['expires'] = time() + 600; // 10 mins

        $name = 'User';
        if ($role === 'student') {
            $name = sanitizeInput($_POST['first_name'] ?? '');
        } else {
            $name = sanitizeInput($_POST['hr_name'] ?? '');
        }

        try {
            require_once __DIR__ . '/../includes/mailer.php';
            sendOTPEmail($email, $name, $otp);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            error_log("Registration mail error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP email.']);
        }
        break;

    // ── Register - Verify OTP ────────────────────────────────
    case 'register_verify_otp':
        $otp = trim($_POST['otp'] ?? '');
        
        if (!isset($_SESSION['temp_reg'])) {
            echo json_encode(['success' => false, 'message' => 'Session expired. Please register again.']);
            break;
        }
        
        $regData = $_SESSION['temp_reg'];
        
        if ($otp !== $regData['otp'] || time() > $regData['expires']) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP.']);
            break;
        }

        $pdo->beginTransaction();
        try {
            $role     = $regData['role'];
            $email    = trim($regData['email']);
            $password = $regData['password'];
            $hashed   = hashPassword($password);
            
            $stmt = $pdo->prepare("INSERT INTO users (email, password, role, is_active) VALUES (?, ?, ?, 0)");
            $stmt->execute([$email, $hashed, $role]);
            $userId = $pdo->lastInsertId();

            if ($role === 'student') {
                $fn   = sanitizeInput($regData['first_name'] ?? '');
                $ln   = sanitizeInput($regData['last_name'] ?? '');
                $roll = sanitizeInput($regData['roll_number'] ?? '');
                $br   = sanitizeInput($regData['branch'] ?? '');
                $yr   = sanitizeInput($regData['year'] ?? '');
                $pdo->prepare("INSERT INTO students (user_id, first_name, last_name, roll_number, branch, year) VALUES (?,?,?,?,?,?)")
                    ->execute([$userId, $fn, $ln, $roll, $br, $yr]);
            } else {
                $cname = sanitizeInput($regData['company_name'] ?? '');
                $hrn   = sanitizeInput($regData['hr_name'] ?? '');
                $pdo->prepare("INSERT INTO companies (user_id, company_name, hr_name, hr_email) VALUES (?,?,?,?)")
                    ->execute([$userId, $cname, $hrn, $email]);
            }

            $pdo->commit();
            unset($_SESSION['temp_reg']);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log($e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Registration failed.']);
        }
        break;

    // ── Logout ───────────────────────────────────────────────
    case 'logout':
        session_unset();
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    // ── Send OTP ─────────────────────────────────────────────
    case 'send_otp':
        $email = trim($_POST['email'] ?? '');
        if (!validateEmail($email)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email.']); break;
        }
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'No account found.']); break;
        }
        $otp = generateOTP();
        $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $pdo->prepare("UPDATE users SET otp=?, otp_expiry=? WHERE id=?")->execute([$otp, $expiry, $user['id']]);
        // Try sending email; if mailer is not configured, still succeed for dev
        try {
            require_once __DIR__ . '/../includes/mailer.php';
            sendOTPEmail($email, $email, $otp);
        } catch (Exception $e) {
            error_log("Mailer not configured: " . $e->getMessage());
        }
        echo json_encode(['success' => true, 'message' => 'OTP sent to your email.', 'dev_otp' => $otp]);
        break;

    // ── Verify OTP ───────────────────────────────────────────
    case 'verify_otp':
        $email = trim($_POST['email'] ?? '');
        $otp   = trim($_POST['otp'] ?? '');
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND otp = ? AND otp_expiry > NOW()");
        $stmt->execute([$email, $otp]);
        if ($stmt->fetch()) {
            $_SESSION['fp_email'] = $email;
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP.']);
        }
        break;

    // ── Reset Password ───────────────────────────────────────
    case 'reset_password':
        $email    = $_SESSION['fp_email'] ?? trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        if (strlen($password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Min 8 characters.']); break;
        }
        if ($password !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match.']); break;
        }
        $hashed = hashPassword($password);
        $pdo->prepare("UPDATE users SET password=?, otp=NULL, otp_expiry=NULL WHERE email=?")->execute([$hashed, $email]);
        unset($_SESSION['fp_email']);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action.']);
}
