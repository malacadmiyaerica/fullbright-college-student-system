<?php
/**
 * Fullbright College - Student Management System
 * Update the fields a student is allowed to change:
 *   - current address
 *   - contact number
 *   - email
 *   - profile photo (upload / skip)
 *
 * Permanent address and the emergency contact name/number are read-only
 * once submitted, so they are intentionally NOT accepted here.
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

$id              = (int) $_SESSION['student_id'];
$email           = trim($_POST['email'] ?? '');
$contact         = trim($_POST['contact_no'] ?? '');
$current_address = trim($_POST['current_address'] ?? '');
$same_address    = !empty($_POST['same_address']) ? 1 : 0;
$photoOpt        = trim($_POST['profile_photo_opt'] ?? 'skip');
if (!in_array($photoOpt, ['upload', 'skip'], true)) {
    $photoOpt = 'skip';
}

$errors = [];
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if ($contact === '') {
    $errors[] = 'Contact number is required.';
}

$pdo = db();

// Email uniqueness (excluding self).
if (empty($errors)) {
    $stmt = $pdo->prepare('SELECT id FROM students WHERE email = ? AND id <> ?');
    $stmt->execute([$email, $id]);
    if ($stmt->rowCount() > 0) {
        $errors[] = 'That email is already used by another student.';
    }
}

// Load current row (for the 2x2 ID photo fallback and the permanent address).
$stmt = $pdo->prepare('SELECT id_photo, profile_photo, permanent_address FROM students WHERE id = ?');
$stmt->execute([$id]);
$current = $stmt->fetch() ?: ['id_photo' => null, 'profile_photo' => null, 'permanent_address' => null];

// "Same as Permanent Address": mirror the permanent address (never trust the
// posted value while mirrored).
if ($same_address === 1) {
    $current_address = (string) ($current['permanent_address'] ?? '');
}

// Optional profile photo upload (only when "upload" was chosen).
$photoName = null;
if (empty($errors) && $photoOpt === 'upload' && !empty($_FILES['profile_photo']['name'])) {
    $f = $_FILES['profile_photo'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'There was a problem uploading the profile photo.';
    } else {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $errors[] = 'Profile photo must be a JPG/PNG image.';
        } elseif ($f['size'] > MAX_FILE_SIZE) {
            $errors[] = 'Profile photo exceeds the 2MB size limit.';
        } else {
            $name = 'photo_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $name)) {
                $errors[] = 'Could not save the profile photo.';
            } else {
                $photoName = $name;
            }
        }
    }
}

// Choosing "upload" without a file: keep an existing profile photo if there
// is one; otherwise it is treated as "skip".
if ($photoOpt === 'upload' && $photoName === null && empty($errors) && empty($current['profile_photo'])) {
    $photoOpt = 'skip';
}

if (!empty($errors)) {
    if ($photoName) { $full = UPLOAD_DIR . $photoName; if (is_file($full)) @unlink($full); }
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Build the update: profile_photo is cleared on "skip", replaced on upload,
// and left untouched otherwise.
$sets   = ['email = ?', 'contact_no = ?', 'current_address = ?', 'same_address = ?', 'profile_photo_opt = ?'];
$params = [$email, $contact, $current_address, $same_address, $photoOpt];

if ($photoOpt === 'skip') {
    $sets[] = 'profile_photo = NULL';
} elseif ($photoName !== null) {
    $sets[] = 'profile_photo = ?';
    $params[] = $photoName;
}

$params[] = $id;
$stmt = $pdo->prepare('UPDATE students SET ' . implode(', ', $sets) . ' WHERE id = ?');
$stmt->execute($params);

// The picture actually used on the ID after this update.
if ($photoName !== null) {
    $effectivePhoto = upload_url($photoName);
    $photoSource    = 'Profile Photo';
} elseif ($photoOpt === 'upload' && !empty($current['profile_photo'])) {
    $effectivePhoto = upload_url($current['profile_photo']);
    $photoSource    = 'Profile Photo';
} elseif (!empty($current['id_photo'])) {
    $effectivePhoto = upload_url($current['id_photo']);
    $photoSource    = '2x2 ID Photo';
} else {
    $effectivePhoto = null;
    $photoSource    = '2x2 ID Photo';
}

echo json_encode([
    'success'        => true,
    'message'        => 'Profile updated successfully.',
    'photo'          => $effectivePhoto,
    'photo_source'   => $photoSource,
    'photo_choice'   => $photoOpt,
    'current_address'=> $current_address,
    'same_address'   => (bool) $same_address,
]);
