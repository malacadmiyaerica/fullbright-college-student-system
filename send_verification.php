<?php
/**
 * Fullbright College - Student Management System
 * Send an enrollment email-verification link.
 *
 * POST (JSON): email
 * Returns JSON: { success, message, email?, link?, expires_in? }
 *
 * The token is single-use, expires after 24 hours, and is tied to the
 * applicant's enrollment session (see includes/verification.php).
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/verification.php';

session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

// Normalise (unique index is case-insensitive under utf8mb4_unicode_ci anyway).
$email = strtolower($email);

try {
    $pdo = db();

    // Prevent fake/duplicate submissions: the email must not already belong to
    // a registered student.
    if (email_is_registered($pdo, $email)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'This email is already registered to a student account. Please use a different email.',
        ]);
        exit;
    }

    // Throttle rapid re-sends within the same session.
    $now  = time();
    $last = (int) ($_SESSION['verify_last_sent_at'] ?? 0);
    if ($last && ($now - $last) < EMAIL_VERIFICATION_RESEND_SECONDS) {
        $wait = EMAIL_VERIFICATION_RESEND_SECONDS - ($now - $last);
        echo json_encode([
            'success' => false,
            'message' => "Please wait {$wait} second(s) before requesting another verification email.",
        ]);
        exit;
    }

    $sessionId = session_id();
    $created   = create_email_verification($pdo, $email, $sessionId);
    $link      = verification_link($created['token']);

    $name = trim($_SESSION['enroll_name'] ?? '');
    $sent = send_student_email(
        $email,
        $name !== '' ? $name : 'Student',
        VERIFICATION_EMAIL_SUBJECT,
        verification_email_html($name, $email, $link)
    );

    $_SESSION['pending_email']        = $email;
    $_SESSION['verify_last_sent_at']  = $now;
    // A new address invalidates any previously verified one.
    unset($_SESSION['verified_email']);

    $response = [
        'success'    => true,
        'message'    => 'A verification link has been sent to ' . $email . '. Please check your inbox and click the link.',
        'email'      => $email,
        'expires_in' => EMAIL_VERIFICATION_TTL_HOURS * 3600,
    ];

    // On this local setup PHP mail() does not deliver, so (by default) the
    // link is also returned to the form for testing.
    if (!$sent && SHOW_VERIFICATION_LINK_ONSCREEN) {
        $response['link']    = $link;
        $response['message'] = 'Verification link generated for ' . $email . '. (Local demo: mail server is not configured, so use the link shown below.)';
    } elseif (SHOW_VERIFICATION_LINK_ONSCREEN) {
        $response['link'] = $link;
    }

    echo json_encode($response);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not send the verification email: ' . $e->getMessage()]);
}
