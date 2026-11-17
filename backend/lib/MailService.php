<?php
declare(strict_types=1);

/**
 * SSMS Mail Service
 * Sends email using configured SMTP settings (settings group 'smtp') with fallback
 * to PHP mail(). Designed to drop in PHPMailer via Composer later.
 */
final class MailService
{
    public static function send(string $to, string $subject, string $body, array $headers = []): bool
    {
        $schoolId = school_id();
        $host = (string)Setting::get($schoolId, 'smtp.smtp_host', '');
        $port = (int)Setting::get($schoolId, 'smtp.smtp_port', 587);
        $user = (string)Setting::get($schoolId, 'smtp.smtp_username', '');
        $pass = (string)Setting::get($schoolId, 'smtp.smtp_password', '');
        $enc  = (string)Setting::get($schoolId, 'smtp.smtp_encryption', 'tls');
        $fromName = (string)Setting::get($schoolId, 'smtp.smtp_from_name', APP_NAME);
        $fromAddr = (string)Setting::get($schoolId, 'smtp.smtp_from_address', 'no-reply@localhost');
        $senderName = (string)Setting::get($schoolId, 'communication.sms_sender_name', APP_NAME);

        $h = [];
        $h[] = 'From: '.$fromName.' <'.$fromAddr.'>';
        $h[] = 'Reply-To: '.$fromAddr;
        $h[] = 'X-Mailer: SSMS/1.0';
        $h[] = 'MIME-Version: 1.0';
        $h[] = 'Content-Type: text/html; charset=UTF-8';
        foreach ($headers as $k => $v) { $h[] = $k.': '.$v; }

        if ($host !== '' && function_exists('fsockopen')) {
            return self::sendSmtp($to, $subject, $body, $host, $port, $user, $pass, $enc, $fromName, $fromAddr, implode("\r\n", $h));
        }
        return @mail($to, $subject, $body, implode("\r\n", $h));
    }

    private static function sendSmtp(string $to, string $subject, string $body, string $host, int $port, string $user, string $pass, string $enc, string $fromName, string $fromAddr, string $headers): bool
    {
        $smtp = @fsockopen(
            ($enc === 'ssl' ? 'ssl://' : '') . $host,
            $port,
            $errno, $errstr, 10
        );
        if (!$smtp) { return false; }
        stream_set_timeout($smtp, 10);
        $cmd = function ($s, $c) use (&$smtp) { fwrite($smtp, $c."\r\n"); usleep(50000); return fgets($smtp, 512); };
        $cmd($smtp, 'EHLO '.$host);
        if ($enc === 'tls') { $cmd($smtp, 'STARTTLS'); }
        if ($user !== '') {
            $cmd($smtp, 'AUTH LOGIN');
            $cmd($smtp, base64_encode($user));
            $cmd($smtp, base64_encode($pass));
        }
        $cmd($smtp, 'MAIL FROM: <'.$fromAddr.'>');
        $cmd($smtp, 'RCPT TO: <'.$to.'>');
        $cmd($smtp, 'DATA');
        $msg = "From: ".$fromName." <".$fromAddr.">\r\nTo: ".$to."\r\nSubject: ".$subject."\r\n".$headers."\r\n\r\n".$body."\r\n.";
        fwrite($smtp, $msg."\r\n");
        fgets($smtp, 512);
        $cmd($smtp, 'QUIT');
        fclose($smtp);
        return true;
    }

    public static function notification(string $to, string $subject, string $body): bool
    {
        return self::send($to, $subject, $body);
    }
}
