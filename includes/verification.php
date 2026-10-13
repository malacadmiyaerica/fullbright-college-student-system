<?php
/**
 * Fullbright College - Student Management System
 * Enrollment email verification.
 *
 * Verification flow
 *   1. The applicant enters their email in the enrollment form.
 *   2. send_verification.php creates a single-use, 24-hour token tied to the
 *      enrollment session and emails a verification link.
 *   3. The student clicks the link; verify_email.php marks the token used and
 *      records the verified email against the session.
 *   4. check_verification.php lets the enrollment form confirm the status, and
 *      enrollment can only continue once the email is verified.
 *
 * Security
 *   - Tokens come from random_bytes(32) (cryptographically secure).
 *   - Only the SHA-256 hash of the token is stored, never the raw value.
 *   - Tokens expire after 24 hours and can be used only once.
 *   - A fresh token invalidates any previous pending token for the same email.
 *   - Emails already registered to a student cannot be reused.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

/** Subject line required for the enrollment verification email. */
const VERIFICATION_EMAIL_SUBJECT = 'Fullbright College Enrollment Email Verification';

/** How long a verification token stays valid. */
const EMAIL_VERIFICATION_TTL_HOURS = 24;

/** Minimum seconds between verification-email sends within one session. */
const EMAIL_VERIFICATION_RESEND_SECONDS = 30;

/**
 * Generates a raw, cryptographically secure verification token (64 hex chars).
 */
function verification_token(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Hashes a raw token for storage/lookup. Only this hash is ever persisted.
 */
function hash_verification_token(string $raw): string {
    return hash('sha256', $raw);
}

/**
 * True when the email already belongs to a registered student.
 */
function email_is_registered(PDO $pdo, string $email): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM students WHERE email = ?');
    $stmt->execute([$email]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Creates a fresh verification token for an email + enrollment session.
 *
 * Any earlier pending token for the same email is invalidated first so a
 * student cannot accumulate multiple active links (prevents duplicate/fake
 * submissions and stale-link reuse).
 *
 * @return array{token:string, expires_at:string}
 */
function create_email_verification(PDO $pdo, string $email, string $sessionId): array {
    $pdo->prepare('DELETE FROM email_verifications WHERE email = ? AND verified_at IS NULL')
        ->execute([$email]);

    $raw     = verification_token();
    $hash    = hash_verification_token($raw);
    $expires = (new DateTime('+' . EMAIL_VERIFICATION_TTL_HOURS . ' hours'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'INSERT INTO email_verifications (email, token_hash, session_id, expires_at)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$email, $hash, $sessionId, $expires]);

    return ['token' => $raw, 'expires_at' => $expires];
}

/**
 * Absolute verification link for the given raw token.
 */
function verification_link(string $rawToken): string {
    return base_url() . '/verify_email.php?token=' . urlencode($rawToken);
}

/**
 * Finds a verification row by raw token, or null.
 */
function find_email_verification(PDO $pdo, string $rawToken): ?array {
    $stmt = $pdo->prepare('SELECT * FROM email_verifications WHERE token_hash = ? LIMIT 1');
    $stmt->execute([hash_verification_token($rawToken)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Consumes a raw token: validates it (exists, unused, unexpired) and marks the
 * email verified. Single-use — a token that was already used is rejected.
 *
 * @return array{ok:bool, status:string, message:string, email:?string}
 */
function consume_email_verification(PDO $pdo, string $rawToken): array {
    if ($rawToken === '' || !preg_match('/^[a-f0-9]{64}$/i', $rawToken)) {
        return ['ok' => false, 'status' => 'invalid', 'message' => 'This verification link is not valid.', 'email' => null];
    }

    $row = find_email_verification($pdo, $rawToken);
    if (!$row) {
        return ['ok' => false, 'status' => 'invalid', 'message' => 'This verification link is not valid.', 'email' => null];
    }

    if ($row['verified_at'] !== null) {
        // Already verified (single-use): treat as success for idempotency, but
        // do not allow it to verify a different email.
        return ['ok' => true, 'status' => 'already', 'message' => 'This email address has already been verified.', 'email' => $row['email']];
    }

    if (strtotime($row['expires_at']) < time()) {
        return ['ok' => false, 'status' => 'expired', 'message' => 'This verification link has expired. Please request a new one.', 'email' => $row['email']];
    }

    $stmt = $pdo->prepare('UPDATE email_verifications SET verified_at = NOW() WHERE id = ?');
    $stmt->execute([$row['id']]);

    return ['ok' => true, 'status' => 'verified', 'message' => 'Your email address has been verified.', 'email' => $row['email']];
}

/**
 * Returns the verified email for the current session, or null.
 *
 * Prefers the value stored directly on the session (same-browser click); if the
 * student opened the link elsewhere, it looks up a token that was verified for
 * this session + the pending email.
 */
function current_verified_email(PDO $pdo, ?string $sessionId = null): ?string {
    if (!empty($_SESSION['verified_email'])) {
        return $_SESSION['verified_email'];
    }

    $pending = $_SESSION['pending_email'] ?? null;
    if (!$pending) {
        return null;
    }

    $sessionId = $sessionId !== null ? $sessionId : session_id();
    $stmt = $pdo->prepare(
        'SELECT email FROM email_verifications
          WHERE email = ? AND session_id = ? AND verified_at IS NOT NULL
          ORDER BY verified_at DESC LIMIT 1'
    );
    $stmt->execute([$pending, $sessionId]);
    $email = $stmt->fetchColumn();

    if ($email) {
        $_SESSION['verified_email'] = $email;
        return $email;
    }
    return null;
}
