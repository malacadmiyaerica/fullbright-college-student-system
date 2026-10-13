<?php
/**
 * Fullbright College - Student Management System
 * Setup helper: creates the database + tables from schema.sql
 * and prepares the uploads folder.
 *
 * Usage: open http://localhost/setup.php in a browser once, then delete it.
 */

require_once __DIR__ . '/includes/config.php';

$log = [];

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents(__DIR__ . '/schema.sql');

    // Strip SQL comments so a ";" inside one cannot break up statements.
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql);   // /* ... */ block comments
    $sql = preg_replace('#^\s*--.*$#m', '', $sql);   // -- line comments

    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
            $log[] = 'Executed: ' . substr(str_replace("\n", ' ', $statement), 0, 70) . '...';
        }
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
        $log[] = 'Created uploads folder.';
    }
    // Ensure the web server (Apache on XAMPP runs as "daemon") can write.
    @chmod(UPLOAD_DIR, 0777);
    $log[] = 'Uploads folder is writable by the web server.';
    @file_put_contents(UPLOAD_DIR . 'index.html', '');

    $log[] = 'Database "' . DB_NAME . '" is ready. Uploads folder is ready.';
} catch (PDOException $e) {
    $log[] = 'ERROR: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup | Fullbright College</title>
</head>
<body style="font-family:sans-serif;max-width:760px;margin:40px auto;line-height:1.6">
<h1>Fullbright College &mdash; Database Setup</h1>
<pre><?php echo htmlspecialchars(implode("\n", $log)); ?></pre>
<p><a href="index.php">Go to Student Portal</a> &middot; <a href="enroll.php">Go to Enrollment Form</a></p>
<p><strong>Security note:</strong> delete <code>setup.php</code> after running it.</p>
</body>
</html>