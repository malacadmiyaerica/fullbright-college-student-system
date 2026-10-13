<?php
/**
 * Fullbright College - Student Management System
 * Saves the student's Digital ID card image (PNG) so the QR code on the card
 * can serve a downloadable copy of the full ID.
 *
 * The card is rendered client-side from the same data shown on the Profile
 * page, then sent here as a PNG data URL. Only the logged-in student's own
 * card can be saved, and the image is re-encoded with GD so the file is a
 * guaranteed, clean PNG.
 */

require_once __DIR__ . '/includes/db.php';

session_start();

$student = null;
if (!empty($_SESSION['student_id'])) {
    $stmt = db()->prepare('SELECT student_id FROM students WHERE id = ?');
    $stmt->execute([$_SESSION['student_id']]);
    $student = $stmt->fetch() ?: null;
}

if (!$student) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to save your ID.']);
    exit;
}

$raw  = (string) ($_POST['image'] ?? '');
if ($raw === '') {
    $body = file_get_contents('php://input');
    $json = json_decode((string) $body, true);
    if (is_array($json)) {
        $raw = (string) ($json['image'] ?? '');
    }
}

if ($raw === '' || strlen($raw) > 6 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or oversized image data.']);
    exit;
}

$data = $raw;
if (preg_match('~^data:image/png;base64,(.+)$~i', $raw, $m)) {
    $data = $m[1];
}
$bin = base64_decode($data, true);
if ($bin === false || $bin === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid image data.']);
    exit;
}

$img = @imagecreatefromstring($bin);
if ($img === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'The uploaded image is not a valid PNG.']);
    exit;
}

$w = imagesx($img);
$h = imagesy($img);
// The card is rendered at 1012x638; reject anything that is not card-sized.
if ($w < 600 || $h < 400) {
    imagedestroy($img);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unexpected image dimensions.']);
    exit;
}

$safe = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $student['student_id']);
if (!is_dir(ID_CARD_DIR)) {
    @mkdir(ID_CARD_DIR, 0775, true);
}

$out = ID_CARD_DIR . $safe . '.png';
$ok  = imagepng($img, $out, 6);
imagedestroy($img);

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not write the ID image.']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Your Digital ID image was saved.']);
exit;