<?php

declare(strict_types=1);

class RemoteQrService
{
    private const DEFAULT_ENDPOINT = 'https://api.qrserver.com/v1/create-qr-code/';

    public function __construct(private readonly string $endpoint = self::DEFAULT_ENDPOINT)
    {
    }

    public function generate(string $data, int $size, int $margin, string $errorCorrection): string
    {
        $size = max(100, min($size, 600));
        $margin = max(0, min($margin, 25));
        $errorCorrection = strtoupper($errorCorrection);
        if (!in_array($errorCorrection, ['L', 'M', 'Q', 'H'], true)) {
            $errorCorrection = 'M';
        }

        $params = [
            'data' => $data,
            'size' => sprintf('%dx%d', $size, $size),
            'margin' => $margin,
            'ecc' => $errorCorrection,
        ];

        $url = $this->endpoint . '?' . http_build_query($params);
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
            ],
        ]);

        $imageData = @file_get_contents($url, false, $context);
        if ($imageData === false) {
            throw new RuntimeException('Der QR-Code konnte nicht vom externen Dienst geladen werden.');
        }

        return 'data:image/png;base64,' . base64_encode($imageData);
    }
}
