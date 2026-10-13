<?php
/**
 * Fullbright College - Student Management System
 * Entrance exam question bank + generation + scoring.
 *
 * Each question:
 *   ['subject' => 'Math' | 'English', 'q' => text,
 *    'options' => [4 strings], 'answer' => index of the correct option]
 */

require_once __DIR__ . '/config.php';

/**
 * Generates a unique, human-usable entrance-exam code (7 chars,
 * uppercase, no ambiguous 0O1Il). Ensures it does not already exist
 * in the exams table before returning it.
 */
function generate_exam_code(PDO $pdo): string {
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $code = '';
        for ($i = 0; $i < 7; $i++) {
            $code .= $alpha[random_int(0, strlen($alpha) - 1)];
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM exams WHERE code = ?');
        $stmt->execute([$code]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $code;
        }
    }
    throw new RuntimeException('Could not generate a unique exam code.');
}


/**
 * Builds a question object with shuffled options.
 */
function build_question(string $subject, string $q, string $correct, array $distractors): array {
    $options = array_values(array_unique(array_merge([$correct], $distractors)));
    if (count($options) < 4) {
        // fill with generic placeholders if uniqueness collapsed
        $extra = ['A. 1', 'B. 2', 'C. 3', 'D. 4', 'E. 5'];
        foreach ($extra as $e) {
            if (count($options) >= 4) break;
            if (!in_array($e, $options, true)) $options[] = $e;
        }
    }
    shuffle($options);
    $answer = array_search($correct, $options, true);
    return [
        'subject' => $subject,
        'q'       => $q,
        'options' => $options,
        'answer'  => $answer,
    ];
}

/**
 * A single randomly-parameterised Math question.
 */
function random_math_question(): array {
    $type = random_int(1, 8);

    switch ($type) {
        case 1: // addition
            $a = random_int(11, 89); $b = random_int(11, 45);
            $c = (string) ($a + $b);
            return build_question('Math', "What is $a + $b ?", $c, [(string)($a+$b+3), (string)($a+$b-4), (string)($a+$b*2)]);
        case 2: // subtraction
            $a = random_int(41, 99); $b = random_int(11, $a - 10);
            $c = (string) ($a - $b);
            return build_question('Math', "What is $a − $b ?", $c, [(string)($a-($b+3)), (string)($a-($b-2)), (string)($a-$b+4)]);
        case 3: // multiplication
            $a = random_int(3, 9); $b = random_int(4, 12);
            $c = (string) ($a * $b);
            return build_question('Math', "What is $a × $b ?", $c, [(string)($a*($b+1)), (string)(($a+1)*$b), (string)($a*$b-$a)]);
        case 4: // division (exact)
            $b = random_int(3, 9); $q = random_int(3, 12); $a = $b * $q;
            $c = (string) $q;
            return build_question('Math', "What is $a ÷ $b ?", $c, [(string)($q+2), (string)($q-1), (string)($q+$b)]);
        case 5: // percentage
            $p = random_int(10, 90); $n = random_int(20, 90);
            $c = (string) ((int) round($p * $n / 100));
            return build_question('Math', "What is $p% of $n ?", $c, [(string)((int)round($p*$n/100)+5), (string)((int)round($p*$n/100)-5), (string)((int)round(($p-10)*$n/100))]);
        case 6: // square
            $n = random_int(4, 15);
            $c = (string) ($n * $n);
            return build_question('Math', "What is {$n}² ?", $c, [(string)(($n+1)*($n+1)), (string)($n*($n+2)), (string)($n*($n-1))]);
        case 7: // order of operations  a + b × c
            $b = random_int(2, 6); $c = random_int(3, 9); $a = random_int(5, 30);
            $value = $a + $b * $c;
            $c2 = (string) $value;
            return build_question('Math', "What is $a + $b × $c ?", $c2, [(string)($value+2), (string)($value-3), (string) (($a+$b)*$c)]);
        default: // nice double
            $n = random_int(3, 24);
            $c = (string) ($n * 2);
            return build_question('Math', "What is the value of 2 × $n, i.e. twice $n ?", $c, [(string)($n+2), (string)($n*3), (string)($n/2)]);
    }
}

/**
 * Static English question bank (grammar, vocabulary, comprehension).
 */
function english_bank(): array {
    return [
        ['q' => 'Choose the synonym of "happy".', 'options' => ['Joyful', 'Angry', 'Tired', 'Nervous'], 'answer' => 0],
        ['q' => 'Choose the opposite (antonym) of "ancient".', 'options' => ['Modern', 'Old', 'Aged', 'Historic'], 'answer' => 0],
        ['q' => 'She ___ to school every morning.', 'options' => ['goes', 'go', 'going', 'gone'], 'answer' => 0],
        ['q' => 'Choose the correctly written sentence.', 'options' => ['The dog ran fast.', 'The dog run fast.', 'The dogs runs fast.', 'The dog running fast.'], 'answer' => 0],
        ['q' => 'Cat is to kitten as dog is to ___.', 'options' => ['puppy', 'calf', 'cub', 'kid'], 'answer' => 0],
        ['q' => 'What is the plural of "child"?', 'options' => ['children', 'childs', 'childes', 'child'], 'answer' => 0],
        ['q' => 'The basketball team ___ playing very well.', 'options' => ['is', 'are', 'were', 'am'], 'answer' => 0],
        ['q' => 'Which word is spelled correctly?', 'options' => ['receive', 'recieve', 'receve', 'reccive'], 'answer' => 0],
        ['q' => 'She is really good ___ mathematics.', 'options' => ['at', 'on', 'in', 'of'], 'answer' => 0],
        ['q' => 'They ___ a movie last night.', 'options' => ['watched', 'watch', 'watching', 'watches'], 'answer' => 0],
        ['q' => 'Select the correct article: "___ apple keeps the doctor away."', 'options' => ['An', 'A', 'The', 'One'], 'answer' => 0],
        ['q' => 'He ran very ___.', 'options' => ['quickly', 'quick', 'more quick', 'quickest'], 'answer' => 0],
        ['q' => 'The children played ___ the park.', 'options' => ['in', 'on', 'of', 'to'], 'answer' => 0],
        ['q' => 'What does "generous" mean?', 'options' => ['Willing to give and share', 'Very quiet', 'Afraid of change', 'Always late'], 'answer' => 0],
        ['q' => 'Choose the correct contraction: "They ___ here yet."', 'options' => ['haven\'t', 'hasnt', 'havent', 'have not yet seen'], 'answer' => 0],
        ['q' => 'Which sentence uses correct punctuation?', 'options' => ['Where are you going?', 'Where are you going.', 'where are you going?', 'Where are you going'], 'answer' => 0],
    ];
}

/**
 * Randomly generates EXAM_QUESTION_COUNT questions:
 * Math 7-8, English 7-8.
 */
function make_exam_questions(): array {
    $math = random_int(7, 8);
    $eng  = EXAM_QUESTION_COUNT - $math;

    $questions = [];
    for ($i = 0; $i < $math; $i++) {
        $questions[] = random_math_question();
    }

    $bank = english_bank();
    shuffle($bank);
    $picked = array_slice($bank, 0, $eng);
    foreach ($picked as $item) {
        $questions[] = [
            'subject' => 'English',
            'q'       => $item['q'],
            'options' => $item['options'],
            'answer'  => $item['answer'],
        ];
    }

    shuffle($questions);
    return $questions;
}

/**
 * Returns questions safe to expose to the browser (answer key removed).
 */
function public_questions(array $questions): array {
    return array_map(function ($q) {
        return ['subject' => $q['subject'], 'q' => $q['q'], 'options' => $q['options']];
    }, $questions);
}

/**
 * Auto-check submitted answers.
 *   $answers — array of selected option indexes (may contain null / -1).
 * Returns ['score' => n, 'total' => count, 'results' => [qIndex => true/false]].
 */
function score_exam(array $questions, array $answers): array {
    $score   = 0;
    $results = [];
    $total   = count($questions);

    foreach ($questions as $i => $q) {
        $selected = $answers[$i] ?? null;
        $correct  = (int) $q['answer'];
        $isRight  = ($selected !== null && $selected !== '' && (int) $selected === $correct);
        if ($isRight) $score++;
        $results[$i] = $isRight;
    }

    return ['score' => $score, 'total' => $total, 'results' => $results];
}

/**
 * Load an exam row by code, or null.
 */
function find_exam(string $code): ?array {
    $stmt = db()->prepare('SELECT * FROM exams WHERE code = ?');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['questions'] = json_decode($row['questions'], true) ?? [];
    $row['answers']   = $row['answers'] ? (json_decode($row['answers'], true) ?? []) : [];
    return $row;
}

/**
 * Rebuild a missing exams row from the student it belongs to, so an exam
 * whose stored questions were lost can still be opened.
 *
 *   - Completed exam (students.exam_score set): recreate the row with the
 *     preserved score so the page shows "already taken".
 *   - Unfinished exam: when $allowFresh is true, generate a brand-new set of
 *     questions under the same code so the student can sit the exam again.
 *     When false, return null (used by submit_exam.php to avoid scoring
 *     answers against unrelated fresh questions).
 *
 * Returns the rebuilt exam array, or null when the code is not tied to a
 * student (or fresh generation is not allowed).
 */
function restore_exam_from_students(string $code, bool $allowFresh = true): ?array {
    $stmt = db()->prepare('SELECT exam_score FROM students WHERE exam_code = ? LIMIT 1');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    if (!$row) return null;

    $score = $row['exam_score'] !== null ? (int) $row['exam_score'] : null;

    if ($score === null && !$allowFresh) {
        return null;
    }

    if ($score === null) {
        $questions = make_exam_questions();
        $ins = db()->prepare('INSERT IGNORE INTO exams (code, questions) VALUES (?, ?)');
        $ins->execute([$code, json_encode($questions)]);
    } else {
        $ins = db()->prepare('INSERT IGNORE INTO exams (code, questions, score, taken_at) VALUES (?, ?, ?, NOW())');
        $ins->execute([$code, json_encode([]), $score]);
    }

    return find_exam($code);
}