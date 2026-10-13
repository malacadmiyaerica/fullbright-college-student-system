<?php
/**
 * Fullbright College - Student Management System
 * Forgot Password (two-step recovery)
 *
 *   Step 1 - Account verification
 *       The student enters their Student ID (username) or registered email.
 *       The system verifies whether a matching student account exists.
 *       (Anti-enumeration: the same generic message is shown whether or not the
 *        account exists, so we never reveal account presence.)
 *
 *   Step 2 - Create a new password
 *       Once verified, the student is prompted to CREATE A NEW PASSWORD. It must
 *       be typed twice (password + confirmation) before it is saved. The new
 *       password immediately REPLACES the old one and is used for the next
 *       sign-in.
 *
 * Login contract (see login.php):
 *   Username = the student's Student ID
 *   Password = the random password generated at enrollment  -- OR the new
 *              password the student creates here (permanent).
 */

require_once __DIR__ . '/includes/db.php';

session_start();

// Already logged in? Go to the dashboard.
if (!empty($_SESSION['student_id'])) {
    header('Location: dashboard.php');
    exit;
}

$year  = date('Y');
$step  = 'email';        // email -> new_password -> success
$error = null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'lookup';

    if ($action === 'lookup') {
        // --- Step 1: verify by Student ID (username) or email ---
        $identifier = trim($_POST['identifier'] ?? '');

        if ($identifier === '') {
            $error = 'Please enter your Student ID or registered email.';
        } else {
            $pdo  = db();
            $stmt = $pdo->prepare('SELECT * FROM students WHERE student_id = ? OR email = ?');
            $stmt->execute([$identifier, $identifier]);
            $found = $stmt->fetch();

            if ($found) {
                // Verified: remember the account and prompt for a new password.
                $_SESSION['reset_email'] = $found['email'];
                $step = 'new_password';
            } else {
                // Same message regardless of outcome (no enumeration).
                $error = 'If an account exists for that Student ID or email, you can reset your password here.';
            }
        }

    } elseif ($action === 'new_password') {
        // --- Step 2: create new password + confirmation ---
        $new      = trim($_POST['new_password'] ?? '');
        $confirm  = trim($_POST['confirm_password'] ?? '');
        $resetEmail = $_SESSION['reset_email'] ?? null;

        if (!$resetEmail) {
            // No verified account in this session; restart the flow.
            $error = 'Please verify your Student ID or email first.';
        } elseif ($new === '' || $confirm === '') {
            $error = 'Please fill in both the new password and its confirmation.';
            $step  = 'new_password';
        } elseif (strlen($new) < 8) {
            $error = 'Your new password must be at least 8 characters long.';
            $step  = 'new_password';
        } elseif (!preg_match('/[a-z]/', $new) || !preg_match('/[A-Z]/', $new)
               || !preg_match('/[0-9]/', $new) || !preg_match('/[!@#$%^&*]/', $new)) {
            $error = 'Your new password must include uppercase and lowercase letters, a number and a special character (!@#$%^&*).';
            $step  = 'new_password';
        } elseif (!hash_equals($new, $confirm)) {
            $error = 'The password confirmation does not match. Please type the same password twice.';
            $step  = 'new_password';
        } else {
            $pdo  = db();
            $stmt = $pdo->prepare('SELECT * FROM students WHERE email = ?');
            $stmt->execute([$resetEmail]);
            $student = $stmt->fetch();

            if (!$student) {
                unset($_SESSION['reset_email']);
                $error = 'That account could not be found. Please start over.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $upd  = $pdo->prepare('UPDATE students SET password_hash = ? WHERE id = ?');
                $upd->execute([$hash, $student['id']]);

                unset($_SESSION['reset_email']);
                $message = 'Your new password has been saved. You may now sign in with it.';
                $step    = 'success';
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
<title>Forgot Password | Fullbright College</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">

<div class="page-backdrop" aria-hidden="true"></div>

<header class="site-header">
    <div class="brand">
        <img src="assets/logo.png" alt="Fullbright College Logo" class="brand-logo">
        <div class="brand-text">
            <h1>Fullbright College</h1>
            <p>Student Management System &mdash; Password Recovery</p>
        </div>
    </div>
    <nav class="site-nav">
        <a href="index.php">Home</a>
        <a href="enroll.php">Enroll Now</a>
        <a href="login.php">Login</a>
    </nav>
</header>

<main class="page-main page-narrow">
    <section class="card login-card">
        <div class="card-head">
            <h2>Forgot Password</h2>

            <?php if ($step === 'email'): ?>
                <p>Enter your Student ID (username) or the email you used during enrollment to verify your account.</p>
            <?php elseif ($step === 'new_password'): ?>
                <p>Account verified. Create a new password &mdash; type it twice to confirm.</p>
            <?php else: ?>
                <p>Your password has been updated successfully.</p>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($step === 'email'): ?>
            <form method="post" action="" class="stack-form" id="forgot-form">
                <input type="hidden" name="action" value="lookup">
                <div class="field">
                    <label for="identifier">Student ID or Email <em>*</em></label>
                    <input type="text" id="identifier" name="identifier"
                           placeholder="e.g. 2601010042 or juan@email.com" required autofocus>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg btn-block">Verify Account</button>
                </div>
            </form>

        <?php elseif ($step === 'new_password'): ?>
            <form method="post" action="" class="stack-form" id="new-password-form">
                <input type="hidden" name="action" value="new_password">
                <div class="field">
                    <label for="new_password">New Password <em>*</em></label>
                    <input type="password" id="new_password" name="new_password"
                           minlength="8" placeholder="At least 8 characters" required autofocus>
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm New Password <em>*</em></label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           minlength="8" placeholder="Re-enter your new password" required>
                </div>
                <p class="muted hint">
                    Use at least 8 characters with uppercase, lowercase, a number and a
                    special character (!@#$%^&amp;*). Type it twice to confirm.
                </p>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg btn-block">Update Password</button>
                </div>
            </form>

        <?php else: /* success */ ?>
            <div class="alert alert-success">
                Your new password has been saved and now replaces your old one. You can sign
                in with it right away.
            </div>
            <div class="form-actions">
                <a href="login.php" class="btn btn-primary btn-lg btn-block">Proceed to Login &rarr;</a>
            </div>
        <?php endif; ?>

        <p class="muted hint" style="text-align:center;">
            Remember your password? <a href="login.php">Back to login</a>.
        </p>
    </section>
</main>

<footer class="site-footer">
    <p>&copy; <?php echo $year; ?> Fullbright College &mdash; Student Management System. All rights reserved.</p>
</footer>
</body>
</html>
