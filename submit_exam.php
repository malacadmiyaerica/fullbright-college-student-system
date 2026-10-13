<?php
/**
 * Fullbright College - Student Management System
 * Submit the entrance exam: auto-check answers, store the score.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/exam.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$code = trim($_POST['code'] ?? $_GET['code'] ?? '');

if ($code === '') {
    echo json_encode(['success' => false, 'message' => 'Exam code is missing.']);
    exit;
}

$exam = find_exam($code);
if (!$exam) {
    // Fallback: if the row was lost but the code belongs to an already-scored
    // student, restore it (completed exams only — never score against freshly
    // generated questions).
    $exam = restore_exam_from_students($code, false);
}
if (!$exam) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Exam not found. Please check the code and try again.']);
    exit;
}

// Already taken: return the existing score instead of allowing a retake.
if ($exam['score'] !== null) {
    echo json_encode([
        'success' => true,
        'already' => true,
        'score'   => $exam['score'],
        'total'   => $exam['total'] ?? EXAM_QUESTION_COUNT,
        'message' => 'This exam has already been submitted.',
    ]);
    exit;
}

$rawAnswers  = $_POST['answers'] ?? '';
$answers     = json_decode($rawAnswers, true);
if (!is_array($answers)) {
    $answers = [];
}

$result = score_exam($exam['questions'], $answers);

$stmt = db()->prepare('UPDATE exams SET answers = ?, score = ?, taken_at = NOW() WHERE code = ?');
$stmt->execute([
    json_encode($answers),
    $result['score'],
    $exam['code'],
]);

echo json_encode([
    'success' => true,
    'score'   => $result['score'],
    'total'   => $result['total'],
    'results' => $result['results'],
]);