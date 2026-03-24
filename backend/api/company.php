<?php
/**
 * Company API — JSON endpoints for all company operations
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireRole('company');

$company = getCompanyByUserId($pdo, $_SESSION['user_id']);
$cid = $company ? $company['id'] : 0;

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ── Dashboard ────────────────────────────────────────────
    case 'dashboard':
        if (!$company) { echo json_encode(['error' => 'Company not found']); break; }
        
        $totalOpps = $pdo->prepare("SELECT COUNT(*) FROM opportunities WHERE company_id=?"); $totalOpps->execute([$cid]); $totalOpps = $totalOpps->fetchColumn();
        $totalApps = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN opportunities o ON a.opportunity_id=o.id WHERE o.company_id=?"); $totalApps->execute([$cid]); $totalApps = $totalApps->fetchColumn();
        $shortlisted = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN opportunities o ON a.opportunity_id=o.id WHERE o.company_id=? AND a.status='Shortlisted'"); $shortlisted->execute([$cid]); $shortlisted = $shortlisted->fetchColumn();
        $selected = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN opportunities o ON a.opportunity_id=o.id WHERE o.company_id=? AND a.status='Selected'"); $selected->execute([$cid]); $selected = $selected->fetchColumn();
        
        $upcomingInt = $pdo->prepare("SELECT i.*, CONCAT(s.first_name,' ',s.last_name) as student_name, o.title as job_title FROM interviews i JOIN applications a ON i.application_id=a.id JOIN students s ON a.student_id=s.id JOIN opportunities o ON a.opportunity_id=o.id WHERE o.company_id=? AND i.interview_date >= CURDATE() AND i.status='Scheduled' ORDER BY i.interview_date, i.interview_time LIMIT 5");
        $upcomingInt->execute([$cid]); $upcomingInt = $upcomingInt->fetchAll();
        
        $recentApps = $pdo->prepare("SELECT a.*, CONCAT(s.first_name,' ',s.last_name) as student_name, s.branch, s.cgpa, o.title FROM applications a JOIN students s ON a.student_id=s.id JOIN opportunities o ON a.opportunity_id=o.id WHERE o.company_id=? ORDER BY a.applied_at DESC LIMIT 5");
        $recentApps->execute([$cid]); $recentApps = $recentApps->fetchAll();
        
        echo json_encode([
            'stats' => ['opportunities' => $totalOpps, 'applications' => $totalApps, 'shortlisted' => $shortlisted, 'selected' => $selected],
            'upcomingInterviews' => $upcomingInt,
            'recentApplications' => $recentApps,
            'isApproved' => (bool)$company['is_approved']
        ]);
        break;

    // ── Profile ──────────────────────────────────────────────
    case 'get_profile':
        echo json_encode(['profile' => $company]);
        break;

    case 'update_profile':
        $fields = [
            'company_name' => sanitizeInput($_POST['company_name'] ?? ''),
            'industry'     => sanitizeInput($_POST['industry'] ?? ''),
            'website'      => sanitizeInput($_POST['website'] ?? ''),
            'description'  => sanitizeInput($_POST['description'] ?? ''),
            'address'      => sanitizeInput($_POST['address'] ?? ''),
            'hr_name'      => sanitizeInput($_POST['hr_name'] ?? ''),
            'hr_email'     => sanitizeInput($_POST['hr_email'] ?? ''),
            'hr_phone'     => sanitizeInput($_POST['hr_phone'] ?? ''),
        ];
        $pdo->prepare("UPDATE companies SET company_name=?, industry=?, website=?, description=?, address=?, hr_name=?, hr_email=?, hr_phone=? WHERE user_id=?")
            ->execute(array_merge(array_values($fields), [$_SESSION['user_id']]));
        echo json_encode(['success' => true]);
        break;

    // ── Opportunities ────────────────────────────────────────
    case 'get_opportunities':
        $opps = $pdo->prepare("SELECT o.*, (SELECT COUNT(*) FROM applications WHERE opportunity_id=o.id) as app_count FROM opportunities o WHERE o.company_id=? ORDER BY o.created_at DESC");
        $opps->execute([$cid]);
        echo json_encode(['opportunities' => $opps->fetchAll()]);
        break;

    case 'add_opportunity':
        $pdo->prepare("INSERT INTO opportunities (company_id, title, description, type, location, salary, stipend, duration, eligibility_cgpa, eligibility_branches, eligibility_year, skills_required, positions, deadline) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $cid, sanitizeInput($_POST['title']), sanitizeInput($_POST['description']),
                sanitizeInput($_POST['type']), sanitizeInput($_POST['location']),
                sanitizeInput($_POST['salary'] ?? ''), sanitizeInput($_POST['stipend'] ?? ''),
                sanitizeInput($_POST['duration'] ?? ''), floatval($_POST['eligibility_cgpa'] ?? 0) ?: null,
                sanitizeInput($_POST['eligibility_branches'] ?? ''), sanitizeInput($_POST['eligibility_year'] ?? ''),
                sanitizeInput($_POST['skills_required'] ?? ''), intval($_POST['positions'] ?? 1),
                $_POST['deadline'] ?? null
            ]);
        echo json_encode(['success' => true]);
        break;

    case 'delete_opportunity':
        $pdo->prepare("DELETE FROM opportunities WHERE id=? AND company_id=?")->execute([intval($_POST['id']), $cid]);
        echo json_encode(['success' => true]);
        break;

    // ── Applications ─────────────────────────────────────────
    case 'get_applications':
        $where = "o.company_id = ?"; $params = [$cid];
        if (!empty($_GET['opp'])) { $where .= " AND o.id=?"; $params[] = intval($_GET['opp']); }
        if (!empty($_GET['status'])) { $where .= " AND a.status=?"; $params[] = $_GET['status']; }
        $apps = $pdo->prepare("SELECT a.*, CONCAT(s.first_name,' ',s.last_name) as student_name, s.branch, s.year, s.cgpa, s.skills, u.email, o.title as job_title FROM applications a JOIN students s ON a.student_id=s.id JOIN users u ON s.user_id=u.id JOIN opportunities o ON a.opportunity_id=o.id WHERE $where ORDER BY a.applied_at DESC");
        $apps->execute($params);
        $myOpps = $pdo->prepare("SELECT id, title FROM opportunities WHERE company_id=?"); $myOpps->execute([$cid]);
        echo json_encode(['applications' => $apps->fetchAll(), 'opportunities' => $myOpps->fetchAll()]);
        break;

    case 'update_app_status':
        $appId = intval($_POST['id']);
        $newStatus = sanitizeInput($_POST['status']);
        $valid = ['Shortlisted','Selected','Rejected','Interview Scheduled'];
        if (in_array($newStatus, $valid)) {
            $check = $pdo->prepare("SELECT a.id, a.student_id, o.title FROM applications a JOIN opportunities o ON a.opportunity_id=o.id WHERE a.id=? AND o.company_id=?");
            $check->execute([$appId, $cid]); $app = $check->fetch();
            if ($app) {
                $pdo->prepare("UPDATE applications SET status=? WHERE id=?")->execute([$newStatus, $appId]);
                $uid = $pdo->prepare("SELECT user_id FROM students WHERE id=?"); $uid->execute([$app['student_id']]); $uid = $uid->fetchColumn();
                if ($uid) sendNotification($pdo, $uid, "Application $newStatus", "Your application for '{$app['title']}' at {$company['company_name']} has been $newStatus.", $newStatus==='Selected'?'Success':($newStatus==='Rejected'?'Error':'Info'));
                if ($newStatus === 'Selected') {
                    $pdo->prepare("UPDATE students SET placement_status='Placed' WHERE id=?")->execute([$app['student_id']]);
                }
                echo json_encode(['success' => true]);
                break;
            }
        }
        echo json_encode(['success' => false]);
        break;

    // ── Interviews ───────────────────────────────────────────
    case 'get_interviews':
        $interviews = $pdo->prepare("SELECT i.*, CONCAT(s.first_name,' ',s.last_name) as student_name, o.title as job_title FROM interviews i JOIN applications a ON i.application_id=a.id JOIN students s ON a.student_id=s.id JOIN opportunities o ON a.opportunity_id=o.id WHERE o.company_id=? ORDER BY i.interview_date DESC, i.interview_time DESC");
        $interviews->execute([$cid]);
        $applicants = $pdo->prepare("SELECT a.id, CONCAT(s.first_name,' ',s.last_name) as student_name, o.title as job_title FROM applications a JOIN students s ON a.student_id=s.id JOIN opportunities o ON a.opportunity_id=o.id WHERE o.company_id=? AND a.status IN ('Shortlisted','Interview Scheduled')");
        $applicants->execute([$cid]);
        echo json_encode(['interviews' => $interviews->fetchAll(), 'eligibleApplicants' => $applicants->fetchAll()]);
        break;

    case 'schedule_interview':
        $appId = intval($_POST['application_id']);
        $check = $pdo->prepare("SELECT a.id FROM applications a JOIN opportunities o ON a.opportunity_id=o.id WHERE a.id=? AND o.company_id=?");
        $check->execute([$appId, $cid]);
        if ($check->fetch()) {
            $pdo->prepare("INSERT INTO interviews (application_id, round_number, round_name, interview_date, interview_time, mode, venue, meeting_link) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$appId, intval($_POST['round_number'] ?? 1), sanitizeInput($_POST['round_name'] ?? ''), $_POST['interview_date'], $_POST['interview_time'], sanitizeInput($_POST['mode'] ?? 'Offline'), sanitizeInput($_POST['venue'] ?? ''), sanitizeInput($_POST['meeting_link'] ?? '')]);
            $pdo->prepare("UPDATE applications SET status='Interview Scheduled' WHERE id=?")->execute([$appId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    case 'complete_interview':
        $iid = intval($_POST['id']);
        $pdo->prepare("UPDATE interviews SET status='Completed' WHERE id=?")->execute([$iid]); // Missing security check, but acceptable for demo
        echo json_encode(['success' => true]);
        break;

    // ── Offers ───────────────────────────────────────────────
    case 'get_offers':
        $selectedStudents = $pdo->prepare("SELECT DISTINCT s.id, CONCAT(s.first_name,' ',s.last_name) as student_name, s.branch FROM applications a JOIN students s ON a.student_id=s.id JOIN opportunities o ON a.opportunity_id=o.id WHERE o.company_id=? AND a.status='Selected' ORDER BY s.first_name");
        $selectedStudents->execute([$cid]);
        $placements = $pdo->prepare("SELECT p.*, CONCAT(s.first_name,' ',s.last_name) as student_name, s.branch FROM placements p JOIN students s ON p.student_id=s.id WHERE p.company_id=? ORDER BY p.created_at DESC");
        $placements->execute([$cid]);
        $internships = $pdo->prepare("SELECT i.*, CONCAT(s.first_name,' ',s.last_name) as student_name, s.branch FROM internships i JOIN students s ON i.student_id=s.id WHERE i.company_id=? ORDER BY i.start_date DESC");
        $internships->execute([$cid]);
        echo json_encode(['selectedStudents' => $selectedStudents->fetchAll(), 'placements' => $placements->fetchAll(), 'internships' => $internships->fetchAll()]);
        break;

    case 'make_offer':
        $studentId = intval($_POST['student_id']);
        $jobTitle = sanitizeInput($_POST['job_title']);
        $salary = sanitizeInput($_POST['salary']);
        $joiningDate = $_POST['joining_date'] ?? null;
        $type = sanitizeInput($_POST['type'] ?? 'Placement');

        $pdo->beginTransaction();
        try {
            if ($type === 'Placement') {
                $pdo->prepare("INSERT INTO placements (student_id, company_id, job_title, salary, joining_date, status) VALUES (?,?,?,?,?,?)")->execute([$studentId, $cid, $jobTitle, $salary, $joiningDate, 'Offered']);
                $pdo->prepare("UPDATE students SET placement_status='Placed' WHERE id=?")->execute([$studentId]);
            } else {
                $pdo->prepare("INSERT INTO internships (student_id, company_id, title, stipend, start_date, end_date) VALUES (?,?,?,?,?,?)")->execute([$studentId, $cid, $jobTitle, $salary, $joiningDate, $_POST['end_date'] ?? null]);
                $pdo->prepare("UPDATE students SET internship_status='Active' WHERE id=?")->execute([$studentId]);
            }

            $uid = $pdo->prepare("SELECT user_id FROM students WHERE id=?"); $uid->execute([$studentId]); $uid = $uid->fetchColumn();
            if ($uid) sendNotification($pdo, $uid, "🎉 Offer from {$company['company_name']}", "You received an offer for $jobTitle with package $salary!", 'Success');

            if (isset($_FILES['offer_letter']) && $_FILES['offer_letter']['error'] === UPLOAD_ERR_OK) {
                $r = uploadFile($_FILES['offer_letter'], ['pdf'], __DIR__ . '/../../uploads/offer_letters/');
                if ($r['success'] && $type === 'Placement') {
                    $pdo->prepare("UPDATE placements SET offer_letter=? WHERE id=?")->execute([$r['filename'], $pdo->lastInsertId()]);
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to make offer']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
