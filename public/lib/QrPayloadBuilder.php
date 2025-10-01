<?php

declare(strict_types=1);

class QrPayloadBuilder
{
    /**
     * @param array<string, mixed> $input
     * @return array{data: string, meta: array<string, mixed>}
     */
    public static function build(string $type, array $input): array
    {
        return match ($type) {
            'wifi' => self::buildWifiPayload($input),
            'text' => self::buildTextPayload($input),
            'email' => self::buildEmailPayload($input),
            'sms' => self::buildSmsPayload($input),
            'geo' => self::buildGeoPayload($input),
            default => self::buildUrlPayload($input),
        };
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function buildWifiPayload(array $input): array
    {
        $ssid = trim((string)($input['wifi_ssid'] ?? ''));
        if ($ssid === '') {
            throw new InvalidArgumentException('Bitte gib eine SSID für das WLAN ein.');
        }

        $encryption = strtoupper((string)($input['wifi_encryption'] ?? 'WPA'));
        if (!in_array($encryption, ['WPA', 'WPA2', 'WEP', 'NOPASS'], true)) {
            $encryption = 'WPA';
        }
        if ($encryption === 'NOPASS') {
            $encryption = 'nopass';
        }

        $password = (string)($input['wifi_password'] ?? '');
        if ($encryption !== 'nopass' && $password === '') {
            throw new InvalidArgumentException('Bitte gib ein WLAN-Passwort ein oder wähle "Ohne Passwort".');
        }

        $hidden = !empty($input['wifi_hidden']);

        $parts = [
            'WIFI:',
            'T:' . $encryption . ';',
            'S:' . self::escapeWifiValue($ssid) . ';',
        ];

        if ($encryption !== 'nopass') {
            $parts[] = 'P:' . self::escapeWifiValue($password) . ';';
        }

        if ($hidden) {
            $parts[] = 'H:true;';
        }

        $parts[] = ';';

        return [
            'data' => implode('', $parts),
            'meta' => [
                'type' => 'wifi',
                'ssid' => $ssid,
                'encryption' => $encryption,
                'hidden' => $hidden,
                'password_set' => $encryption !== 'nopass',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function buildUrlPayload(array $input): array
    {
        $url = trim((string)($input['url'] ?? ''));
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Bitte gib eine gültige URL ein.');
        }

        return [
            'data' => $url,
            'meta' => [
                'type' => 'url',
                'url' => $url,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function buildTextPayload(array $input): array
    {
        $text = trim((string)($input['text'] ?? ''));
        if ($text === '') {
            throw new InvalidArgumentException('Bitte gib einen Text ein.');
        }

        return [
            'data' => $text,
            'meta' => [
                'type' => 'text',
                'preview' => mb_substr($text, 0, 120),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function buildEmailPayload(array $input): array
    {
        $address = trim((string)($input['email_address'] ?? ''));
        if ($address === '' || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Bitte gib eine gültige E-Mail-Adresse ein.');
        }

        $subject = trim((string)($input['email_subject'] ?? ''));
        $body = trim((string)($input['email_body'] ?? ''));

        $query = [];
        if ($subject !== '') {
            $query['subject'] = $subject;
        }
        if ($body !== '') {
            $query['body'] = $body;
        }

        $mailto = 'mailto:' . $address;
        if ($query !== []) {
            $mailto .= '?' . http_build_query($query);
        }

        return [
            'data' => $mailto,
            'meta' => [
                'type' => 'email',
                'address' => $address,
                'has_subject' => $subject !== '',
                'has_body' => $body !== '',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function buildSmsPayload(array $input): array
    {
        $number = trim((string)($input['sms_number'] ?? ''));
        if ($number === '') {
            throw new InvalidArgumentException('Bitte gib eine Telefonnummer für die SMS ein.');
        }

        $message = trim((string)($input['sms_message'] ?? ''));
        $payload = 'SMSTO:' . $number;
        if ($message !== '') {
            $payload .= ':' . $message;
        }

        return [
            'data' => $payload,
            'meta' => [
                'type' => 'sms',
                'number' => $number,
                'has_message' => $message !== '',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function buildGeoPayload(array $input): array
    {
        $lat = trim((string)($input['geo_lat'] ?? ''));
        $lng = trim((string)($input['geo_lng'] ?? ''));
        if ($lat === '' || $lng === '') {
            throw new InvalidArgumentException('Bitte gib sowohl Breiten- als auch Längengrad ein.');
        }

        if (!is_numeric($lat) || !is_numeric($lng)) {
            throw new InvalidArgumentException('Breiten- und Längengrad müssen numerisch sein.');
        }

        $latFloat = (float)$lat;
        $lngFloat = (float)$lng;
        if ($latFloat < -90 || $latFloat > 90 || $lngFloat < -180 || $lngFloat > 180) {
            throw new InvalidArgumentException('Die Koordinaten liegen außerhalb des gültigen Bereichs.');
        }

        return [
            'data' => sprintf('geo:%s,%s', self::normalizeCoordinate($lat), self::normalizeCoordinate($lng)),
            'meta' => [
                'type' => 'geo',
                'lat' => $latFloat,
                'lng' => $lngFloat,
            ],
        ];
    }

    private static function escapeWifiValue(string $value): string
    {
        return str_replace(['\\', ';', ',', ':'], ['\\\\', '\\;', '\\,', '\\:'], $value);
    }

    private static function normalizeCoordinate(string $value): string
    {
        $normalized = rtrim($value, '0');
        $normalized = rtrim($normalized, '.');
        return $normalized === '' ? '0' : $normalized;
    }
}
