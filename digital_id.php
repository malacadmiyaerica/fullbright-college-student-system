<?php
/**
 * Fullbright College - Student Management System
 * Public Digital ID image download endpoint.
 *
 * The QR code printed on a student's Digital ID points here. Opening it
 * (for example by scanning the QR with a phone) automatically downloads the
 * student's full Digital ID card as a PNG image.
 *
 * The card image is generated on the student's Profile page — identical to
 * the card shown there — and saved via save_id_card.php into /digital_id/.
 * This script only serves that PNG, declaring it as a download.
 */

require_once __DIR__ . '/includes/config.php';

$sid  = trim($_GET['sid'] ?? '');
$safe = preg_replace('/[^A-Za-z0-9_-]/', '', $sid);
if ($safe === '') {
    $safe = 'student';
}

$png = ID_CARD_DIR . $safe . '.png';

if (is_file($png)) {
    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($png));
    header('Content-Disposition: attachment; filename="' . $safe . '.png"');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($png);
    exit;
}

// No generated card on file yet: return a clearly-labelled placeholder image
// instead of an error, so a scan still yields an image.
$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1012" height="638" viewBox="0 0 1012 638">'
    . '<rect width="1012" height="638" fill="#0f2a5c"/>'
    . '<rect x="30" y="30" width="952" height="578" fill="none" stroke="#e4b23f" stroke-width="6"/>'
    . '<text x="506" y="280" fill="#ffffff" font-family="Segoe UI, Arial, sans-serif" font-size="46" font-weight="700" text-anchor="middle">FULLBRIGHT COLLEGE</text>'
    . '<text x="506" y="342" fill="#e4b23f" font-family="Segoe UI, Arial, sans-serif" font-size="30" text-anchor="middle">Your Digital ID is being prepared</text>'
    . '<text x="506" y="392" fill="#9fb0d8" font-family="Segoe UI, Arial, sans-serif" font-size="24" text-anchor="middle">Student No. ' . htmlspecialchars($safe) . ' &mdash; please open your Profile to generate your card</text>'
    . '</svg>';
header('Content-Type: image/svg+xml');
header('Content-Length: ' . strlen($svg));
header('Content-Disposition: attachment; filename="' . $safe . '-digital-id.svg"');
header('Cache-Control: public, max-age=300');
echo $svg;
exit;