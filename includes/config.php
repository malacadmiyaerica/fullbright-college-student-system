<?php
/**
 * Fullbright College - Student Management System
 * Configuration
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'fullbright_college');
define('DB_USER', 'root');
define('DB_PASS', '');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', 'uploads/');

define('ID_CARD_DIR', __DIR__ . '/../digital_id/');
define('ID_CARD_URL', 'digital_id/');

define("MAX_FILE_SIZE", 2 * 1024 * 1024); // 2 MB (validation limit)

/* ------------------------------------------------------------
 * URL helpers
 * ------------------------------------------------------------ */

/**
 * Absolute base URL of the application, e.g. http://localhost/SYSTEM FINALS
 * Works on http and https, and in sub-folders (XAMPP htdocs).
 */
function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return $scheme . '://' . $host . rtrim($dir, '/');
}

/**
 * Optional manual override for the public URL used in shared links (e.g. the
 * Digital ID QR code). Leave it EMPTY for normal use: the app then detects
 * the host it is being served from automatically, so the QR works on any
 * server (localhost, LAN IP, or a real domain) without editing code.
 *
 * Only set this when you need to force a specific address, e.g. a public
 * tunnel (https://abc.ngrok.app) or a canonical domain. You can also set the
 * FULLBRIGHT_PUBLIC_URL environment variable instead.
 */
define('PUBLIC_BASE_URL', getenv('FULLBRIGHT_PUBLIC_URL') ?: '');

/**
 * Detects the request scheme, including https behind a reverse proxy.
 */
function request_scheme(): string {
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return 'https';
    }
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return 'https';
    }
    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return 'https';
    }
    return 'http';
}

/**
 * True when the host is a local/loopback address that a phone cannot reach.
 */
function is_local_host(string $host): bool {
    $host = strtolower(preg_replace('/:\d+$/', '', trim($host)));
    return $host === '' || $host === 'localhost' || $host === '127.0.0.1'
        || $host === '0.0.0.0' || $host === '::1' || $host === '[::1]' || substr($host, -6) === '.local';
}

/**
 * Best-effort discovery of this machine's LAN IPv4 address, so a QR code
 * opened on a phone (same Wi-Fi) can reach the local development server.
 * Returns null when it cannot be determined.
 */
function server_lan_ip(): ?string {
    $isUsable = function ($ip): bool {
        return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && !in_array($ip, ['0.0.0.0', '127.0.0.1'], true);
    };

    $addr = (string) ($_SERVER['SERVER_ADDR'] ?? '');
    if ($isUsable($addr)) {
        return $addr;
    }

    if (function_exists('shell_exec')) {
        foreach (['ipconfig getifaddr en0', 'ipconfig getifaddr en1', 'hostname -I', 'hostname -i'] as $cmd) {
            $out = @shell_exec($cmd . ' 2>/dev/null');
            if (!$out) {
                continue;
            }
            foreach (preg_split('/\s+/', trim($out)) as $ip) {
                if ($isUsable($ip)) {
                    return $ip;
                }
            }
        }
    }
    return null;
}

/**
 * Base URL for public/shared links (QR codes). Auto-detects the host the site
 * is served from. When the request comes in on localhost, it substitutes the
 * machine's LAN IP (same port) so a phone on the same Wi-Fi can scan the QR.
 * Set PUBLIC_BASE_URL to force a specific address (e.g. a tunnel or domain).
 */
function public_base_url(): string {
    $configured = trim((string) PUBLIC_BASE_URL);
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return '';
    }
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));

    if (is_local_host($host)) {
        $lan = server_lan_ip();
        if ($lan !== null) {
            $port = '';
            if (preg_match('/:(\d+)$/', $host, $m)) {
                $port = ':' . $m[1];
            } elseif (!empty($_SERVER['SERVER_PORT']) && !in_array((string) $_SERVER['SERVER_PORT'], ['80', '443'], true)) {
                $port = ':' . $_SERVER['SERVER_PORT'];
            }
            return rtrim('http://' . $lan . $port . rtrim($dir, '/'), '/');
        }
    }

    return rtrim(request_scheme() . '://' . $host . rtrim($dir, '/'), '/');
}

/**
 * Public URL of an entrance exam, e.g. .../exam.php?code=ABCDEFG
 */
function exam_url(string $code): string {
    return base_url() . '/exam.php?code=' . urlencode($code);
}

// Entrance exam constants
define('EXAM_QUESTION_COUNT', 15);
define('EXAM_CODE_LENGTH', 7);

// Department offered in this module (Engineering & Communication Technology)
define('DEPARTMENT', 'Engineering & Communication Technology (ECT)');

// Max students per section
define('SECTION_CAPACITY', 50);

// Forgot Password: XAMPP has no real mail server, so PHP mail() never
// actually delivers. When true (default), recovered login credentials are
// also displayed on screen so the student can log in.
// Set to false if real SMTP delivery is configured.
define('SHOW_CREDENTIALS_ONSCREEN', true);

// Enrollment email verification: XAMPP has no real mail server, so PHP mail()
// never actually delivers. When true (default), the verification link is also
// returned to the enrollment form and shown on screen so it can be tested.
// Set to false once real SMTP delivery is configured.
define('SHOW_VERIFICATION_LINK_ONSCREEN', true);

/**
 * Programs offered under the ECT department.
 * Returns [full_title => full_title].
 */
