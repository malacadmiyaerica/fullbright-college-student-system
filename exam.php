<?php
/**
 * Fullbright College - Student Management System
 * Entrance Examination page.
 * Accessible by code (?code=XXXXXXX) or by scanning the QR code.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/exam.php';

session_start();

$code = strtoupper(trim($_GET['code'] ?? ''));

$exam       = null;
$started    = false;
$already    = null;

if ($code !== '') {
    $exam = find_exam($code);
    if (!$exam) {
        // Fallback: the stored questions were lost but the code belongs to a
        // student — rebuild the exam so it still opens.
        $exam = restore_exam_from_students($code);
    }
    if ($exam) {
        if ($exam['score'] !== null) {
            $already = $exam;
        } else {
            $started = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrance Examination | Fullbright College</title>
<link rel="stylesheet" href="css/style.css?v=20240921">
<script src="js/qrcode.js"></script>
</head>
<body class="exam-page">

<div class="page-backdrop" aria-hidden="true"></div>

<header class="site-header">
    <div class="brand">
        <img src="assets/logo.png" alt="Fullbright College Logo" class="brand-logo">
        <div class="brand-text">
            <h1>Fullbright College</h1>
            <p>Student Management System &mdash; Entrance Examination</p>
        </div>
    </div>
</header>

<main class="page-main">
    <section class="card exam-card">

        <?php if ($code === ''): ?>
            <!-- 1. Enter exam code -->
            <div class="card-head"><h2>Entrance Examination</h2>
                <p>Enter your exam code to begin. Your code was generated when you clicked <strong>Generate Exam</strong> (or from the QR code).</p>
            </div>
            <form id="exam-code-form" class="exam-code-form stack-form">
                <div class="field">
                    <label for="exam_code_input">Exam Code <em>*</em></label>
                    <input type="text" id="exam_code_input" name="code" maxlength="20" placeholder="e.g. ABCDEFG" autocomplete="off" required>
                </div>
                <div id="exam-code-alert" class="alert hidden"></div>
                <button type="submit" class="btn btn-primary btn-lg">Start the Exam</button>
            </form>

        <?php elseif (!$exam): ?>
            <!-- 2. Invalid code -->
            <div class="card-head"><h2>Exam Not Found</h2>
                <p>The code <strong><?php echo htmlspecialchars($code); ?></strong> is invalid or expired.</p>
            </div>
            <p><a class="btn btn-ghost" href="exam.php">&larr; Try another code</a></p>

        <?php elseif ($already): ?>
            <!-- 3. Already taken -->
            <div class="card-head"><h2>Entrance Examination Completed</h2>
                <p>This exam was already submitted under code <strong class="exam-code-text"><?php echo htmlspecialchars($already['code']); ?></strong>.</p>
            </div>
            <div class="score-box score-done">
                <span class="score-label">Your Score</span>
                <span class="score-value"><?php echo (int) $already['score']; ?>/<?php echo (int) EXAM_QUESTION_COUNT; ?></span>
            </div>

        <?php else: ?>
            <!-- 4. Live exam -->
            <div class="card-head">
                <h2>Fullbright College Entrance Examination</h2>
                <p>Answer all <?php echo (int) EXAM_QUESTION_COUNT; ?> questions (Math + English). Choose the best answer for each. Your score is computed automatically when you press <strong>Submit Exam</strong>.</p>
            </div>

            <div class="exam-code-strip">
                <span>Exam Code: <strong class="exam-code-text"><?php echo htmlspecialchars($code); ?></strong></span>
                <div class="exam-qr-mini" id="exam-qr-mini" title="QR code linked to this exam"></div>
            </div>

            <form id="exam-form" data-code="<?php echo htmlspecialchars($code); ?>" novalidate>
                <div id="exam-alert" class="alert hidden"></div>

                <?php foreach ($exam['questions'] as $i => $q): $n = $i + 1; ?>
                <div class="exam-question" data-index="<?php echo $i; ?>">
                    <p class="question-stem">
                        <span class="q-num"><?php echo $n; ?>.</span>
                        <span class="q-subject badge badge-type"><?php echo htmlspecialchars($q['subject']); ?></span>
                        <?php echo htmlspecialchars($q['q']); ?>
                    </p>
                    <div class="option-list">
                        <?php foreach ($q['options'] as $oi => $opt): $letter = chr(65 + $oi); ?>
                        <label class="option-item">
                            <input type="radio" name="q<?php echo $i; ?>" value="<?php echo $oi; ?>">
                            <span class="option-letter"><?php echo $letter; ?></span>
                            <span class="option-text"><?php echo htmlspecialchars($opt); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="submit" id="exam-submit-btn" class="btn btn-primary btn-lg">Submit Exam</button>
                    <button type="reset" class="btn btn-ghost">Clear Answers</button>
                </div>
            </form>

            <!-- Result view (shown after submit) -->
            <div id="exam-result" class="hidden">
                <div class="score-box">
                    <span class="score-label">Exam Score</span>
                    <span class="score-value" id="exam-score-value">--</span>
                </div>
                <p class="muted hint">Your score has been recorded automatically. You may now return to the enrollment form and submit your application.</p>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php if ($started): ?>
<script>
(function () {
    'use strict';
    var form = document.getElementById('exam-form');
    if (!form) return;

    // Offline QR code linked to this exam (works without internet).
    var qrBox = document.getElementById('exam-qr-mini');
    if (qrBox && typeof qrcode === 'function') {
        try {
            var qr = qrcode(0, 'M');
            qr.addData(<?php echo json_encode(exam_url($code)); ?>);
            qr.make();
            qrBox.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 2 });
        } catch (err) { /* QR rendering failed; code access still works */ }
    }

    var alertBox = document.getElementById('exam-alert');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        alertBox.className = 'alert hidden';

        var unanswered = form.querySelectorAll('.exam-question').length;
        var answered = form.querySelectorAll('input[type="radio"]:checked').length;
        if (answered < unanswered && !window.confirm('You still have ' + (unanswered - answered) + ' unanswered question(s). Submit anyway?')) {
            return;
        }

        var answers = [];
        form.querySelectorAll('.exam-question').forEach(function (q) {
            var checked = q.querySelector('input[type="radio"]:checked');
            answers.push(checked ? parseInt(checked.value, 10) : null);
        });

        var body = new URLSearchParams();
        body.append('code', form.getAttribute('data-code'));
        body.append('answers', JSON.stringify(answers));

        var btn = document.getElementById('exam-submit-btn');
        var original = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Checking answers...';

        fetch('submit_exam.php', { method: 'POST', body: body })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) {
                    alertBox.className = 'alert alert-error';
                    alertBox.textContent = data.message || 'Could not submit the exam.';
                    return;
                }

                // Show non-editable score.
                form.classList.add('hidden');
                var result = document.getElementById('exam-result');
                result.classList.remove('hidden');
                document.getElementById('exam-score-value').textContent = data.score + '/' + data.total;

                // Mark correct / wrong per question (review).
                if (data.results) {
                    form.querySelectorAll('.exam-question').forEach(function (q) {
                        var idx = parseInt(q.getAttribute('data-index'), 10);
                        var right = data.results[idx] === true;
                        q.classList.add(right ? 'is-correct' : 'is-wrong');
                    });
                }

                // Tell the parent page (enrollment form) the exam is done.
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ source: 'fullbright-exam', action: 'done', code: form.getAttribute('data-code'), score: data.score, total: data.total }, '*');
                }
            })
            .catch(function (err) {
                alertBox.className = 'alert alert-error';
                alertBox.textContent = 'Something went wrong: ' + err.message;
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = original;
            });
    });

    // Support "Enter exam code" page.
    var codeForm = document.getElementById('exam-code-form');
    if (codeForm) {
        codeForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var codeInput = document.getElementById('exam_code_input');
            var code = (codeInput.value || '').trim().toUpperCase().replace(/\s+/g, '');
            if (code) {
                window.location.href = 'exam.php?code=' + encodeURIComponent(code);
            }
        });
    }
})();
</script>
<?php endif; ?>
</body>
</html>