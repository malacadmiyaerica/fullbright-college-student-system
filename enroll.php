<?php
/**
 * Fullbright College - Student Management System
 * Student Enrollment Form (Registration = Enrollment).
 * Includes personal information, document uploads, 2x2 photo,
 * the mandatory entrance exam, and application submission.
 */

require_once __DIR__ . '/includes/db.php';

session_start();
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Enrollment | Fullbright College</title>
<link rel="stylesheet" href="css/style.css?v=20240921">
</head>
<body class="enroll-page">

<div class="page-backdrop" aria-hidden="true"></div>

<header class="site-header">
    <div class="brand">
        <img src="assets/logo.png" alt="Fullbright College Logo" class="brand-logo">
        <div class="brand-text">
            <h1>Fullbright College</h1>
            <p>Student Management System &mdash; Enrollment</p>
        </div>
    </div>
    <nav class="site-nav">
        <a href="index.php">Home</a>
        <a href="login.php">Student Login</a>
    </nav>
</header>

<main class="page-main">
    <section class="card enroll-card">
        <div class="card-head">
            <h2>Student Enrollment Form</h2>
            <p>Registration and enrollment are a single process. Complete your personal information, upload the required documents and 2x2 ID photo, then take the entrance examination before submitting your application.</p>
        </div>

        <form id="enrollment-form" action="submit_enrollment.php" method="post" enctype="multipart/form-data" novalidate>

            <div id="form-alert" class="alert hidden" role="alert"></div>

            <!-- 1. Enrollment Instructions -->
            <fieldset class="form-section wizard-step">
                <legend><span>1</span> Enrollment Instructions</legend>
                <p class="req-note">Registration and enrollment are a single process. Complete the sections in order &mdash; your application can only be submitted after your email is verified and the entrance exam score is recorded.</p>
                <ol class="steps">
                    <li><strong>Email Verification</strong> &mdash; enter your email address and click the verification link we send you (valid for 24 hours).</li>
                    <li><strong>Personal Information</strong> &mdash; fill in your details, both addresses, contact numbers, and course/program.</li>
                    <li><strong>Required Documents</strong> &mdash; upload your PSA birth certificate, good moral character certificate, Form 138, and 2x2 ID photos.</li>
                    <li><strong>Entrance Examination</strong> &mdash; click <em>Generate Exam</em> to create your code and QR, then answer the auto-generated questions (Math &amp; English). Your score is recorded automatically.</li>
                </ol>
                <div class="wizard-nav">
                    <button type="button" class="btn btn-primary" data-step-next>Next &rarr;</button>
                </div>
            </fieldset>

            <!-- 2. Email Verification -->
            <fieldset class="form-section wizard-step" hidden data-verify-step>
                <legend><span>2</span> Email Verification</legend>
                <p class="req-note">We need to confirm your email address before you can continue. We&rsquo;ll email you a verification link &mdash; it stays valid for <strong>24 hours</strong> and can be used only once.</p>

                <div class="verify-panel">
                    <div class="verify-email-row">
                        <div class="field">
                            <label for="email">Email Address <em>*</em></label>
                            <input type="email" id="email" name="email" placeholder="juan.delacruz@email.com"
                                   autocomplete="email" data-verify-email required>
                        </div>
                        <button type="button" class="btn btn-primary" id="send-verify-btn">Send Verification Link</button>
                    </div>

                    <div id="verify-status" class="verify-status hidden" role="status" aria-live="polite">
                        <span class="dot" aria-hidden="true"></span>
                        <span class="verify-status-text"></span>
                    </div>

                    <div id="verify-demo" class="alert alert-success hidden">
                        <span>Local demo &mdash; no mail server is configured. Open your verification link:</span>
                        <a id="verify-demo-link" href="#" target="_blank" rel="noopener">Verify now &rarr;</a>
                    </div>
                </div>

                <div class="wizard-nav">
                    <button type="button" class="btn btn-ghost" data-step-back>&larr; Back</button>
                    <button type="button" class="btn btn-primary" data-step-next data-verify-gate>Next &rarr;</button>
                </div>
            </fieldset>

            <!-- 3. Personal Information -->
            <fieldset class="form-section wizard-step" hidden>
                <legend><span>3</span> Personal Information</legend>
                <div class="grid grid-3">
                    <div class="field">
                        <label for="first_name">First Name <em>*</em></label>
                        <input type="text" id="first_name" name="first_name" placeholder="Juan" required>
                    </div>
                    <div class="field">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" placeholder="Santos">
                    </div>
                    <div class="field">
                        <label for="last_name">Last Name <em>*</em></label>
                        <input type="text" id="last_name" name="last_name" placeholder="Dela Cruz" required>
                    </div>
                </div>

                <div class="grid grid-4">
                    <div class="field">
                        <label for="suffix">Suffix</label>
                        <select id="suffix" name="suffix">
                            <option value="">None</option>
                            <option value="Jr.">Jr.</option>
                            <option value="Sr.">Sr.</option>
                            <option value="II">II</option>
                            <option value="III">III</option>
                            <option value="IV">IV</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Email (verified)</label>
                        <div class="verified-email-chip" data-verified-email-text>&nbsp;</div>
                    </div>
                    <div class="field">
                        <label for="birthday">Birthday <em>*</em></label>
                        <input type="date" id="birthday" name="birthday" max="<?php echo $year . '-12-31'; ?>" required>
                    </div>
                    <div class="field">
                        <label for="marital_status">Status <em>*</em></label>
                        <select id="marital_status" name="marital_status" required>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-2">
                    <div class="field">
                        <label for="permanent_address">Permanent Address <em>*</em></label>
                        <input type="text" id="permanent_address" name="permanent_address" placeholder="No., Street, Barangay, City, Province, Zip Code" required>
                        <label class="address-same-toggle" for="same_address">
                            <input type="checkbox" id="same_address" name="same_address" value="1">
                            <span>Same as Permanent Address.</span>
                        </label>
                    </div>
                    <div class="field">
                        <label for="current_address">Current Address <em>*</em></label>
                        <input type="text" id="current_address" name="current_address" placeholder="No., Street, Barangay, City, Province, Zip Code" required>
                    </div>
                </div>

                <div class="grid grid-3">
                    <div class="field">
                        <label for="contact_no">Contact Number <em>*</em></label>
                        <input type="tel" id="contact_no" name="contact_no" placeholder="0917 123 4567" required>
                    </div>
                    <div class="field">
                        <label for="emergency_contact_name">Emergency Contact Name <em>*</em></label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" placeholder="Maria Dela Cruz" required>
                    </div>
                    <div class="field">
                        <label for="emergency_contact_no">Emergency Contact Number <em>*</em></label>
                        <input type="tel" id="emergency_contact_no" name="emergency_contact_no" placeholder="0917 765 4321" required>
                    </div>
                </div>

                <div class="grid grid-2">
                    <div class="field">
                        <label for="program">Course / Program <em>*</em></label>
                        <select id="program" name="program" required>
                            <option value="" disabled selected>-- Select Program --</option>
                            <?php foreach (ect_programs() as $abbr => $full): ?>
                                <option value="<?php echo htmlspecialchars($abbr); ?>"><?php echo htmlspecialchars($abbr); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="department">Department (auto)</label>
                        <input type="text" id="department" name="department" value="<?php echo htmlspecialchars(DEPARTMENT); ?>" readonly>
                    </div>
                </div>

                <div class="wizard-nav">
                    <button type="button" class="btn btn-ghost" data-step-back>&larr; Back</button>
                    <button type="button" class="btn btn-primary" data-step-next>Next &rarr;</button>
                </div>
            </fieldset>

            <!-- 4. Required Documents -->
            <fieldset class="form-section wizard-step" hidden>
                <legend><span>4</span> Required Documents</legend>
                <p class="req-note">Upload the following documents (<strong>document files</strong> - PDF or Word). Document size limit is 8&nbsp;MB each.</p>
                <div class="grid grid-2">
                    <div class="field">
                        <label for="req_psa">PSA Birth Certificate <em>*</em></label>
                        <input type="file" id="req_psa" name="req_psa" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                    </div>
                    <div class="field">
                        <label for="req_good_moral">Certificate of Good Moral Character <em>*</em></label>
                        <input type="file" id="req_good_moral" name="req_good_moral" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                    </div>
                    <div class="field">
                        <label for="req_form138">Form 138 (Original H.S. Report Card) <em>*</em></label>
                        <input type="file" id="req_form138" name="req_form138" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                    </div>
                    <div class="field">
                        <label for="req_id_2x2">3 pcs 2x2 ID Picture (Colored, White Background) <em>*</em></label>
                        <input type="file" id="req_id_2x2" name="req_id_2x2" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                    </div>
                </div>

                <div class="photo-upload">
                    <div class="photo-preview" id="photo-preview" aria-hidden="true">
                        <span class="photo-placeholder">2x2</span>
                    </div>
                    <div class="photo-fields">
                        <h4>2x2 ID Photo <em>*</em></h4>
                        <p class="field-hint">Upload your 2x2 picture <strong>separately here</strong> (colored, white background, image file). This photo is required and will be used on your student ID and profile.</p>
                        <input type="file" id="id_photo" name="id_photo" class="file-input" accept=".jpg,.jpeg,.png" required>
                    </div>
                </div>

                <div class="photo-choice">
                    <h4>Profile Photo</h4>
                    <p class="field-hint">Optional &mdash; choose whether to upload a separate profile photo. This choice can be changed later in your Profile menu. If you skip, your submitted 2x2 ID photo is used as your ID picture.</p>
                    <label class="choice-item">
                        <input type="radio" name="profile_photo_opt" value="skip" checked>
                        <span><strong>Skip Profile Photo</strong> &mdash; use my 2x2 ID photo</span>
                    </label>
                    <label class="choice-item">
                        <input type="radio" name="profile_photo_opt" value="upload">
                        <span><strong>Upload Profile Photo</strong> &mdash; optional</span>
                    </label>
                    <div class="choice-upload hidden" id="profile-upload-field">
                        <div class="field">
                            <label for="profile_photo">Profile Photo (JPG/PNG)</label>
                            <input type="file" id="profile_photo" name="profile_photo" class="file-input" accept=".jpg,.jpeg,.png">
                        </div>
                    </div>
                </div>

                <div class="wizard-nav">
                    <button type="button" class="btn btn-ghost" data-step-back>&larr; Back</button>
                    <button type="button" class="btn btn-primary" data-step-next>Next &rarr;</button>
                </div>
            </fieldset>

            <!-- 5. Entrance Examination -->
            <fieldset class="form-section wizard-step" hidden>
                <legend><span>5</span> Entrance Examination</legend>
                <p class="req-note">An entrance exam with <strong><?php echo (int) EXAM_QUESTION_COUNT; ?> auto-generated questions</strong> (Math 7&ndash;8, English 7&ndash;8) is required. Click <strong>Generate Exam</strong> to create a unique code and QR link. Your application can only be submitted after the exam score is recorded.</p>

                <div id="exam-launch">
                    <button type="button" id="generate-exam-btn" class="btn btn-primary btn-lg">
                        <span class="gen-spin" aria-hidden="true"></span> Generate Exam
                    </button>
                </div>

                <div id="exam-summary" class="exam-summary hidden">
                    <div class="exam-code-box">
                        <span class="score-label">Exam Code</span>
                        <strong class="exam-code-text" id="exam-code-display">------</strong>
                        <p class="field-hint">Use this code or the QR code to access your exam.</p>
                    </div>
                    <div class="exam-qr" id="exam-qr" title="Scan to open your exam"></div>
                    <div class="exam-actions">
                        <button type="button" id="open-exam-btn" class="btn btn-outline btn-lg">Open the Exam</button>
                        <a id="exam-url-link" class="btn btn-ghost" href="#" target="_blank" rel="noopener">Open in new tab / scan QR</a>
                    </div>
                </div>

                <div id="exam-score-box" class="exam-score-box hidden">
                    <div class="score-box score-done">
                        <span class="score-label">Exam Completed &mdash; Score</span>
                        <span class="score-value" id="exam-score-display">--/15</span>
                    </div>
                </div>

                <div class="wizard-nav">
                    <button type="button" class="btn btn-ghost" data-step-back>&larr; Back</button>
                    <div class="form-actions">
                        <button type="submit" id="submit-application-btn" class="btn btn-primary btn-lg" disabled>Submit Application</button>
                        <button type="reset" class="btn btn-ghost">Clear Form</button>
                    </div>
                </div>
            </fieldset>
        </form>
    </section>
</main>

<!-- Exam modal -->
<div class="modal" id="exam-modal" hidden>
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-dialog modal-exam">
        <div class="modal-head">
            <h3>Fullbright College Entrance Examination</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <iframe id="exam-frame" class="exam-frame" src="about:blank" title="Entrance Examination"></iframe>
    </div>
</div>

<footer class="site-footer">
    <p>&copy; <?php echo $year; ?> Fullbright College &mdash; Student Management System. All rights reserved.</p>
</footer>

<script src="js/qrcode.js"></script>
<script src="js/script.js"></script>
</body>
</html>