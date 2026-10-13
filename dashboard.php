<?php
/**
 * Fullbright College - Student Management System
 * Student Dashboard: announcements, school events, teacher projects,
 * student section (auto-assigned, max 50) with schedule, and student
 * records placeholders (to be filled by the registrar later).
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/content.php';

session_start();

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$pdo     = db();
$stmt    = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$stmt->execute([$_SESSION['student_id']]);
$student = $stmt->fetch();

if (!$student) {
    session_destroy();
    header('Location: login.php');
    exit;
}

ensure_section($pdo, $student);

$year     = date('Y');
$semLabel = (int) date('n') < 7 ? '1st Semester' : '2nd Semester';

$section     = $student['section'];
$sectionInfo = section_description($section);

// Section fill: current number of students in this section.
$stmt = $pdo->prepare('SELECT COUNT(*) FROM students WHERE section = ?');
$stmt->execute([$section]);
$sectionCount = (int) $stmt->fetchColumn();
$sectionPct   = (int) round($sectionCount / SECTION_CAPACITY * 100);

$schedule = section_schedule($sectionInfo['acronym']);

$announcements = school_announcements();
$events        = school_events();
$projects      = teacher_projects();

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard | Fullbright College</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body class="dash-page">

<div class="page-backdrop" aria-hidden="true"></div>

<header class="site-header">
    <div class="brand">
        <img src="assets/logo.png" alt="Fullbright College Logo" class="brand-logo">
        <div class="brand-text">
            <h1>Fullbright College</h1>
            <p>Student Management System &mdash; Student Dashboard</p>
        </div>
    </div>
    <nav class="site-nav">
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php" class="btn btn-sm btn-ghost">Log out</a>
    </nav>
</header>

<main class="page-main">

    <section class="card welcome-banner">
        <div>
            <h2>Welcome, <?php echo htmlspecialchars($student['first_name']); ?>!</h2>
            <p>Student No. <strong><?php echo htmlspecialchars($student['student_id']); ?></strong>
               &middot; <?php echo htmlspecialchars($student['program']); ?>
               &middot; <?php echo htmlspecialchars($semLabel); ?> A.Y. <?php echo $year; ?>-<?php echo $year + 1; ?>
               &middot; Section <strong><?php echo htmlspecialchars($section); ?></strong></p>
        </div>
    </section>

    <!-- Announcements -->
    <section class="card">
        <div class="card-head"><h2>Announcements</h2>
            <p>Important notices from Fullbright College.</p>
        </div>
        <ul class="notice-list">
            <?php foreach ($announcements as $a): ?>
                <li class="notice-item">
                    <span class="notice-date"><?php echo htmlspecialchars($a['date']); ?></span>
                    <div>
                        <strong><?php echo htmlspecialchars($a['title']); ?></strong>
                        <p><?php echo htmlspecialchars($a['body']); ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <div class="dash-grid">
        <!-- School events -->
        <section class="card">
            <div class="card-head"><h2>School Events</h2>
                <p>Upcoming events of the school.</p>
            </div>
            <ul class="mini-list">
                <?php foreach ($events as $ev): ?>
                    <li class="mini-item">
                        <span class="mini-date"><?php echo htmlspecialchars($ev['date']); ?></span>
                        <div>
                            <strong><?php echo htmlspecialchars($ev['title']); ?></strong>
                            <p><?php echo htmlspecialchars($ev['venue']); ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- Teacher projects -->
        <section class="card">
            <div class="card-head"><h2>Projects from Teachers</h2>
                <p>Class projects posted by your instructors.</p>
            </div>
            <ul class="mini-list">
                <?php foreach ($projects as $pj): ?>
                    <li class="mini-item">
                        <span class="mini-tag"><?php echo htmlspecialchars($pj['subject']); ?></span>
                        <div>
                            <strong><?php echo htmlspecialchars($pj['title']); ?></strong>
                            <p><?php echo htmlspecialchars($pj['desc']); ?></p>
                            <small>Posted by <?php echo htmlspecialchars($pj['teacher']); ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <!-- Section -->
    <section class="card">
        <div class="card-head">
            <h2>My Section</h2>
            <p><?php echo htmlspecialchars($sectionInfo['label']); ?></p>
        </div>

        <div class="section-fill">
            <div class="section-fill-label">
                <strong>Section <?php echo htmlspecialchars($section); ?></strong>
                <span><?php echo $sectionCount; ?>/<?php echo SECTION_CAPACITY; ?> students</span>
            </div>
            <div class="progress-bar" aria-hidden="true">
                <div class="progress-fill" style="width: <?php echo $sectionPct; ?>%"></div>
            </div>
        </div>

        <h4 class="sched-title">Weekly Class Schedule</h4>
        <div class="table-wrap">
            <table class="sched-table">
                <thead>
                    <tr><th>Time</th>
                        <?php foreach ($days as $d): ?><th><?php echo $d; ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedule as $row): ?>
                        <tr>
                            <td class="sched-time"><?php echo htmlspecialchars($row['time']); ?></td>
                            <?php foreach ($days as $d): ?>
                                <td><?php if ($row[$d] !== ''): ?>
                                    <span class="sched-subject"><?php echo $row[$d]; ?></span>
                                <?php else: ?>
                                    <span class="sched-empty">&mdash;</span>
                                <?php endif; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="field-hint">Sample schedule. The official schedule issued by the registrar may vary.</p>
    </section>

    <!-- Student records (placeholder) -->
    <section class="card">
        <div class="card-head"><h2>Student Records</h2>
            <p>Grades, attendance and status for Semester <?php echo $semLabel; ?> (records will be posted by the registrar).</p>
        </div>

        <h4 class="sched-title">Grades</h4>
        <div class="table-wrap">
            <table class="sched-table records-table">
                <thead>
                    <tr><th>Subject</th><th>Units</th><th>Midterm</th><th>Finals</th><th>Remarks</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" class="records-empty">No grades posted yet. The registrar will fill this in later.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h4 class="sched-title">Attendance (Monthly Count)</h4>
        <div class="table-wrap">
            <table class="sched-table records-table">
                <thead>
                    <tr><th>Month</th><th>Present</th><th>Absent</th><th>Late</th></tr>
                </thead>
                <tbody>
                    <?php foreach (['Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May'] as $m): ?>
                        <tr><td><?php echo $m; ?></td><td class="records-empty">&mdash;</td><td class="records-empty">&mdash;</td><td class="records-empty">&mdash;</td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="record-status">
            <span class="field-hint">Status: </span>
            <span class="status-badge">Regular <small>(to be confirmed by the registrar)</small></span>
        </div>
    </section>
</main>

<footer class="site-footer">
    <p>&copy; <?php echo $year; ?> Fullbright College &mdash; Student Management System. All rights reserved.</p>
</footer>

<script src="js/script.js"></script>
</body>
</html>