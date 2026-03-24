<?php
/**
 * Admin API — JSON endpoints for all admin operations
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireRole('admin');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ── Dashboard Stats ──────────────────────────────────────
    case 'dashboard_stats':
        $stats = getDashboardStats($pdo);

        $branchData = $pdo->query("
            SELECT s.branch, COUNT(*) as total,
                   SUM(CASE WHEN s.placement_status='Placed' THEN 1 ELSE 0 END) as placed
            FROM students s WHERE s.branch IS NOT NULL GROUP BY s.branch
        ")->fetchAll();

        $monthlyApps = $pdo->query("
            SELECT DATE_FORMAT(applied_at,'%b') as month, COUNT(*) as count
            FROM applications WHERE applied_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(applied_at,'%Y-%m') ORDER BY MIN(applied_at)
        ")->fetchAll();

        $recentStudents = $pdo->query("
            SELECT s.*, u.email FROM students s JOIN users u ON s.user_id=u.id
            ORDER BY s.created_at DESC LIMIT 5
        ")->fetchAll();

        $upcomingInterviews = $pdo->query("
            SELECT i.*, CONCAT(s.first_name,' ',s.last_name) as student_name,
                   c.company_name, o.title as job_title
            FROM interviews i
            JOIN applications a ON i.application_id=a.id
            JOIN students s ON a.student_id=s.id
            JOIN opportunities o ON a.opportunity_id=o.id
            JOIN companies c ON o.company_id=c.id
            WHERE i.interview_date >= CURDATE() AND i.status='Scheduled'
            ORDER BY i.interview_date, i.interview_time LIMIT 5
        ")->fetchAll();

        echo json_encode([
            'stats' => $stats,
            'branchData' => $branchData,
            'monthlyApps' => $monthlyApps,
            'recentStudents' => $recentStudents,
            'upcomingInterviews' => $upcomingInterviews
        ]);
        break;

    // ── Students ─────────────────────────────────────────────
    case 'get_students':
        $where = "1=1"; $params = [];
        if (!empty($_GET['search'])) {
            $s = '%'.$_GET['search'].'%';
            $where .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.roll_number LIKE ? OR u.email LIKE ?)";
            $params = array_merge($params, [$s,$s,$s,$s]);
        }
        if (!empty($_GET['branch']))  { $where .= " AND s.branch=?";           $params[] = $_GET['branch']; }
        if (!empty($_GET['year']))    { $where .= " AND s.year=?";             $params[] = $_GET['year']; }
        if (!empty($_GET['status']))  { $where .= " AND s.placement_status=?"; $params[] = $_GET['status']; }

        $stmt = $pdo->prepare("SELECT s.*, u.email, u.is_active FROM students s JOIN users u ON s.user_id=u.id WHERE $where ORDER BY s.created_at DESC");
        $stmt->execute($params);
        echo json_encode(['students' => $stmt->fetchAll()]);
        break;

    case 'verify_student':
        $id = intval($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE students SET profile_verified=1 WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    case 'delete_student':
        $id = intval($_POST['id'] ?? 0);
        $s = $pdo->prepare("SELECT user_id FROM students WHERE id=?"); $s->execute([$id]); $s = $s->fetch();
        if ($s) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$s['user_id']]);
        echo json_encode(['success' => true]);
        break;

    // ── Companies ────────────────────────────────────────────
    case 'get_companies':
        $where = "1=1"; $params = [];
        if (!empty($_GET['search'])) { $where .= " AND c.company_name LIKE ?"; $params[] = '%'.$_GET['search'].'%'; }
        if (isset($_GET['status']) && $_GET['status'] !== '') { $where .= " AND c.is_approved=?"; $params[] = intval($_GET['status']); }

        $stmt = $pdo->prepare("SELECT c.*, u.email, u.is_active FROM companies c JOIN users u ON c.user_id=u.id WHERE $where ORDER BY c.created_at DESC");
        $stmt->execute($params);
        echo json_encode(['companies' => $stmt->fetchAll()]);
        break;

    case 'approve_company':
        $id = intval($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE companies SET is_approved=1 WHERE id=?")->execute([$id]);
        $c = $pdo->prepare("SELECT user_id, company_name FROM companies WHERE id=?"); $c->execute([$id]); $c = $c->fetch();
        if ($c) sendNotification($pdo, $c['user_id'], 'Account Approved', 'Your company has been approved.', 'Success');
        echo json_encode(['success' => true]);
        break;

    case 'reject_company':
        $pdo->prepare("UPDATE companies SET is_approved=0 WHERE id=?")->execute([intval($_POST['id'] ?? 0)]);
        echo json_encode(['success' => true]);
        break;

    case 'delete_company':
        $id = intval($_POST['id'] ?? 0);
        $c = $pdo->prepare("SELECT user_id FROM companies WHERE id=?"); $c->execute([$id]); $c = $c->fetch();
        if ($c) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$c['user_id']]);
        echo json_encode(['success' => true]);
        break;

    // ── Opportunities ────────────────────────────────────────
    case 'get_opportunities':
        $where = "1=1"; $params = [];
        if (!empty($_GET['type']))     { $where .= " AND o.type=?";        $params[] = $_GET['type']; }
        if (!empty($_GET['status']))   { $where .= " AND o.status=?";      $params[] = $_GET['status']; }
        if (isset($_GET['approved']) && $_GET['approved'] !== '') { $where .= " AND o.is_approved=?"; $params[] = intval($_GET['approved']); }

        $stmt = $pdo->prepare("SELECT o.*, c.company_name, (SELECT COUNT(*) FROM applications a WHERE a.opportunity_id=o.id) as app_count FROM opportunities o JOIN companies c ON o.company_id=c.id WHERE $where ORDER BY o.created_at DESC");
        $stmt->execute($params);
        echo json_encode(['opportunities' => $stmt->fetchAll()]);
        break;

    case 'approve_opportunity':
        $pdo->prepare("UPDATE opportunities SET is_approved=1 WHERE id=?")->execute([intval($_POST['id'] ?? 0)]);
        echo json_encode(['success' => true]);
        break;

    case 'close_opportunity':
        $pdo->prepare("UPDATE opportunities SET status='Closed' WHERE id=?")->execute([intval($_POST['id'] ?? 0)]);
        echo json_encode(['success' => true]);
        break;

    case 'delete_opportunity':
        $pdo->prepare("DELETE FROM opportunities WHERE id=?")->execute([intval($_POST['id'] ?? 0)]);
        echo json_encode(['success' => true]);
        break;

    // ── Drives ───────────────────────────────────────────────
    case 'get_drives':
        $drives = $pdo->query("SELECT pd.*, c.company_name FROM placement_drives pd LEFT JOIN companies c ON pd.company_id=c.id ORDER BY pd.drive_date DESC")->fetchAll();
        $companies = $pdo->query("SELECT id, company_name FROM companies WHERE is_approved=1 ORDER BY company_name")->fetchAll();
        echo json_encode(['drives' => $drives, 'companies' => $companies]);
        break;

    case 'add_drive':
        $pdo->prepare("INSERT INTO placement_drives (title,description,company_id,drive_date,venue,eligibility_criteria,rounds) VALUES (?,?,?,?,?,?,?)")
            ->execute([
                sanitizeInput($_POST['title']),
                sanitizeInput($_POST['description'] ?? ''),
                intval($_POST['company_id'] ?? 0) ?: null,
                $_POST['drive_date'],
                sanitizeInput($_POST['venue'] ?? ''),
                sanitizeInput($_POST['eligibility'] ?? ''),
                sanitizeInput($_POST['rounds'] ?? '')
            ]);
        echo json_encode(['success' => true]);
        break;

    case 'update_drive_status':
        $pdo->prepare("UPDATE placement_drives SET status=? WHERE id=?")->execute([sanitizeInput($_POST['status']), intval($_POST['id'])]);
        echo json_encode(['success' => true]);
        break;

    // ── Interviews ───────────────────────────────────────────
    case 'get_interviews':
        $interviews = $pdo->query("
            SELECT i.*, CONCAT(s.first_name,' ',s.last_name) as student_name, u.email as student_email,
                   c.company_name, o.title as job_title
            FROM interviews i
            JOIN applications a ON i.application_id=a.id
            JOIN students s ON a.student_id=s.id JOIN users u ON s.user_id=u.id
            JOIN opportunities o ON a.opportunity_id=o.id
            JOIN companies c ON o.company_id=c.id
            ORDER BY i.interview_date DESC, i.interview_time DESC
        ")->fetchAll();

        $calEvents = $pdo->query("
            SELECT i.interview_date, COUNT(*) as cnt,
                   GROUP_CONCAT(CONCAT(c.company_name,' - ',s.first_name,' ',s.last_name) SEPARATOR '|') as details
            FROM interviews i
            JOIN applications a ON i.application_id=a.id
            JOIN students s ON a.student_id=s.id
            JOIN opportunities o ON a.opportunity_id=o.id
            JOIN companies c ON o.company_id=c.id
            WHERE i.status='Scheduled' GROUP BY i.interview_date
        ")->fetchAll(PDO::FETCH_ASSOC);

        $cal = [];
        foreach ($calEvents as $ce) $cal[$ce['interview_date']] = $ce;

        echo json_encode(['interviews' => $interviews, 'calendarEvents' => $cal]);
        break;

    // ── Placements ───────────────────────────────────────────
    case 'get_placements':
        $placements = $pdo->query("
            SELECT p.*, CONCAT(s.first_name,' ',s.last_name) as student_name, s.branch, s.roll_number, c.company_name
            FROM placements p JOIN students s ON p.student_id=s.id JOIN companies c ON p.company_id=c.id
            ORDER BY p.created_at DESC
        ")->fetchAll();
        $totalPlaced = $pdo->query("SELECT COUNT(*) FROM placements WHERE status IN ('Offered','Accepted','Joined')")->fetchColumn();
        $avgSalary = $pdo->query("SELECT AVG(CAST(REPLACE(REPLACE(salary,' LPA',''),' lpa','') AS DECIMAL(10,2))) FROM placements WHERE salary IS NOT NULL AND salary != ''")->fetchColumn();
        echo json_encode(['placements' => $placements, 'totalPlaced' => $totalPlaced, 'avgSalary' => $avgSalary ? number_format($avgSalary, 1) : null]);
        break;

    case 'add_placement':
        $studentId = intval($_POST['student_id'] ?? 0);
        $companyId = intval($_POST['company_id'] ?? 0);
        $jobTitle = sanitizeInput($_POST['job_title'] ?? '');
        $salary = sanitizeInput($_POST['salary'] ?? '');
        $joiningDate = $_POST['joining_date'] ?? null;
        $status = sanitizeInput($_POST['status'] ?? 'Offered');

        if (!$studentId || !$companyId || !$jobTitle) {
            echo json_encode(['success' => false, 'message' => 'Student, company, and job title are required.']);
            break;
        }

        $pdo->prepare("INSERT INTO placements (student_id, company_id, job_title, salary, joining_date, status) VALUES (?,?,?,?,?,?)")
            ->execute([$studentId, $companyId, $jobTitle, $salary, $joiningDate ?: null, $status]);

        // Update student placement status ONLY if Accepted or Joined
        if (in_array($status, ['Accepted', 'Joined'])) {
            $pdo->prepare("UPDATE students SET placement_status='Placed' WHERE id=?")->execute([$studentId]);
        }

        // Send notification to the student
        $stu = $pdo->prepare("SELECT user_id, first_name FROM students WHERE id=?"); $stu->execute([$studentId]); $stu = $stu->fetch();
        $comp = $pdo->prepare("SELECT company_name FROM companies WHERE id=?"); $comp->execute([$companyId]); $comp = $comp->fetch();
        if ($stu && $comp) {
            if (in_array($status, ['Accepted', 'Joined'])) {
                sendNotification($pdo, $stu['user_id'], '🎉 Placement Recorded!', "Congratulations {$stu['first_name']}! You have been placed at {$comp['company_name']} as {$jobTitle}.", 'Success');
            } else {
                sendNotification($pdo, $stu['user_id'], '📌 Placement Offer', "You have received an offer for {$jobTitle} from {$comp['company_name']}. Please review it in your dashboard.", 'Info');
            }
        }

        echo json_encode(['success' => true]);
        break;

    case 'get_students_list':
        $students = $pdo->query("SELECT s.id, s.first_name, s.last_name, s.roll_number, s.branch, s.placement_status FROM students s ORDER BY s.first_name, s.last_name")->fetchAll();
        $companies = $pdo->query("SELECT id, company_name FROM companies WHERE is_approved=1 ORDER BY company_name")->fetchAll();
        echo json_encode(['students' => $students, 'companies' => $companies]);
        break;

    // ── Announcements ────────────────────────────────────────
    case 'get_announcements':
        echo json_encode(['announcements' => $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll()]);
        break;

    case 'add_announcement':
        $title = sanitizeInput($_POST['title']);
        $content = sanitizeInput($_POST['content']);
        $target = sanitizeInput($_POST['target_role']);
        $priority = sanitizeInput($_POST['priority'] ?? 'Normal');
        $pdo->prepare("INSERT INTO announcements (title,content,target_role,priority,created_by) VALUES (?,?,?,?,?)")
            ->execute([$title, $content, $target, $priority, $_SESSION['user_id']]);
        $tw = $target === 'all' ? "1=1" : "role='$target'";
        $users = $pdo->query("SELECT id FROM users WHERE $tw AND is_active=1")->fetchAll();
        foreach ($users as $u) sendNotification($pdo, $u['id'], "📢 $title", substr($content,0,200), 'Info');
        echo json_encode(['success' => true]);
        break;

    case 'delete_announcement':
        $pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([intval($_POST['id'])]);
        echo json_encode(['success' => true]);
        break;

    // ── Reports ──────────────────────────────────────────────
    case 'get_reports':
        $placement = $pdo->query("SELECT CONCAT(s.first_name,' ',s.last_name) as student_name, s.roll_number, s.branch, s.year, c.company_name, p.job_title, p.salary, p.joining_date, p.status FROM placements p JOIN students s ON p.student_id=s.id JOIN companies c ON p.company_id=c.id ORDER BY p.created_at DESC")->fetchAll();
        $internship = $pdo->query("SELECT CONCAT(s.first_name,' ',s.last_name) as student_name, s.roll_number, s.branch, c.company_name, i.title, i.start_date, i.end_date, i.stipend, i.status FROM internships i JOIN students s ON i.student_id=s.id JOIN companies c ON i.company_id=c.id ORDER BY i.start_date DESC")->fetchAll();
        $compSummary = $pdo->query("SELECT c.company_name, COUNT(DISTINCT o.id) as total_opportunities, SUM(CASE WHEN o.type='Placement' THEN 1 ELSE 0 END) as placements, SUM(CASE WHEN o.type='Internship' THEN 1 ELSE 0 END) as internships, (SELECT COUNT(*) FROM applications a2 JOIN opportunities o2 ON a2.opportunity_id=o2.id WHERE o2.company_id=c.id) as total_apps, (SELECT COUNT(*) FROM placements p WHERE p.company_id=c.id) as hired FROM companies c LEFT JOIN opportunities o ON c.id=o.company_id WHERE c.is_approved=1 GROUP BY c.id, c.company_name ORDER BY hired DESC")->fetchAll();
        $branchStats = $pdo->query("SELECT s.branch, COUNT(*) as total, SUM(CASE WHEN s.placement_status='Placed' THEN 1 ELSE 0 END) as placed, SUM(CASE WHEN s.internship_status IN ('Active','Completed') THEN 1 ELSE 0 END) as interned, ROUND(AVG(s.cgpa),2) as avg_cgpa FROM students s WHERE s.branch IS NOT NULL GROUP BY s.branch ORDER BY s.branch")->fetchAll();
        echo json_encode(['placement' => $placement, 'internship' => $internship, 'companySummary' => $compSummary, 'branchStats' => $branchStats]);
        break;

    // ── Users ────────────────────────────────────────────────
    case 'get_users':
        echo json_encode(['users' => $pdo->query("SELECT u.*, CASE WHEN u.role='student' THEN (SELECT CONCAT(first_name,' ',last_name) FROM students WHERE user_id=u.id) WHEN u.role='company' THEN (SELECT company_name FROM companies WHERE user_id=u.id) ELSE 'Admin' END as display_name FROM users u ORDER BY u.created_at DESC")->fetchAll(), 'current_user_id' => $_SESSION['user_id']]);
        break;

    case 'get_approvals':
        $students = $pdo->query("SELECT s.*, u.email, u.is_active, u.created_at as reg_date FROM students s JOIN users u ON s.user_id = u.id ORDER BY u.is_active ASC, u.created_at DESC")->fetchAll();
        $companies = $pdo->query("SELECT c.*, u.email, u.is_active, u.created_at as reg_date FROM companies c JOIN users u ON c.user_id = u.id ORDER BY u.is_active ASC, u.created_at DESC")->fetchAll();
        echo json_encode(['students' => $students, 'companies' => $companies]);
        break;

    case 'toggle_user':
        $id = intval($_POST['id'] ?? 0);
        $active = intval($_POST['is_active'] ?? 0);
        if ($id != $_SESSION['user_id']) {
            $pdo->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$active, $id]);
            // If it's a company, also sync is_approved just in case
            $pdo->prepare("UPDATE companies SET is_approved=? WHERE user_id=?")->execute([$active, $id]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'delete_user':
        $id = intval($_POST['id'] ?? 0);
        if ($id != $_SESSION['user_id']) {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        }
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action.']);
}
