<?php
/**
 * Fullbright College - Student Management System
 * Generate a new entrance exam: unique code + linked QR code.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/exam.php';

session_start();

header('Content-Type: application/json');

try {
    $pdo = db();

    // Reuse an existing, unfinished exam for this enrollment session.
    $existing = $_SESSION['exam_code'] ?? null;
    if ($existing) {
        $ex = find_exam($existing);
        if ($ex && $ex['score'] === null) {
            echo json_encode([
                'success'   => true,
                'code'      => $ex['code'],
                'exam_url'  => exam_url($ex['code']),
                'questions' => public_questions($ex['questions']),
            ]);
            exit;
        }
        if ($ex && $ex['score'] !== null) {
            echo json_encode([
                'success' => true,
                'code'    => $ex['code'],
                'exam_url'=> exam_url($ex['code']),
                'score'   => $ex['score'],
                'total'   => EXAM_QUESTION_COUNT,
            ]);
            exit;
        }
    }

    $code      = generate_exam_code($pdo);
    $questions = make_exam_questions();

    $stmt = db()->prepare('INSERT INTO exams (code, questions) VALUES (?, ?)');
    $stmt->execute([$code, json_encode($questions)]);

    $_SESSION['exam_code'] = $code;

    echo json_encode([
        'success'   => true,
        'code'      => $code,
        'exam_url'  => exam_url($code),
        'questions' => public_questions($questions),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not generate the exam: ' . $e->getMessage()]);
}