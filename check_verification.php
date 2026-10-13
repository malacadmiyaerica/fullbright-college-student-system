<?php
/**
 * Fullbright College - Student Management System
 * Report the email-verification status for the current enrollment session.
 *
 * GET -> JSON: { success, verified, email? }
 *
 * The enrollment form polls this after sending a link; it also picks up a
 * verification that happened in another tab/browser for the same session.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/verification.php';

session_start();

header('Content-Type: application/json');

$email = current_verified_email(db());

echo json_encode([
    'success'  => true,
    'verified' => $email !== null,
    'email'    => $email,
]);
