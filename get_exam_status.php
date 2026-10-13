<?php
/**
 * Fullbright College - Student Management System
 * Returns the current exam status for the enrollment session.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/exam.php';

session_start();

header('Content-Type: application/json');

$code = $_SESSION['exam_code'] ?? null;

if (!$code) {
    echo json_encode(['success' => true, 'generated' => false]);
    exit;
}

$ex = find_exam($code);
if (!$ex) {
    echo json_encode(['success' => true, 'generated' => false]);
    exit;
}

echo json_encode([
    'success'   => true,
    'generated' => true,
    'code'      => $ex['code'],
    'exam_url'  => exam_url($ex['code']),
    'score'     => $ex['score'],
    'total'     => EXAM_QUESTION_COUNT,
]);