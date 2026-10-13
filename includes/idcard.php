<?php
/**
 * Fullbright College - Student Management System
 * Shared Digital ID card renderer.
 *
 * Used by profile.php and receipt.php so the card stays
 * consistent everywhere (Student ID, Name, Course, Department, addresses,
 * emergency contact and the download QR code).
 */

require_once __DIR__ . '/db.php';

/**
 * Stored file name of the student's ID picture:
 * the optional profile photo when set, otherwise the submitted 2x2 ID photo.
 */
function id_card_photo_name(array $student): ?string {
    if (!empty($student['profile_photo'])) {
        return $student['profile_photo'];
    }
    if (!empty($student['id_photo'])) {
        return $student['id_photo'];
    }
    return null;
}

/**
 * The picture shown as the student's ID picture (public URL), or null.
 */
function id_card_photo_url(array $student): ?string {
    $name = id_card_photo_name($student);
    return $name !== null ? upload_url($name) : null;
}

/**
 * Human label for the picture source, e.g. "Profile Photo" or "2x2 ID Photo".
 */
function id_card_photo_source(array $student): string {
    return !empty($student['profile_photo']) ? 'Profile Photo' : '2x2 ID Photo';
}

/**
 * Public URL the ID QR code points to. Opening it downloads the student's
 * full Digital ID card as an image (PNG), so scanning saves the whole ID to
 * the device. Uses the configured/auto-detected public domain (never a
 * hard-coded localhost).
 */
function id_card_download_url(array $student): string {
    return public_base_url() . '/digital_id.php?sid=' . urlencode((string) $student['student_id']);
}

/**
 * Renders the digital ID card markup.
 *
 * @param array $student Student row.
 * @param array $opts    ['qr' => bool] whether to include the download QR.
 */
function render_id_card(array $student, array $opts = []): string {
    $withQr   = array_key_exists('qr', $opts) ? (bool) $opts['qr'] : true;
    $photoUrl = id_card_photo_url($student);
    $source   = id_card_photo_source($student);
    $clean    = function ($value): string {
        $value = trim((string) ($value ?? ''));
        return htmlspecialchars($value !== '' ? $value : '—');
    };
    $name     = htmlspecialchars(full_name_with_suffix($student));
    $sid      = htmlspecialchars((string) $student['student_id']);
    $program  = htmlspecialchars((string) $student['program']);
    $dept     = htmlspecialchars((string) $student['department']);
    $perm     = $clean($student['permanent_address'] ?? '');
    $curr     = $clean($student['current_address'] ?? '');
    $sameAddr = student_same_address($student);
    $ecName   = $clean($student['emergency_contact_name'] ?? '');
    $ecNo     = $clean($student['emergency_contact_no'] ?? '');
    $status   = htmlspecialchars((string) $student['marital_status']);
    $year     = date('Y');
    $qrUrl    = id_card_download_url($student);

    ob_start();
    ?>
    <section class="card id-card" aria-label="Student digital ID card">
        <div class="id-head">
            <img src="assets/logo.png" alt="Fullbright College" class="id-logo">
            <div><strong>FULLBRIGHT COLLEGE</strong><small>Official Student ID</small></div>
        </div>
        <div class="id-body">
            <div class="id-photo">
                <?php if ($photoUrl): ?>
                    <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Student photo">
                <?php else: ?>
                    <span class="photo-placeholder">2x2</span>
                <?php endif; ?>
                <span class="id-photo-tag"><?php echo $source; ?></span>
            </div>
            <div class="id-info">
                <span class="id-label">Name</span>
                <strong><?php echo $name; ?></strong>
                <span class="id-label">Student No.</span>
                <strong class="student-no"><?php echo $sid; ?></strong>
                <div class="id-meta">
                    <div>
                        <span class="id-label">Course / Program</span>
                        <strong><?php echo $program; ?></strong>
                    </div>
                    <div>
                        <span class="id-label">Department</span>
                        <strong><?php echo $dept; ?></strong>
                    </div>
                </div>
            </div>
            <?php if ($withQr): ?>
            <div class="id-qr">
                <div class="id-qr-code" data-qr="<?php echo htmlspecialchars($qrUrl); ?>"></div>
                <span>Scan to download.</span>
            </div>
            <?php endif; ?>
        </div>
        <div class="id-details<?php echo $sameAddr ? ' is-single' : ''; ?>">
            <div class="id-detail id-detail-perm">
                <span class="id-label"><?php echo $sameAddr ? 'Address' : 'Permanent Address'; ?></span>
                <strong id="id-permanent-address"><?php echo $perm; ?></strong>
            </div>
            <div class="id-detail id-detail-current">
                <span class="id-label">Current Address</span>
                <strong id="id-current-address"><?php echo $curr; ?></strong>
            </div>
            <div class="id-detail">
                <span class="id-label">Emergency Contact Name</span>
                <strong id="id-emergency-name"><?php echo $ecName; ?></strong>
            </div>
            <div class="id-detail">
                <span class="id-label">Emergency Contact Number</span>
                <strong id="id-emergency-no"><?php echo $ecNo; ?></strong>
            </div>
        </div>
        <div class="id-foot">
            <span>Valid ID — <?php echo $year; ?>-<?php echo $year + 1; ?></span>
            <span><?php echo $status; ?></span>
        </div>
    </section>
    <?php
    return (string) ob_get_clean();
}
