<?php
/**
 * Fullbright College - Student Management System
 * Application receipt with the auto-generated Student ID.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/idcard.php';

session_start();

$sid = $_SESSION['just_registered'] ?? null;

if (!$sid) {
    // Logged-in students can still print their info from the dashboard.
    header('Location: index.php');
    exit;
}

$pdo   = db();
$stmt  = $pdo->prepare('SELECT * FROM students WHERE student_id = ?');
$stmt->execute([$sid]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT document_name, file_path FROM requirements WHERE student_id = ? ORDER BY document_name');
$stmt->execute([$student['id']]);
$requirements = $stmt->fetchAll();

$year = date('Y');

// One-time credential display: read the generated password, then discard it
// so it can never be shown again (it is only stored hashed in the database).
$generatedPassword = $_SESSION['generated_password'] ?? null;
unset($_SESSION['generated_password']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Application Receipt | Fullbright College</title>
<link rel="stylesheet" href="css/style.css?v=20240921">
</head>
<body class="receipt-page">

<div class="page-backdrop" aria-hidden="true"></div>

<header class="site-header">
    <div class="brand">
        <img src="assets/logo.png" alt="Fullbright College Logo" class="brand-logo">
        <div class="brand-text">
            <h1>Fullbright College</h1>
            <p>Student Management System &mdash; Application Receipt</p>
        </div>
    </div>
</header>

<main class="page-main page-narrow">
    <section class="card receipt-card">
        <div class="receipt-head">
            <div class="receipt-brand">
                <img src="assets/logo.png" alt="Fullbright College" class="receipt-logo">
                <div>
                    <h2>Official Application Receipt</h2>
                    <p>Fullbright College &mdash; Student Enrollment</p>
                </div>
            </div>
            <div class="receipt-badge">
                <span class="badge badge-approved">Application Received</span>
            </div>
        </div>

        <div class="success-banner">
            <h3>Congratulations, <?php echo htmlspecialchars(full_name_with_suffix($student)); ?>!</h3>
            <p>Your enrollment application has been submitted. Please keep this receipt for your records.</p>
        </div>

        <!-- Student ID -->
        <?php echo render_id_card($student, ['qr' => true]); ?>

        <!-- Receipt body -->
        <div class="receipt-grid">
            <div class="receipt-block">
                <h4>Personal Information</h4>
                <dl class="review-grid">
                    <dt>Full Name</dt><dd><?php echo htmlspecialchars(full_name_with_suffix($student)); ?></dd>
                    <dt>Email</dt>
                    <dd>
                        <?php echo htmlspecialchars($student['email']); ?>
                        <?php if (!empty($student['email_verified'])): ?>
                            <span class="badge badge-approved">Verified</span>
                        <?php endif; ?>
                    </dd>
                    <dt>Birthday</dt><dd><?php echo date('F d, Y', strtotime($student['birthday'])); ?></dd>
                    <dt>Contact Number</dt><dd><?php echo htmlspecialchars($student['contact_no']); ?></dd>
                    <dt>Emergency Contact Name</dt><dd><?php echo htmlspecialchars($student['emergency_contact_name'] ?? '—'); ?></dd>
                    <dt>Emergency Contact Number</dt><dd><?php echo htmlspecialchars($student['emergency_contact_no']); ?></dd>
                    <dt>Status</dt><dd><?php echo htmlspecialchars($student['marital_status']); ?></dd>
                    <dt>Course / Program</dt><dd><?php echo htmlspecialchars($student['program']); ?></dd>
                    <dt>Department</dt><dd><?php echo htmlspecialchars($student['department']); ?></dd>
                </dl>
            </div>

            <div class="receipt-block">
                <h4>Entrance Exam</h4>
                <dl class="review-grid">
                    <dt>Exam Code</dt><dd><?php echo htmlspecialchars($student['exam_code'] ?? '—'); ?></dd>
                    <dt>Exam Score</dt>
                    <dd>
                        <span class="score-pill"><?php echo (int) $student['exam_score']; ?>/<?php echo (int) EXAM_QUESTION_COUNT; ?></span>
                    </dd>
                </dl>

                <h4>Submitted Documents</h4>
                <ul class="req-list receipt-reqs">
                    <?php foreach ($requirements as $r): ?>
                        <li>
                            <span><?php echo htmlspecialchars($r['document_name']); ?></span>
                            <span class="badge badge-approved">Received</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Login credentials -->
        <div class="cred-box">
            <h4>Your Login Credentials</h4>
            <p class="muted">Use these details to sign in to your Student Dashboard. Your password is shown <strong>only once</strong> on this receipt &mdash; please write it down and keep it safe.</p>
            <dl class="review-grid">
                <dt>Username (Student ID)</dt><dd><code><?php echo htmlspecialchars($student['student_id']); ?></code></dd>
                <dt>Password</dt>
                <dd>
                    <?php if ($generatedPassword !== null): ?>
                        <code class="cred-password"><?php echo htmlspecialchars($generatedPassword); ?></code>
                    <?php else: ?>
                        <span class="muted">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull; (shown only once)</span>
                    <?php endif; ?>
                </dd>
            </dl>
            <p class="muted">You can change your password anytime from your Profile &rarr; Change Password.</p>
            <div class="form-actions">
                <a href="login.php" class="btn btn-primary btn-lg">Proceed to Login &rarr;</a>
                <button type="button" class="btn btn-ghost" onclick="window.print()">Print Receipt</button>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <p>&copy; <?php echo $year; ?> Fullbright College &mdash; Student Management System. All rights reserved.</p>
</footer>
<script src="js/qrcode.js"></script>
<script src="js/script.js"></script>
</body>
</html>