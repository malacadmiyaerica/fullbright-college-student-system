<?php
/**
 * Fullbright College - Student Management System
 * Configuration
 *
 * Sensitive / environment-specific values are read from the environment
 * (Vercel-style deployment platforms, Docker, etc.) and fall back to the
 * stock XAMPP defaults for local development. Never commit real credentials.
 */

if (!function_exists('env')) {
    /**
     * Reads an environment variable, trimming surrounding whitespace.
     * Returns $default when the variable is unset or empty.
     */
    function env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        $value = trim($value);
        return $value === '' ? $default : $value;
    }
}

if (!function_exists('env_bool')) {
    /**
     * Reads a boolean environment variable ("1", "true", "on", "yes" => true).
     */
    function env_bool(string $key, bool $default = false): bool
    {
        $value = env($key);
        if ($value === null) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

/**
 * Application environment: "local" (default) or "production".
 * In production, on-screen credential/link fallbacks default to OFF.
 */
define('APP_ENV', env('APP_ENV', 'local'));
define('IS_PRODUCTION', APP_ENV === 'production');

/* ------------------------------------------------------------------
 * Database
 *
 * Configuration is resolved in this order:
 *   1. A single connection URL  (DATABASE_URL or MYSQL_URL), as provided
 *      by Railway / Render / Fly.io / Heroku, e.g.
 *        mysql://user:pass@host:3306/dbname
 *   2. Individual DB_* variables (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS).
 *   3. Local XAMPP defaults.
 * ------------------------------------------------------------------ */
$__db_url = env('DATABASE_URL')
    ?? env('MYSQL_URL')
    ?? env('MYSQL_PUBLIC_URL')
    ?? '';

$__db = [
    'host' => env('DB_HOST', 'localhost'),
    'port' => env('DB_PORT', '3306'),
    'name' => env('DB_NAME', 'fullbright_college'),
    'user' => env('DB_USER', 'root'),
    'pass' => env('DB_PASS', ''),
];

if ($__db_url !== '') {
    $__parts = parse_url($__db_url);
    if (is_array($__parts) && !empty($__parts['host'])) {
        $__db['host'] = $__parts['host'];
        $__db['port'] = (string) ($__parts['port'] ?? $__db['port']);
        $__db['user'] = isset($__parts['user']) ? urldecode($__parts['user']) : $__db['user'];
        $__db['pass'] = isset($__parts['pass']) ? urldecode($__parts['pass']) : $__db['pass'];
        if (!empty($__parts['path'])) {
            $__db['name'] = ltrim($__parts['path'], '/');
        }
    }
}

define('DB_HOST', $__db['host']);
define('DB_PORT', (int) $__db['port']);
define('DB_NAME', $__db['name']);
define('DB_USER', $__db['user']);
define('DB_PASS', $__db['pass']);
// Some managed MySQL providers require TLS on public endpoints.
define('DB_SSL', env_bool('DB_SSL', false));
define('DB_SSL_CA', env('DB_SSL_CA', ''));

unset($__db_url, $__db, $__parts);

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
// actually delivers. When true, recovered login credentials are also displayed
// on screen so the student can log in. Defaults to ON locally and OFF in
// production (where real SMTP delivery should be configured).
// Override with the SHOW_CREDENTIALS_ONSCREEN environment variable.
define('SHOW_CREDENTIALS_ONSCREEN', env_bool('SHOW_CREDENTIALS_ONSCREEN', !IS_PRODUCTION));

// Enrollment email verification: same reasoning as above. When true, the
// verification link is also returned to the enrollment form and shown on
// screen so it can be tested without a mail server.
// Override with the SHOW_VERIFICATION_LINK_ONSCREEN environment variable.
define('SHOW_VERIFICATION_LINK_ONSCREEN', env_bool('SHOW_VERIFICATION_LINK_ONSCREEN', !IS_PRODUCTION));

// Email delivery. When SMTP_HOST is set the app sends mail over SMTP;
// otherwise it falls back to PHP mail(). See includes/mailer.php.
define('MAIL_FROM', env('MAIL_FROM', 'noreply@fullbrightcollege.example'));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Fullbright College Student Portal'));

/**
 * Programs offered under the ECT department.
 * Returns [full_title => full_title].
 */
