<?php
/**
 * Fullbright College - Student Management System
 * Email sending + password regeneration helpers (Forgot Password).
 *
 * XAMPP does not ship a working mail server, so on this local setup
 * PHPMailer-style delivery via PHP mail() usually fails. The callers
 * therefore fall back to displaying the temporary password on screen.
 */

require_once __DIR__ . '/config.php';

/**
 * Sends an HTML email to a student.
 *
 * Delivery is attempted over SMTP when SMTP_HOST is configured (recommended
 * for production / containers, which have no mail transfer agent), and falls
 * back to PHP mail() for local XAMPP setups. Returns true on accepted delivery.
 *
 * SMTP environment variables:
 *   SMTP_HOST        e.g. smtp.gmail.com
 *   SMTP_PORT        default 587 (tls) or 465 (ssl)
 *   SMTP_USER        username (also used as the From address)
 *   SMTP_PASS        password / app password
 *   SMTP_ENCRYPTION  tls | ssl | none   (default: tls, or ssl when port 465)
 */
function send_student_email(string $to, string $toName, string $subject, string $htmlBody): bool {
    $body = "<!DOCTYPE html><html><body style=\"margin:0;padding:0;background:#f4f6fb;font-family:Segoe UI,Arial,sans-serif;\">"
          . "<div style=\"max-width:560px;margin:0 auto;padding:24px 18px;\">"
          . "<table role=\"presentation\" width=\"100%\" style=\"background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #dfe4ee;\">"
          . "<tr><td style=\"background:#0f2a5c;color:#ffffff;padding:18px 24px;\">"
          . "<strong style=\"font-size:18px;letter-spacing:0.04em;\">FULLBRIGHT COLLEGE</strong><br>"
          . "<span style=\"color:#e4b23f;font-size:12px;\">Student Management System</span>"
          . "</td></tr>"
          . "<tr><td style=\"padding:24px;color:#1c2333;font-size:14px;line-height:1.6;\">"
          . $htmlBody
          . "</td></tr>"
          . "<tr><td style=\"padding:14px 24px;background:#f4f6fb;color:#6b7486;font-size:11px;border-top:1px solid #dfe4ee;\">"
          . "This is an automated message from the Fullbright College Student Management System. Do not reply to this email.<br>"
          . "If you did not request this, please contact the Registrar's Office immediately.</td></tr>"
          . "</table></div></body></html>";

    if (env('SMTP_HOST') !== null) {
        return smtp_send($to, $toName, $subject, $body);
    }

    $headers = [
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8',
        'From'         => MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'X-Mailer'     => 'Fullbright SMS',
    ];
    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

/**
 * Minimal, dependency-free SMTP client (AUTH LOGIN, STARTTLS/SSL).
 * Returns true when the message is accepted by the server.
 */
function smtp_send(string $to, string $toName, string $subject, string $body): bool {
    $host = (string) env('SMTP_HOST');
    $port = (int) env('SMTP_PORT', '0');
    $enc  = strtolower((string) env('SMTP_ENCRYPTION', ''));

    if ($port <= 0) {
        $port = 465; // implicit TLS
    }
    if ($enc === '') {
        $enc = $port === 465 ? 'ssl' : 'tls';
    }

    $user = (string) env('SMTP_USER', '');
    $pass = (string) env('SMTP_PASS', '');
    $from = $user !== '' ? $user : MAIL_FROM;

    $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp  = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return false;
    }
    stream_set_timeout($fp, 20);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break; // last line of a multi-line reply
            }
        }
        return $data;
    };
    $send = function (string $cmd) use ($fp): void {
        fwrite($fp, $cmd . "\r\n");
    };
    $ok = function (string $reply) : bool {
        return $reply !== '' && (int) substr($reply, 0, 3) < 400;
    };

    if (!$ok($read())) { fclose($fp); return false; } // server greeting

    $hostname = parse_url(public_base_url(), PHP_URL_HOST) ?: 'localhost';
    $send("EHLO {$hostname}");
    $read();

    if ($enc === 'tls') {
        $send('STARTTLS');
        if (!$ok($read())) { fclose($fp); return false; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return false;
        }
        $send("EHLO {$hostname}");
        $read();
    }

    if ($user !== '') {
        $send('AUTH LOGIN');
        $read();
        $send(base64_encode($user)); $read();
        $send(base64_encode($pass));
        if (!$ok($read())) { fclose($fp); return false; }
    }

    $send('MAIL FROM:<' . $from . '>');    if (!$ok($read())) { fclose($fp); return false; }
    $send('RCPT TO:<' . $to . '>');        if (!$ok($read())) { fclose($fp); return false; }
    $send('DATA');                         if (!$ok($read())) { fclose($fp); return false; }

    $headers = 'From: ' . MAIL_FROM_NAME . ' <' . $from . '>' . "\r\n"
             . 'To: ' . ($toName !== '' ? $toName . ' <' . $to . '>' : $to) . "\r\n"
             . 'Subject: ' . $subject . "\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n"
             . 'Date: ' . date('r') . "\r\n";

    // Dot-stuff any line beginning with a period, then terminate with a lone dot.
    $data = preg_replace('/^\./m', '..', $body);
    fwrite($fp, $headers . "\r\n" . $data . "\r\n.\r\n");
    $accepted = $ok($read());

    $send('QUIT');
    fclose($fp);
    return $accepted;
}

/**
 * Regenerates a student's password for the "Forgot Password" flow.
 *
 *  - If the student never changed their password (no password_hash),
 *    the password is re-affirmed as their Student ID (stored anyway),
 *    so nothing is written to the database.
 *  - If they changed it, the old hash cannot be recovered, so a fresh
 *    random temporary password is generated and hashed.
 *
 * Returns ['password' => ..., 'source' => 'student_id'|'generated'].
 */
function regenerate_student_password(PDO $pdo, array $student): array {
    if (empty($student['password_hash'])) {
        return ['password' => $student['student_id'], 'source' => 'student_id'];
    }

    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $pw    = '';
    for ($i = 0; $i < 10; $i++) {
        $pw .= $chars[random_int(0, strlen($chars) - 1)];
    }

    $stmt = $pdo->prepare('UPDATE students SET password_hash = ? WHERE id = ?');
    $stmt->execute([password_hash($pw, PASSWORD_DEFAULT), $student['id']]);

    return ['password' => $pw, 'source' => 'generated'];
}

/**
 * Builds the HTML body of the password recovery email.
 */
function recovery_email_html(string $firstName, string $email, string $password): string {
    $escName = htmlspecialchars($firstName);
    $escEmail = htmlspecialchars($email);
    $escPw   = htmlspecialchars($password);
    return "<h2 style=\"margin:0 0 12px;color:#0f2a5c;\">Password Recovery</h2>"
         . "<p>Hello <strong>{$escName}</strong>,</p>"
         . "<p>Here are the login credentials for your Fullbright College Student account:</p>"
         . "<table role=\"presentation\" width=\"100%\" style=\"background:#f4f6fb;border:1px solid #dfe4ee;border-radius:10px;padding:14px;\">"
         . "<tr><td style=\"padding:6px 12px;color:#6b7486;font-size:12px;\">Username (email)</td>"
         . "<td style=\"padding:6px 12px;font-weight:700;\">{$escEmail}</td></tr>"
         . "<tr><td style=\"padding:6px 12px;color:#6b7486;font-size:12px;\">Password</td>"
         . "<td style=\"padding:6px 12px;\"><code style=\"background:#0f2a5c;color:#e4b23f;padding:3px 12px;border-radius:6px;font-weight:700;letter-spacing:0.06em;\">{$escPw}</code></td></tr>"
         . "</table>"
         . "<p style=\"margin-top:16px;\">Sign in at the Student Portal and change your password afterwards for security.<br>"
         . "If you believe this is an error, contact the Registrar's Office.</p>";
}

/**
 * Builds the HTML body of the enrollment email-verification message.
 * The wrapper (deep-blue Fullbright header + footer) is added by
 * send_student_email(); this is only the inner content.
 */
function verification_email_html(string $name, string $email, string $link): string {
    $escName  = htmlspecialchars($name !== '' ? $name : 'Student');
    $escEmail = htmlspecialchars($email);
    $escLink  = htmlspecialchars($link);
    return "<h2 style=\"margin:0 0 12px;color:#0f2a5c;\">Verify your email address</h2>"
         . "<p>Hello <strong>{$escName}</strong>,</p>"
         . "<p>Thank you for starting your enrollment at <strong>Fullbright College</strong>. "
         . "Please confirm that <strong>{$escEmail}</strong> is your email address by clicking the button below.</p>"
         . "<p style=\"text-align:center;margin:26px 0;\">"
         . "<a href=\"{$escLink}\" style=\"display:inline-block;background:#0f2a5c;color:#ffffff;"
         . "text-decoration:none;font-weight:700;padding:13px 30px;border-radius:8px;letter-spacing:0.02em;\">"
         . "Verify My Email</a></p>"
         . "<p style=\"font-size:12px;color:#6b7486;\">If the button does not work, copy and paste this link into your browser:<br>"
         . "<a href=\"{$escLink}\" style=\"color:#1f4aa0;word-break:break-all;\">{$escLink}</a></p>"
         . "<p style=\"margin-top:16px;\">This link is valid for <strong>24 hours</strong> and can be used only once. "
         . "You will not be able to continue your enrollment until your email is verified.</p>"
         . "<p style=\"color:#6b7486;font-size:12px;\">If you did not start an enrollment application, you can safely ignore this email.</p>";
}