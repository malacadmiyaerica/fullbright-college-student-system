<?php
/**
 * Fullbright College - Student Management System
 * Student Profile Menu: digital ID card (photo overlay), all personal
 * info from enrollment, editable contact number / email / profile
 * picture, and change password (with confirmation).
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/idcard.php';

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

$stmt2 = $pdo->prepare('SELECT document_name, file_path FROM requirements WHERE student_id = ? ORDER BY document_name');
$stmt2->execute([$student['id']]);
$requirements = $stmt2->fetchAll();

$year            = date('Y');
$photo           = id_card_photo_url($student);
$photoSource     = id_card_photo_source($student);
$photoChoice     = ($student['profile_photo_opt'] ?? 'skip') === 'upload' ? 'upload' : 'skip';
$profilePhotoUrl = !empty($student['profile_photo']) ? upload_url($student['profile_photo']) : null;
$programs        = ect_programs();

function display_age(string $birthday): string {
    $d = new DateTime($birthday);
    return (string) $d->diff(new DateTime('today'))->y;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile | Fullbright College</title>
<link rel="stylesheet" href="css/style.css?v=20240921">
</head>
<body class="dash-page">

<div class="page-backdrop" aria-hidden="true"></div>

<header class="site-header">
    <div class="brand">
        <img src="assets/logo.png" alt="Fullbright College Logo" class="brand-logo">
        <div class="brand-text">
            <h1>Fullbright College</h1>
            <p>Student Management System &mdash; Profile Menu</p>
        </div>
    </div>
    <nav class="site-nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="profile.php" class="active">Profile</a>
        <a href="logout.php" class="btn btn-sm btn-ghost">Log out</a>
    </nav>
</header>

<main class="page-main">

    <section class="card welcome-banner">
        <div>
            <h2>My Profile</h2>
            <p>Student No. <strong><?php echo htmlspecialchars($student['student_id']); ?></strong>
               &middot; <?php echo htmlspecialchars($student['program']); ?>
               &middot; Section <strong><?php echo htmlspecialchars($student['section'] ?? '—'); ?></strong></p>
        </div>
    </section>

    <div class="profile-grid">

        <!-- Digital ID card -->
        <aside class="profile-left">
            <?php echo render_id_card($student, ['qr' => true]); ?>
            <div class="id-card-actions">
                <button type="button" id="download-id-btn" class="btn btn-primary btn-block">Download as Image (PNG)</button>
                <button type="button" class="btn btn-ghost btn-block" onclick="window.print()">Print</button>
                <p class="field-hint">Scan the QR code on the card to download your official ID as an image. Use the buttons above to download or print it.</p>
            </div>
        </aside>

        <!-- Profile details + editing -->
        <div class="profile-right">

            <section class="card details-card">
                <div class="card-head"><h2>Profile Information</h2>
                    <p>All the personal information you provided during enrollment.</p>
                </div>
                <div class="receipt-block">
                    <h4>Personal Information</h4>
                    <dl class="review-grid">
                        <dt>Full Name</dt><dd><?php echo htmlspecialchars(full_name_with_suffix($student)); ?></dd>
                        <dt>Email</dt><dd><?php echo htmlspecialchars($student['email']); ?></dd>
                        <dt>Birthday</dt><dd><?php echo date('F d, Y', strtotime($student['birthday'])); ?></dd>
                        <dt>Age</dt><dd><?php echo display_age($student['birthday']); ?> years old</dd>
                        <dt>Contact Number</dt><dd><?php echo htmlspecialchars($student['contact_no']); ?></dd>
                        <dt>Emergency Contact Name</dt><dd><?php echo htmlspecialchars($student['emergency_contact_name'] ?? '—'); ?></dd>
                        <dt>Emergency Contact Number</dt><dd><?php echo htmlspecialchars($student['emergency_contact_no']); ?></dd>
                        <dt>Permanent Address</dt><dd><?php echo htmlspecialchars($student['permanent_address'] ?? '—'); ?></dd>
                        <dt>Current Address</dt><dd><?php echo htmlspecialchars($student['current_address'] ?? '—'); ?></dd>
                        <dt>Profile Photo</dt>
                        <dd id="profile-photo-cell">
                            <?php if ($photoChoice === 'upload'): ?>
                                <span id="profile-photo-badge" class="badge badge-approved">Uploaded</span>
                            <?php else: ?>
                                <span id="profile-photo-badge" class="badge badge-type">Skipped — using 2x2 ID photo</span>
                            <?php endif; ?>
                            <span class="field-hint" id="profile-photo-source">ID picture source: <?php echo htmlspecialchars($photoSource); ?></span>
                        </dd>
                        <dt>Status</dt><dd><?php echo htmlspecialchars($student['marital_status']); ?></dd>
                    </dl>
                </div>
                <div class="receipt-block">
                    <h4>Academic Information</h4>
                    <dl class="review-grid">
                        <dt>Course / Program</dt><dd><?php echo htmlspecialchars($student['program']); ?></dd>
                        <dt>Department</dt><dd><?php echo htmlspecialchars($student['department']); ?></dd>
                        <dt>Section</dt><dd><?php echo htmlspecialchars($student['section'] ?? '—'); ?></dd>
                        <dt>Entrance Exam Score</dt><dd><span class="score-pill"><?php echo (int) $student['exam_score']; ?>/<?php echo (int) EXAM_QUESTION_COUNT; ?></span></dd>
                        <dt>Enrolled</dt><dd><?php echo date('F d, Y g:i A', strtotime($student['created_at'])); ?></dd>
                    </dl>
                </div>
                <div class="receipt-block">
                    <h4>Submitted Documents</h4>
                    <ul class="req-list">
                        <?php foreach ($requirements as $r): ?>
                            <li>
                                <span><?php echo htmlspecialchars($r['document_name']); ?></span>
                                <span class="req-actions">
                                    <a class="req-file-link" href="<?php echo htmlspecialchars($r['file_path']); ?>" target="_blank" rel="noopener">View</a>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>

            <!-- Edit profile -->
            <section class="card details-card">
                <div class="card-head"><h2>Edit Profile</h2>
                    <p>Update your current address, contact number, email and profile photo. Changes are applied after you confirm.</p>
                </div>

                <div id="profile-alert" class="alert hidden"></div>

                <form id="profile-form" class="stack-form" novalidate>
                    <div class="grid grid-2">
                        <div class="field">
                            <label for="p-current_address">Current Address</label>
                            <input type="text" id="p-current_address" name="current_address" value="<?php echo htmlspecialchars($student['current_address'] ?? ''); ?>" placeholder="House no., Street, Barangay, City">
                            <label class="address-same-toggle" for="p_same_address">
                                <input type="checkbox" id="p_same_address" name="same_address" value="1" <?php echo student_same_address($student) ? 'checked' : ''; ?>>
                                <span>Same as Permanent Address.</span>
                            </label>
                        </div>
                        <div class="field">
                            <label for="p-contact">Contact Number</label>
                            <input type="tel" id="p-contact" name="contact_no" value="<?php echo htmlspecialchars($student['contact_no']); ?>" required>
                        </div>
                    </div>

                    <div class="field">
                        <label for="p-email">Email</label>
                        <input type="email" id="p-email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                    </div>

                    <div class="photo-choice">
                        <h4>Profile Photo</h4>
                        <p class="field-hint">Optional. If you skip, your 2x2 ID photo is used as your ID picture.</p>
                        <label class="choice-item">
                            <input type="radio" name="profile_photo_opt" value="skip" <?php echo $photoChoice === 'skip' ? 'checked' : ''; ?>>
                            <span><strong>Skip Profile Photo</strong> &mdash; use my 2x2 ID photo</span>
                        </label>
                        <label class="choice-item">
                            <input type="radio" name="profile_photo_opt" value="upload" <?php echo $photoChoice === 'upload' ? 'checked' : ''; ?>>
                            <span><strong>Upload Profile Photo</strong> &mdash; optional</span>
                        </label>
                        <div class="choice-upload <?php echo $photoChoice === 'upload' ? '' : 'hidden'; ?>" id="profile-upload-field">
                            <div class="field">
                                <label for="p-photo">Choose Profile Photo (JPG/PNG)</label>
                                <input type="file" id="p-photo" name="profile_photo" class="file-input" accept=".jpg,.jpeg,.png">
                                <?php if ($profilePhotoUrl): ?>
                                    <span class="field-hint">Current profile photo is set.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <p class="readonly-note">
                        Read-only once submitted:
                        <strong>Permanent Address</strong> &amp;
                        <strong>Emergency Contact Name / Number</strong>.
                        Please contact the registrar to change these.
                    </p>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>

                <hr class="divider">

                <div class="card-head"><h2>Change Password</h2>
                    <p>Changing your password requires confirming your current password.</p>
                </div>

                <div id="pwd-alert" class="alert hidden"></div>

                <form id="password-form" class="stack-form" novalidate>
                    <div class="field">
                        <label for="pw-current">Current Password <em>*</em></label>
                        <input type="password" id="pw-current" name="current" autocomplete="current-password" required>
                    </div>
                    <div class="field">
                        <label for="pw-new">New Password <em>*</em></label>
                        <input type="password" id="pw-new" name="new" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="field">
                        <label for="pw-confirm">Confirm New Password <em>*</em></label>
                        <input type="password" id="pw-confirm" name="confirm" minlength="8" autocomplete="new-password" required>
                    </div>
                    <p class="readonly-note">New password must be at least 8 characters and include uppercase, lowercase, a number and a special character.</p>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Change Password</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</main>

<footer class="site-footer">
    <p>&copy; <?php echo $year; ?> Fullbright College &mdash; Student Management System. All rights reserved.</p>
</footer>

<script>
window.CURRENT = <?php echo json_encode([
    'email'          => $student['email'],
    'photo'          => $photo,
    'photoSource'    => $photoSource,
    'photoChoice'    => $photoChoice,
    'currentAddress' => $student['current_address'] ?? '',
    'permanentAddress' => $student['permanent_address'] ?? '',
    'sameAddress'    => student_same_address($student),
], JSON_UNESCAPED_SLASHES); ?>;

window.ID_CARD = <?php echo json_encode([
    'name'             => full_name_with_suffix($student),
    'studentId'        => $student['student_id'],
    'program'          => $student['program'],
    'department'       => $student['department'],
    'permanentAddress' => ($student['permanent_address'] ?? '') !== '' ? $student['permanent_address'] : '—',
    'currentAddress'   => ($student['current_address'] ?? '') !== '' ? $student['current_address'] : '—',
    'sameAddress'      => student_same_address($student),
    'emergencyName'    => ($student['emergency_contact_name'] ?? '') !== '' ? $student['emergency_contact_name'] : '—',
    'emergencyNo'      => ($student['emergency_contact_no'] ?? '') !== '' ? $student['emergency_contact_no'] : '—',
    'status'           => $student['marital_status'],
    'valid'            => $year . '-' . ($year + 1),
    'photoSource'      => $photoSource,
    'photoUrl'         => $photo,
    'qrUrl'            => id_card_download_url($student),
    'logoSrc'          => 'assets/logo.png',
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="js/qrcode.js"></script>
<script src="js/script.js"></script>
</body>
</html>