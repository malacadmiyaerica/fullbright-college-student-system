<?php
/**
 * Fullbright College - Student Management System
 * Student Login — username = Student ID, password = the random password
 * generated at enrollment (or the password the student later set).
 */

require_once __DIR__ . '/includes/db.php';

session_start();

// Already logged in?
if (!empty($_SESSION['student_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter your Student ID and password.';
    } else {
        $stmt = db()->prepare('SELECT * FROM students WHERE student_id = ?');
        $stmt->execute([$username]);
        $student = $stmt->fetch();

        $ok = false;
        if ($student) {
            // Accounts created with a generated password have a hash. Legacy
            // rows without one still accept the Student ID as the password.
            $ok = !empty($student['password_hash'])
                ? password_verify($password, $student['password_hash'])
                : hash_equals($student['student_id'], $password);
        }

        if ($student && $ok) {
            $_SESSION['student_id'] = $student['id'];
            unset($_SESSION['just_registered'], $_SESSION['generated_password']);
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Invalid Student ID or password. Please try again.';
    }
}

$year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Login | Fullbright College</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">

<div class="page-backdrop" aria-hidden="true"></div>

<header class="site-header">
    <div class="brand">
        <img src="assets/logo.png" alt="Fullbright College Logo" class="brand-logo">
        <div class="brand-text">
            <h1>Fullbright College</h1>
            <p>Student Management System &mdash; Login</p>
        </div>
    </div>
    <nav class="site-nav">
        <a href="index.php">Home</a>
        <a href="enroll.php">Enroll Now</a>
    </nav>
</header>

<main class="page-main">
    <section class="card login-card">
        <div class="card-head">
            <h2>Student Login</h2>
            <p>Sign in to access your Student Dashboard.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" action="" class="stack-form">
            <div class="field">
                <label for="username">Username (Student ID) <em>*</em></label>
                <input type="text" id="username" name="username" inputmode="numeric" autocomplete="username"
                       placeholder="e.g. 2601010042" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password <em>*</em></label>
                <input type="password" id="password" name="password" autocomplete="current-password"
                       placeholder="Your password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block">Sign In</button>
        </form>

        <p class="muted hint">
            <a href="forgot_password.php">Forgot your password?</a>
        </p>

        <p class="muted hint">
            New student? <a href="enroll.php">Enroll now</a>. Your username is your Student ID and your
            initial password is shown on your application receipt.
        </p>
    </section>
</main>

<footer class="site-footer">
    <p>&copy; <?php echo $year; ?> Fullbright College &mdash; Student Management System. All rights reserved.</p>
</footer>
</body>
</html>