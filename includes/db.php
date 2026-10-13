<?php
/**
 * Fullbright College - Student Management System
 * Database connection (PDO), section assignment, password helpers.
 */

require_once __DIR__ . '/config.php';

/**
 * Returns the shared PDO connection.
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Database connection failed. Please make sure MySQL is running and schema.sql has been imported. ' . $e->getMessage());
        }
    }
    return $pdo;
}

/* ------------------------------------------------------------
 * Required enrollment documents (field => label)
 *
 * These labels are BOTH the <label> text on enroll.php AND the
 * document_name persisted to requirements.* (shown on the receipt,
 * dashboards and staff). Keep the two in sync when editing.
 * ------------------------------------------------------------ */
function requirements_map(): array {
    return [
        'req_psa'        => 'PSA Birth Certificate',
        'req_good_moral' => 'Certificate of Good Moral Character',
        'req_form138'    => 'Form 138 (Original H.S. Report Card)',
        'req_id_2x2'     => '3 pcs 2x2 ID Picture (Colored, White Background)',
    ];
}

/* ------------------------------------------------------------
 * Names
 * ------------------------------------------------------------ */

/**
 * Display name without suffix: "Juan B. Dela Cruz".
 */
function name_of_student(array $student): string {
    $parts = array_filter([
        $student['first_name']  ?? '',
        $student['middle_name'] ?? '',
        $student['last_name']   ?? '',
    ]);
    return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
}

/**
 * Display name with suffix: "Juan B. Dela Cruz Jr.".
 */
function full_name_with_suffix(array $student): string {
    $suffix = trim($student['suffix'] ?? '');
    $name   = name_of_student($student);
    return $suffix !== '' ? $name . ' ' . $suffix : $name;
}

function student_age(string $birthday): string {
    $d = new DateTime($birthday);
    return (string) $d->diff(new DateTime('today'))->y;
}

/* ------------------------------------------------------------
 * Addresses
 * ------------------------------------------------------------ */

/**
 * Normalises an address for comparison (trim + collapse spaces + lowercase).
 */
function normalize_address(?string $address): string {
    return strtolower(trim(preg_replace('/\s+/', ' ', (string) $address)));
}

/**
 * True when the student's Current Address is the same as the Permanent Address.
 *
 * Honours the explicit "Same as Permanent Address" flag; when the flag is not
 * set it still treats two identical, non-empty addresses as the same.
 */
function student_same_address(array $student): bool {
    if (!empty($student['same_address'])) {
        return true;
    }
    $perm = normalize_address($student['permanent_address'] ?? '');
    $curr = normalize_address($student['current_address'] ?? '');
    return $perm !== '' && $perm === $curr;
}

/* ------------------------------------------------------------
 * Program / department helpers
 * ------------------------------------------------------------ */

/**
 * Programs offered under this department:
 * short program code => full official title.
 */
function ect_programs(): array {
    return [
        'BS in Information Technology'                => 'Bachelor of Science in Information Technology',
        'BS in Computer Science'                      => 'Bachelor of Science in Computer Science',
        'BS in Computer Engineering'                  => 'Bachelor of Science in Computer Engineering',
        'BS in Electronics & Communication Engineering' => 'Bachelor of Science in Electronics & Communication Engineering',
    ];
}

/**
 * Short code used to build section names, e.g. IT1A, CS2B, ECE3C.
 */
function program_acronym(string $program): string {
    $map = [
        'BS in Information Technology'                => 'IT',
        'BS in Computer Science'                      => 'CS',
        'BS in Computer Engineering'                  => 'CpE',
        'BS in Electronics & Communication Engineering' => 'ECE',
    ];
    return $map[$program] ?? preg_replace('/[^A-Za-z]/', '', $program);
}

/* ------------------------------------------------------------
 * Sections (auto-assigned, max students per section)
 * ------------------------------------------------------------ */

/**
 * Assigns the next free section for a program's 1st-year cohort.
 * Sections are named ACRONYM + YEAR + LETTER (e.g. IT1A). A section
 * opens when the previous one reaches SECTION_CAPACITY students.
 */
function assign_section(PDO $pdo, string $program): string {
    $prefix  = program_acronym($program) . '1';
    $pattern = $prefix . '%';

    // Reuse the earliest section that still has a free slot.
    $open = $pdo->prepare(
        'SELECT section, COUNT(*) AS cnt FROM students
         WHERE section LIKE ?
         GROUP BY section HAVING cnt < ?
         ORDER BY section ASC LIMIT 1'
    );
    $open->execute([$pattern, SECTION_CAPACITY]);
    $row = $open->fetch();
    if ($row) {
        return $row['section'];
    }

    // Otherwise open a new section letter after the highest one in use.
    $max = $pdo->prepare('SELECT MAX(section) FROM students WHERE section LIKE ?');
    $max->execute([$pattern]);
    $last = $max->fetchColumn();
    if (!$last) {
        return $prefix . 'A';
    }
    $nextLetter = chr(ord(substr($last, -1)) + 1); // A -> B -> C ...
    return $prefix . $nextLetter;
}

/**
 * Fills in a student's section (used at enrollment and again at login in
 * case the section had not been assigned yet).
 */
function ensure_student_section(PDO $pdo, array $student): array {
    if (empty($student['section'])) {
        $section = assign_section($pdo, $student['program']);
        $stmt = $pdo->prepare('UPDATE students SET section = ? WHERE id = ?');
        $stmt->execute([$section, $student['id']]);
        $student['section'] = $section;
    }
    return $student;
}

/* ------------------------------------------------------------
 * Miscellaneous helpers
 * ------------------------------------------------------------ */

/**
 * Builds a browsable URL from an upload path using the configured prefix.
 */
function upload_public_url(string $path): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    return rtrim(UPLOAD_URL_PREFIX, '/') . '/' . ltrim(str_replace('\\', '/', $path), '/');
}

/**
 * HTML-safe URL to a public exam page for a given exam code.
 */
function exam_public_url(string $code): string {
    return base_url() . '/exam.php?code=' . urlencode($code);
}

/**
 * Alias kept for older callers (dashboard.php). Same as ensure_student_section().
 */
function ensure_section(PDO $pdo, array $student): array { return ensure_student_section($pdo, $student); }

/**
 * Generates a NEW unique Student ID in YYSSXXXXXX format:
 *
 *   YY      = current year (last 2 digits)            -- date('y') => "26"
 *   SS      = semester code                           -- '01' first, '02' second
 *   XXXXXX  = 6-digit random sequence (mt_rand)       -- 000001..999999
 *
 * Example: for the 2026-2027 first semester -> "2601" + "000042" => 2601000042
 * This matches the students.student_id column (VARCHAR(12), 10 chars).
 *
 * Uniqueness is enforced by re-checking the value against existing rows and
 * retrying with a fresh random sequence until a free one is found (same
 * strategy as generate_exam_code()).
 *
 * @param PDO    $pdo      live database handle
 * @param string $semester semester code; defaults to '01' (first semester)
 * @return string the unique Student ID (10 characters)
 * @throws RuntimeException if no free ID could be produced in 50 attempts
 */
function generate_student_id(PDO $pdo, string $semester = '01'): string {
    if ($semester !== '01' && $semester !== '02') {
        $semester = '01'; // only two valid semesters accepted
    }

    $year = date('y');                  // YY  — last two digits of the current year
    $prefix = $year . $semester;        // e.g. "2601"

    for ($attempt = 0; $attempt < 50; $attempt++) {
        $seq  = str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $id   = $prefix . $seq;         // YYSS + XXXXXX = 10 chars

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM students WHERE student_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $id;                 // unique — return it
        }
    }

    throw new RuntimeException('Could not generate a unique Student ID after 50 attempts.');
}

/**
 * Generates a secure random password for a new student account.
 *
 * Guarantees the policy: at least 8 characters, with uppercase and lowercase
 * letters, at least one digit and at least one special character (!@#$%^&*).
 * Uses a cryptographically secure random source and avoids look-alike
 * characters (l/I/O/0/1) so it is easy to read from the receipt.
 *
 * @param int $length total password length (>= 8)
 */
function generate_password(int $length = 12): string {
    $lower   = 'abcdefghjkmnpqrstuvwxyz';       // no l, i, o
    $upper   = 'ABCDEFGHJKMNPQRSTUVWXYZ';       // no I, O
    $digits  = '23456789';                       // no 0, 1
    $special = '!@#$%^&*';
    $all     = $lower . $upper . $digits . $special;

    $length = max(8, $length);

    // Seed with one of each required class.
    $chars = [
        $lower[random_int(0, strlen($lower) - 1)],
        $upper[random_int(0, strlen($upper) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $special[random_int(0, strlen($special) - 1)],
    ];
    for ($i = count($chars); $i < $length; $i++) {
        $chars[] = $all[random_int(0, strlen($all) - 1)];
    }

    // Fisher-Yates shuffle so the required classes are not in fixed positions.
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        $tmp = $chars[$i];
        $chars[$i] = $chars[$j];
        $chars[$j] = $tmp;
    }

    return implode('', $chars);
}

/**
 * Public URL of an uploaded enrollment document or 2x2 photo.
 *
 * upload_file() stores the file inside UPLOAD_DIR with a unique relative name;
 * this turns that relative name into the web URL the receipt, profile and
 * dashboard render (saved into students/requirements rows).
 *
 * @param string $fname relative filename stored by upload_file(), e.g. "doc_ab12cd34.pdf"
 * @return string       public URL, e.g. "uploads/doc_ab12cd34.pdf"
 */
function upload_url(string $fname): string {
    return rtrim(UPLOAD_URL, '/') . '/' . ltrim($fname, '/');
}
