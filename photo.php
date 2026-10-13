<?php
/**
 * Fullbright College - Student Management System
 * Public ID-photo download endpoint.
 *
 * The QR code printed on a student's Digital ID points here. Opening it
 * (for example by scanning the QR with a phone) downloads the student's ID
 * picture to the device — the optional profile photo when set, otherwise the
 * submitted 2x2 ID photo. Only PNG/JPG images are served.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/idcard.php';

$sid = trim($_GET['sid'] ?? '');
$safeSid = preg_replace('/[^A-Za-z0-9_-]/', '', $sid);
if ($safeSid === '') {
    $safeSid = 'student';
}

$student = null;
if ($sid !== '') {
    $stmt = db()->prepare('SELECT student_id, profile_photo, id_photo FROM students WHERE student_id = ?');
    $stmt->execute([$sid]);
    $student = $stmt->fetch() ?: null;
}

$name = $student ? id_card_photo_name($student) : null;
$path = $name !== null ? UPLOAD_DIR . basename($name) : null;

if ($path === null || !is_file($path)) {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600">'
        . '<rect width="600" height="600" fill="#0f2a5c"/>'
        . '<rect x="20" y="20" width="560" height="560" fill="none" stroke="#e4b23f" stroke-width="6"/>'
        . '<text x="300" y="270" fill="#ffffff" font-family="Segoe UI, Arial, sans-serif" font-size="42" font-weight="700" text-anchor="middle">FULLBRIGHT COLLEGE</text>'
        . '<text x="300" y="330" fill="#e4b23f" font-family="Segoe UI, Arial, sans-serif" font-size="30" text-anchor="middle">No ID photo on file</text>'
        . '<text x="300" y="380" fill="#9fb0d8" font-family="Segoe UI, Arial, sans-serif" font-size="24" text-anchor="middle">Student No. ' . htmlspecialchars($safeSid) . '</text>'
        . '</svg>';
    header('Content-Type: image/svg+xml');
    header('Content-Length: ' . strlen($svg));
    header('Content-Disposition: attachment; filename="' . $safeSid . '-no-photo.svg"');
    header('Cache-Control: public, max-age=300');
    echo $svg;
    exit;
}

$ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];
$mime  = $types[$ext] ?? 'application/octet-stream';
$downloadName = $safeSid . '.' . ($ext === 'jpeg' ? 'jpg' : ($ext !== '' ? $ext : 'jpg'));

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;
