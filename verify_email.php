<?php
/**
 * Fullbright College - Student Management System
 * Enrollment email-verification landing page.
 *
 * The student arrives here from the emailed link (?token=...). The token is
 * single-use and expires after 24 hours. On success the verified email is
 * recorded so enrollment can continue.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/verification.php';

session_start();

$rawToken = trim($_GET['token'] ?? '');
$result   = ['ok' => false, 'status' => 'invalid',
             'message' => 'This verification link is not valid.', 'email' => null];

if ($rawToken !== '') {
    try {
        $result = consume_email_verification(db(), $rawToken);

        // Same-browser click: remember the verified email for the enrollment form.
        if ($result['ok'] && !empty($result['email']) && session_id() !== '') {
            $_SESSION['verified_email'] = $result['email'];
            $_SESSION['pending_email']  = $result['email'];
        }
    } catch (Throwable $e) {
        $result = ['ok' => false, 'status' => 'error',
                   'message' => 'We could not verify your email right now. Please try again later.',
                   'email' => null];
    }
}

$year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Email Verification | Fullbright College</title>
<link rel="stylesheet" href="css/style.css?v=20240921">
</head>
<body class="verify-page">

<div class="page-backdrop" aria-hidden="true"></div>

<header class="site-header">
    <div class="brand">
        <img src="assets/logo.png" alt="Fullbright College Logo" class="brand-logo">
        <div class="brand-text">
            <h1>Fullbright College</h1>
            <p>Student Management System &mdash; Email Verification</p>
        </div>
    </div>
    <nav class="site-nav">
        <a href="enroll.php">Enrollment</a>
        <a href="login.php">Student Login</a>
    </nav>
</header>

<main class="page-main page-narrow">
    <section class="card verify-card">
        <?php if ($result['ok'] && $result['status'] === 'verified'): ?>
            <div class="verify-icon verify-icon-ok" aria-hidden="true">&#10003;</div>
            <div class="card-head">
                <h2>Email Verified</h2>
                <p>Your email address has been confirmed.</p>
            </div>
            <div class="alert alert-success">
                <strong><?php echo htmlspecialchars($result['email']); ?></strong> is now verified.
                You can continue and complete your enrollment.
            </div>
            <p class="muted verify-note">You can close this tab and return to the enrollment form, which will continue automatically.</p>
            <div class="form-actions">
                <a href="enroll.php" class="btn btn-primary btn-lg">Continue Enrollment &rarr;</a>
            </div>

        <?php elseif ($result['ok']): ?>
            <div class="verify-icon verify-icon-ok" aria-hidden="true">&#10003;</div>
            <div class="card-head">
                <h2>Already Verified</h2>
                <p>This email address was already verified.</p>
            </div>
            <div class="alert alert-success">
                <strong><?php echo htmlspecialchars($result['email']); ?></strong> is verified.
                You can continue with your enrollment.
            </div>
            <div class="form-actions">
                <a href="enroll.php" class="btn btn-primary btn-lg">Continue Enrollment &rarr;</a>
            </div>

        <?php else: ?>
            <div class="verify-icon verify-icon-fail" aria-hidden="true">&times;</div>
            <div class="card-head">
                <h2>Verification Failed</h2>
                <p><?php echo $result['status'] === 'expired'
                        ? 'This verification link has expired.'
                        : 'This verification link is not valid.'; ?></p>
            </div>
            <div class="alert alert-error"><?php echo htmlspecialchars($result['message']); ?></div>
            <p class="muted verify-note">
                Verification links are valid for 24 hours and can be used only once.
                Return to the enrollment form and request a new link if needed.
            </p>
            <div class="form-actions">
                <a href="enroll.php" class="btn btn-primary btn-lg">&larr; Back to Enrollment</a>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer class="site-footer">
    <p>&copy; <?php echo $year; ?> Fullbright College &mdash; Student Management System. All rights reserved.</p>
</footer>
</body>
</html>
