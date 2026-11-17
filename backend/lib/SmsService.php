<?php
declare(strict_types=1);

/**
 * SSMS SMS Service
 * Sends SMS via configured gateway (settings group 'sms') using a generic HTTP POST.
 * Providers (Africa's Talking, Twilio, local gateway) can be swapped by changing
 * the gateway URL and payload keys in Settings.
 */
final class SmsService
{
    public static function send(string $to, string $message): array
    {
        $schoolId = school_id();
        $provider = (string)Setting::get($schoolId, 'sms.sms_provider', '');
        $apiKey   = (string)Setting::get($schoolId, 'sms.sms_api_key', '');
        $secret   = (string)Setting::get($schoolId, 'sms.sms_api_secret', '');
        $sender   = (string)Setting::get($schoolId, 'sms.sms_sender_id', 'SSMS');
        $url      = (string)Setting::get($schoolId, 'sms.sms_gateway_url', '');
        $lang     = (string)Setting::get($schoolId, 'sms.sms_default_language', 'en');

        if ($url === '' || $provider === '') {
            return ['ok' => false, 'error' => 'SMS gateway not configured in Settings.'];
        }

        $payload = [
            'to' => $to,
            'message' => $message,
            'sender' => $sender,
            'username' => $apiKey,
            'password' => $secret,
            'apikey' => $apiKey,
            'from' => $sender,
            'text' => $message,
            'language' => $lang,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            return ['ok' => false, 'error' => $err, 'http_code' => $httpCode];
        }
        return ['ok' => $httpCode >= 200 && $httpCode < 300, 'response' => $response, 'http_code' => $httpCode];
    }

    public static function sendBulk(array $recipients, string $message): array
    {
        $results = [];
        foreach ($recipients as $r) {
            $results[$r['phone']] = self::send($r['phone'], $message);
        }
        return $results;
    }
}
