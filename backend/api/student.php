<?php
/**
 * Student API — JSON endpoints for all student operations
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireRole('student');

$student = getStudentByUserId($pdo, $_SESSION['user_id']);
$sid = $student ? $student['id'] : 0;

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ── Dashboard ────────────────────────────────────────────
    case 'dashboard':
        if (!$student) { echo json_encode(['error' => 'Student not found']); break; }
        
        $totalApps = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id=?"); $totalApps->execute([$sid]); $totalApps = $totalApps->fetchColumn();
        $shortlisted = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id=? AND status='Shortlisted'"); $shortlisted->execute([$sid]); $shortlisted = $shortlisted->fetchColumn();
        $selected = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id=? AND status='Selected'"); $selected->execute([$sid]); $selected = $selected->fetchColumn();
        
        $interviews = $pdo->prepare("SELECT i.*, o.title as job_title, c.company_name FROM interviews i JOIN applications a ON i.application_id=a.id JOIN opportunities o ON a.opportunity_id=o.id JOIN companies c ON o.company_id=c.id WHERE a.student_id=? AND i.interview_date >= CURDATE() AND i.status='Scheduled' ORDER BY i.interview_date, i.interview_time LIMIT 3");
        $interviews->execute([$sid]); $interviews = $interviews->fetchAll();
        
        $recentApps = $pdo->prepare("SELECT a.*, o.title, o.type, c.company_name FROM applications a JOIN opportunities o ON a.opportunity_id=o.id JOIN companies c ON o.company_id=c.id WHERE a.student_id=? ORDER BY a.applied_at DESC LIMIT 5");
        $recentApps->execute([$sid]); $recentApps = $recentApps->fetchAll();
        
        $internship = $pdo->prepare("SELECT i.*, c.company_name FROM internships i JOIN companies c ON i.company_id=c.id WHERE i.student_id=? AND i.status='Active' LIMIT 1");
        $internship->execute([$sid]); $internship = $internship->fetch();
        
        $placement = $pdo->prepare("SELECT p.*, c.company_name FROM placements p JOIN companies c ON p.company_id=c.id WHERE p.student_id=? ORDER BY p.created_at DESC LIMIT 1");
        $placement->execute([$sid]); $placement = $placement->fetch();
        
        $announcements = $pdo->query("SELECT * FROM announcements WHERE target_role IN ('all','student') ORDER BY created_at DESC LIMIT 3")->fetchAll();
        
        echo json_encode([
            'stats' => ['apps' => $totalApps, 'shortlisted' => $shortlisted, 'selected' => $selected, 'cgpa' => $student['cgpa']],
            'interviews' => $interviews,
            'recentApps' => $recentApps,
            'internship' => $internship,
            'placement' => $placement,
            'announcements' => $announcements,
            'profilePct' => getProfileCompletion($student)
        ]);
        break;

    // ── Profile ──────────────────────────────────────────────
    case 'get_profile':
        echo json_encode(['profile' => $student]);
        break;

    case 'update_profile':
        $fields = [
            'first_name' => sanitizeInput($_POST['first_name'] ?? ''),
            'last_name'  => sanitizeInput($_POST['last_name'] ?? ''),
            'phone'      => sanitizeInput($_POST['phone'] ?? ''),
            'dob'        => $_POST['dob'] ?? null,
            'gender'     => sanitizeInput($_POST['gender'] ?? ''),
            'address'    => sanitizeInput($_POST['address'] ?? ''),
            'branch'     => sanitizeInput($_POST['branch'] ?? ''),
            'year'       => sanitizeInput($_POST['year'] ?? ''),
            'cgpa'       => floatval($_POST['cgpa'] ?? 0),
            'skills'     => sanitizeInput($_POST['skills'] ?? ''),
            'projects'   => sanitizeInput($_POST['projects'] ?? ''),
            'certifications' => sanitizeInput($_POST['certifications'] ?? ''),
            'linkedin'   => sanitizeInput($_POST['linkedin'] ?? ''),
            'github'     => sanitizeInput($_POST['github'] ?? ''),
        ];
        if ($student) {
            $pdo->prepare("UPDATE students SET first_name=?, last_name=?, phone=?, dob=?, gender=?, address=?, branch=?, year=?, cgpa=?, skills=?, projects=?, certifications=?, linkedin=?, github=? WHERE user_id=?")
                ->execute(array_merge(array_values($fields), [$_SESSION['user_id']]));
        } else {
            $pdo->prepare("INSERT INTO students (user_id, first_name, last_name, phone, dob, gender, address, branch, year, cgpa, skills, projects, certifications, linkedin, github) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute(array_merge([$_SESSION['user_id']], array_values($fields)));
        }
        echo json_encode(['success' => true]);
        break;

    // ── Documents ────────────────────────────────────────────
    case 'get_documents':
        $docs = $pdo->prepare("SELECT * FROM documents WHERE student_id=? ORDER BY uploaded_at DESC");
        $docs->execute([$sid]);
        echo json_encode(['documents' => $docs->fetchAll()]);
        break;

    case 'upload_document':
        $type = sanitizeInput($_POST['doc_type'] ?? '');
        $uploadDir = __DIR__ . '/../../uploads/documents/';
        $r = uploadFile($_FILES['document'], ['pdf','jpg','jpeg','png'], $uploadDir);
        if ($r['success']) {
            $pdo->prepare("INSERT INTO documents (student_id, doc_type, file_name, file_path, original_name) VALUES (?,?,?,?,?)")
                ->execute([$sid, $type, $r['filename'], $r['path'], $r['original']]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $r['message']]);
        }
        break;

    case 'delete_document':
        $doc = $pdo->prepare("SELECT * FROM documents WHERE id=? AND student_id=?");
        $doc->execute([intval($_POST['id']), $sid]);
        $doc = $doc->fetch();
        if ($doc) {
            @unlink($doc['file_path']);
            $pdo->prepare("DELETE FROM documents WHERE id=?")->execute([$doc['id']]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    // ── Opportunities & Applications ─────────────────────────
    case 'get_opportunities':
        $where = "o.status='Open' AND o.is_approved=1"; $params = [];
        if (!empty($_GET['type'])) { $where .= " AND o.type=?"; $params[] = $_GET['type']; }
        if (!empty($_GET['search'])) { $where .= " AND (o.title LIKE ? OR c.company_name LIKE ?)"; $s='%'.$_GET['search'].'%'; $params[]=$s; $params[]=$s; }
        $opps = $pdo->prepare("SELECT o.*, c.company_name, (SELECT id FROM applications WHERE student_id={$sid} AND opportunity_id=o.id) as already_applied FROM opportunities o JOIN companies c ON o.company_id=c.id WHERE $where ORDER BY o.created_at DESC");
        $opps->execute($params);
        echo json_encode(['opportunities' => $opps->fetchAll()]);
        break;

    case 'apply':
        $oid = intval($_POST['opportunity_id']);
        $check = $pdo->prepare("SELECT id FROM applications WHERE student_id=? AND opportunity_id=?");
        $check->execute([$sid, $oid]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO applications (student_id, opportunity_id, cover_letter) VALUES (?,?,?)")
                ->execute([$sid, $oid, sanitizeInput($_POST['cover_letter'] ?? '')]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Already applied']);
        }
        break;

    case 'get_applications':
        $apps = $pdo->prepare("SELECT a.*, o.title, o.type, o.location, o.salary, o.stipend, c.company_name FROM applications a JOIN opportunities o ON a.opportunity_id=o.id JOIN companies c ON o.company_id=c.id WHERE a.student_id=? ORDER BY a.applied_at DESC");
        $apps->execute([$sid]);
        echo json_encode(['applications' => $apps->fetchAll()]);
        break;

    // ── Interviews ───────────────────────────────────────────
    case 'get_interviews':
        $ivs = $pdo->prepare("SELECT i.*, o.title as job_title, c.company_name FROM interviews i JOIN applications a ON i.application_id=a.id JOIN opportunities o ON a.opportunity_id=o.id JOIN companies c ON o.company_id=c.id WHERE a.student_id=? ORDER BY i.interview_date DESC, i.interview_time DESC");
        $ivs->execute([$sid]);
        echo json_encode(['interviews' => $ivs->fetchAll()]);
        break;

    case 'respond_interview':
        $iid = intval($_POST['id']);
        $resp = $_POST['response'] === 'accept' ? 'Accepted' : 'Declined';
        $check = $pdo->prepare("SELECT i.id FROM interviews i JOIN applications a ON i.application_id=a.id WHERE i.id=? AND a.student_id=?");
        $check->execute([$iid, $sid]);
        if ($check->fetch()) {
            $pdo->prepare("UPDATE interviews SET student_response=? WHERE id=?")->execute([$resp, $iid]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    // ── Internships & Placements ─────────────────────────────
    case 'get_internship':
        $int = $pdo->prepare("SELECT i.*, c.company_name FROM internships i JOIN companies c ON i.company_id=c.id WHERE i.student_id=? ORDER BY i.start_date DESC");
        $int->execute([$sid]);
        echo json_encode(['internships' => $int->fetchAll()]);
        break;

    case 'upload_certificate':
        $iid = intval($_POST['id']);
        $r = uploadFile($_FILES['certificate'], ['pdf','jpg','jpeg','png'], __DIR__.'/../../uploads/certificates/');
        if ($r['success']) {
            $pdo->prepare("UPDATE internships SET completion_certificate=? WHERE student_id=? AND id=?")->execute([$r['filename'], $sid, $iid]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $r['message']]);
        }
        break;

    case 'get_placement':
        $p = $pdo->prepare("SELECT p.*, c.company_name, c.website, c.address as company_address FROM placements p JOIN companies c ON p.company_id=c.id WHERE p.student_id=? ORDER BY p.created_at DESC");
        $p->execute([$sid]);
        echo json_encode(['placements' => $p->fetchAll()]);
        break;

    // ── Notifications ────────────────────────────────────────
    case 'get_notifications':
        $n = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
        $n->execute([$_SESSION['user_id']]);
        echo json_encode(['notifications' => $n->fetchAll()]);
        break;

    case 'mark_read':
        markAllRead($pdo, $_SESSION['user_id']);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
