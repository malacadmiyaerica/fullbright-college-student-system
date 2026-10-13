<?php
/**
 * Fullbright College - Student Management System
 * Public portal: Enroll now or Login.
 */

require_once __DIR__ . '/includes/db.php';

session_start();
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fullbright College | Student Management System</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body class="portal-page">

<div class="page-backdrop" aria-hidden="true"></div>

<header class="site-header">
    <div class="brand">
        <img src="assets/logo.png" alt="Fullbright College Logo" class="brand-logo">
        <div class="brand-text">
            <h1>Fullbright College</h1>
            <p>Student Management System</p>
        </div>
    </div>
    <nav class="site-nav">
        <a href="enroll.php">Enroll Now</a>
        <a href="login.php">Student Login</a>
    </nav>
</header>

<main class="page-main">
    <section class="card hero-card">
        <div class="hero-copy">
            <h2>Welcome to Fullbright College</h2>
            <p class="lead">A single platform for student enrollment, entrance examination, and your official student record.</p>
            <ul class="hero-points">
                <li>Registration &amp; enrollment in one simple process</li>
                <li>Auto-generated entrance examination with QR access</li>
                <li>Instant receipt with your official <strong>Student ID</strong></li>
                <li>Personal Student Dashboard with digital ID</li>
            </ul>

            <div class="portal-actions">
                <a href="enroll.php" class="btn btn-primary btn-lg">New Student &mdash; Enroll Now</a>
                <a href="login.php" class="btn btn-ghost btn-lg">Already Enrolled &mdash; Login</a>
            </div>
        </div>

        <aside class="dept-card">
            <h3>Departments &amp; Courses</h3>
            <div class="dept-item">
                <span class="dept-name">Engineering &amp; Communication Technology (ECT)</span>
                <ul>
                    <?php foreach (ect_programs() as $abbr => $full): ?>
                        <li><strong><?php echo htmlspecialchars($abbr); ?></strong><small><?php echo htmlspecialchars($full); ?></small></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </aside>
    </section>
</main>

<footer class="site-footer">
    <p>&copy; <?php echo $year; ?> Fullbright College &mdash; Student Management System. All rights reserved.</p>
</footer>
</body>
</html>