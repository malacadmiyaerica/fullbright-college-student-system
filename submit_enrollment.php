<?php
/**
 * Fullbright College - Student Management System
 * Process the Student Enrollment submission:
 *   - validates personal info
 *   - uploads required documents + 2x2 photo
 *   - verifies the entrance exam score
 *   - generates a unique Student ID (YYSSXXXXXX)
 *   - returns a receipt reference
 */

require_once __DIR__ . '/includes/db.php';

session_start();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$first_name         = trim($_POST['first_name']          ?? '');
$middle_name        = trim($_POST['middle_name']         ?? '');
$last_name          = trim($_POST['last_name']           ?? '');
$suffix             = trim($_POST['suffix']              ?? '');
$email              = trim($_POST['email']               ?? '');
$birthday           = trim($_POST['birthday']            ?? '');
$contact_no         = trim($_POST['contact_no']          ?? '');
$emergency_contact  = trim($_POST['emergency_contact_no'] ?? '');
$emergency_name     = trim($_POST['emergency_contact_name'] ?? '');
$marital_status     = trim($_POST['marital_status']       ?? '');
$program            = trim($_POST['program']              ?? '');
$department         = trim($_POST['department']           ?? '');
$permanent_address  = trim($_POST['permanent_address']    ?? '');
$current_address    = trim($_POST['current_address']      ?? '');
$same_address       = !empty($_POST['same_address']) ? 1 : 0;
// "Same as Permanent Address": never trust the posted value when mirrored.
if ($same_address === 1) {
    $current_address = $permanent_address;
}
$profile_photo_opt  = trim($_POST['profile_photo_opt']    ?? 'skip');
if (!in_array($profile_photo_opt, ['upload', 'skip'], true)) {
    $profile_photo_opt = 'skip';
}

// ---- Basic validation -------------------------------------------------
if ($first_name === '')  $errors[] = 'First name is required.';
if ($last_name  === '')  $errors[] = 'Last name is required.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday) !== 1) {
    $errors[] = 'Birthday is required.';
} elseif ($birthday > date('Y-m-d')) {
    $errors[] = 'Birthday cannot be a future date.';
}
if ($contact_no === '')        $errors[] = 'Contact number is required.';
if ($emergency_contact === '') $errors[] = 'Emergency contact number is required.';
if ($emergency_name === '')    $errors[] = 'Emergency contact name is required.';
if (!in_array($marital_status, ['Single', 'Married'], true)) {
    $errors[] = 'Please choose a status (Single or Married).';
}
if ($permanent_address === '') $errors[] = 'Permanent address is required.';
if ($current_address   === '') $errors[] = 'Current address is required.';
$programs = ect_programs();
if (!isset($programs[$program])) {
    $errors[] = 'Please select a valid course / program.';
}

// ---- Email uniqueness ------------------------------------------------
if (empty($errors)) {
    $stmt = db()->prepare('SELECT id FROM students WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $errors[] = 'An application with this email already exists. Please use a different email.';
    }
}

// ---- Email must be verified for this session -------------------------
if (empty($errors)) {
    require_once __DIR__ . '/includes/verification.php';
    $verifiedEmail = current_verified_email(db());
    if ($verifiedEmail === null) {
        $errors[] = 'Please verify your email to proceed.';
    } elseif (strcasecmp($verifiedEmail, $email) !== 0) {
        $errors[] = 'The verified email does not match the email entered. Please verify this address before submitting.';
    }
}

// ---- Entrance exam must exist and be scored --------------------------
$examCode = $_SESSION['exam_code'] ?? null;
$examScore = null;
if (empty($errors)) {
    if (!$examCode) {
        $errors[] = 'Please generate and take the entrance exam before submitting.';
    } else {
        require_once __DIR__ . '/includes/exam.php';
        $exam = find_exam($examCode);
        if (!$exam || $exam['score'] === null) {
            $errors[] = 'Please complete the entrance exam before submitting your application.';
        } else {
            $examScore = (int) $exam['score'];
        }
    }
}

// ---- Uploads ----------------------------------------------------------
$allowedDoc = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$allowedImg = ['jpg', 'jpeg', 'png'];

$uploaded = []; // label => path (relative, e.g. uploads/abc.pdf)

function upload_file(string $field, string $label, array $allowed, string $dirPrefix, bool $required = true): ?string {
    global $errors;
    if (empty($_FILES[$field]['name'])) {
        if ($required) {
            $errors[] = $label . ' is required.';
        }
        return null;
    }
    $f   = $_FILES[$field];
    $err = $f['error'];
    if ($err === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            $errors[] = $label . ' is required.';
        }
        return null;
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || $f['size'] > MAX_FILE_SIZE) {
        $errors[] = $label . ' exceeds the 2MB size limit.';
        return null;
    }
    if ($err !== UPLOAD_ERR_OK) {
        $errors[] = 'There was a problem uploading ' . $label . '.';
        return null;
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        $errors[] = $label . ' must be a ' . ($dirPrefix === 'photo' ? 'JPG/PNG image' : 'PDF/Word document or JPG/PNG image') . '.';
        return null;
    }
    $newname = $dirPrefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest    = UPLOAD_DIR . $newname;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        $errors[] = 'Could not save uploaded file for ' . $label . '.';
        return null;
    }
    return $newname;
}

if (empty($errors)) {
    $map = requirements_map();
    foreach ($map as $field => $label) {
        $name = upload_file($field, $label, $allowedDoc, 'doc');
        if ($name !== null) {
            $uploaded[$label] = $name;
        }
    }

    // Required 2x2 ID photo (used on the Digital ID card).
    $idPhoto = upload_file('id_photo', '2x2 ID Photo', $allowedImg, 'photo');

    // Optional profile photo, only when the student chose to upload one.
    $profilePhoto = null;
    if (empty($errors) && $profile_photo_opt === 'upload') {
        $profilePhoto = upload_file('profile_photo', 'Profile Photo', $allowedImg, 'photo', false);
        if ($profilePhoto === null) {
            // No file provided after choosing upload -> fall back to skipping.
            $profile_photo_opt = 'skip';
        }
    }

    $photo = $idPhoto;

    // Clean up partial uploads when validation or upload failed.
    if (!empty($errors)) {
        $cleanup = array_merge($uploaded, $photo ? [$photo] : [], $profilePhoto ? [$profilePhoto] : []);
        foreach ($cleanup as $fname) {
            $full = UPLOAD_DIR . $fname;
            if (is_file($full)) { @unlink($full); }
        }
    }
}

// ---- Save -------------------------------------------------------------
$studentId = null;

if (empty($errors)) {
    try {
        $pdo = db();
        $pdo->beginTransaction();

        $studentId = generate_student_id($pdo);
        $section   = assign_section($pdo, $program);

        // Random, secure initial password (shown once on the receipt).
        $plainPassword = generate_password(12);
        $passwordHash  = password_hash($plainPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
          'INSERT INTO students
              (student_id, first_name, middle_name, last_name, suffix, email, email_verified, email_verified_at, birthday,
               contact_no, emergency_contact_no, emergency_contact_name, marital_status, department, program,
               permanent_address, current_address, same_address,
               id_photo, profile_photo, profile_photo_opt, exam_code, exam_score, section, password_hash)
           VALUES
              (:student_id, :first_name, :middle_name, :last_name, :suffix, :email, 1, NOW(), :birthday,
               :contact_no, :emergency_contact_no, :emergency_contact_name, :marital_status, :department, :program,
               :permanent_address, :current_address, :same_address,
               :id_photo, :profile_photo, :profile_photo_opt, :exam_code, :exam_score, :section, :password_hash)'
        );
        $stmt->execute([
            ':student_id'           => $studentId,
            ':first_name'           => $first_name,
            ':middle_name'          => $middle_name !== '' ? $middle_name : null,
            ':last_name'            => $last_name,
            ':suffix'               => $suffix !== '' ? $suffix : null,
            ':email'                => $email,
            ':birthday'             => $birthday,
            ':contact_no'           => $contact_no,
            ':emergency_contact_no' => $emergency_contact,
            ':emergency_contact_name' => $emergency_name,
            ':marital_status'       => $marital_status,
            ':department'           => $department !== '' ? $department : DEPARTMENT,
            ':program'              => $program,
            ':permanent_address'    => $permanent_address,
            ':current_address'      => $current_address,
            ':same_address'         => $same_address,
            ':id_photo'             => $photo,
            ':profile_photo'        => $profilePhoto,
            ':profile_photo_opt'    => $profile_photo_opt,
            ':exam_code'            => $examCode,
            ':exam_score'           => $examScore,
            ':section'              => $section,
            ':password_hash'        => $passwordHash,
        ]);
        $studentRowId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO requirements (student_id, document_name, file_path) VALUES (?, ?, ?)'
        );
        foreach ($uploaded as $label => $fname) {
            $stmt->execute([$studentRowId, $label, upload_url($fname)]);
        }

        $pdo->commit();

        // Allow the receipt page only for this session (until login).
        $_SESSION['just_registered'] = $studentId;
        // One-time: shown on the receipt, then discarded by receipt.php.
        $_SESSION['generated_password'] = $plainPassword;

        // Clear the exam draft so a new applicant starts fresh.
        unset($_SESSION['exam_code']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $errors[] = 'Database error while saving: ' . $e->getMessage();
        $cleanup = array_merge($uploaded, $photo ? [$photo] : [], $profilePhoto ? [$profilePhoto] : []);
        foreach ($cleanup as $fname) {
            $full = UPLOAD_DIR . $fname;
            if (is_file($full)) { @unlink($full); }
        }
    }
}

http_response_code(empty($errors) ? 200 : 422);
header('Content-Type: application/json');
echo json_encode([
    'success'    => $errors === [],
    'message'    => $errors === [] ? 'Application submitted successfully!' : implode(' ', $errors),
    'errors'     => $errors,
    'student_id' => $studentId,
    'receipt_url'=> 'receipt.php',
]);
