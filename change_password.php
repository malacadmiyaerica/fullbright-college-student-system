<?php
/**
 * Fullbright College - Student Management System
 * Change password. Requires confirmation with the current password
 * (the generated password, or a legacy Student-ID password).
 */

require_once __DIR__ . '/includes/db.php';

session_start();

header('Content-Type: application/json');

if (empty($_SESSION['student_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$id      = (int) $_SESSION['student_id'];
$current = (string) ($_POST['current']  ?? '');
$new     = (string) ($_POST['new']      ?? '');
$confirm = (string) ($_POST['confirm']  ?? '');

$errors = [];

if ($current === '') {
    $errors[] = 'Current password is required.';
}
if (strlen($new) < 8) {
    $errors[] = 'New password must be at least 8 characters long.';
} elseif (!preg_match('/[a-z]/', $new) || !preg_match('/[A-Z]/', $new)
        || !preg_match('/[0-9]/', $new) || !preg_match('/[!@#$%^&*]/', $new)) {
    $errors[] = 'New password must include uppercase and lowercase letters, a number and a special character (!@#$%^&*).';
}
if ($new !== $confirm) {
    $errors[] = 'New password and confirmation do not match.';
}

if (empty($errors)) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT student_id, password_hash FROM students WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student record not found.']);
        exit;
    }

    // Confirm the current credential (the generated password, or a legacy
    // Student-ID password for accounts that never had a hash set).
    if (!empty($row['password_hash'])) {
        $confirmed = password_verify($current, $row['password_hash']);
    } else {
        $confirmed = hash_equals($row['student_id'], $current);
    }

    if (!$confirmed) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $upd  = $pdo->prepare('UPDATE students SET password_hash = ? WHERE id = ?');
    $upd->execute([$hash, $id]);

    echo json_encode(['success' => true, 'message' => 'Password changed successfully. Use your new password on the next login.']);
    exit;
}

http_response_code(422);
echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);