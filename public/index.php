<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/lib/RemoteQrService.php';
require_once __DIR__ . '/lib/QrPayloadBuilder.php';
require_once __DIR__ . '/lib/DatabaseLogger.php';

$config = [
    'db_enabled' => false,
    'db_dsn' => '',
    'db_user' => '',
    'db_password' => '',
];

$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    $userConfig = require $configFile;
    if (is_array($userConfig)) {
        $config = array_replace($config, $userConfig);
    }
}

$pdo = null;
$dbLogger = null;
$dbError = null;

if (!empty($config['db_enabled'])) {
    try {
        $pdo = new PDO((string)$config['db_dsn'], (string)$config['db_user'], (string)$config['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $dbLogger = new DatabaseLogger($pdo);
    } catch (Throwable $exception) {
        $dbError = 'Die Datenbankverbindung konnte nicht aufgebaut werden: ' . $exception->getMessage();
    }
}

$errors = [];
$qrImage = null;
$payload = null;
$meta = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = (string)($_POST['qr_type'] ?? 'url');

    try {
        $payload = QrPayloadBuilder::build($type, $_POST);
        $meta = $payload['meta'];

        $size = filter_var($_POST['size'] ?? 300, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 100, 'max_range' => 600],
        ]);
        if ($size === false) {
            $size = 300;
        }

        $margin = filter_var($_POST['margin'] ?? 2, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 25],
        ]);
        if ($margin === false) {
            $margin = 2;
        }

        $ecc = (string)($_POST['ecc'] ?? 'M');

        $qrService = new RemoteQrService();
        $qrImage = $qrService->generate($payload['data'], $size, $margin, $ecc);

        if ($dbLogger !== null) {
            try {
                $dbLogger->log($meta['type'] ?? $type, $meta);
            } catch (Throwable $exception) {
                $errors[] = 'Der QR-Code wurde erstellt, konnte aber nicht in die Datenbank geschrieben werden: ' . $exception->getMessage();
            }
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

function old(string $key, mixed $default = ''): string
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $value = $_POST[$key] ?? $default;
        if (is_array($value)) {
            return '';
        }
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    return htmlspecialchars((string)$default, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function checked(string $key): string
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return empty($_POST[$key]) ? '' : 'checked';
    }

    return '';
}

$selectedType = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string)($_POST['qr_type'] ?? 'url') : 'url';

?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP QR-Code Generator</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="page-header">
    <div class="container">
        <h1>QR-Code Generator</h1>
        <p>Erstelle QR-Codes für WLAN, Links, Text, E-Mails, SMS und Geo-Standorte.</p>
    </div>
</header>
<main class="container">
    <?php if ($dbError !== null): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($dbError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="generator">
        <form method="post" class="form" novalidate>
            <div class="form-group">
                <label for="qr_type">QR-Code Typ</label>
                <select id="qr_type" name="qr_type">
                    <option value="url" <?= $selectedType === 'url' ? 'selected' : '' ?>>Link / URL</option>
                    <option value="wifi" <?= $selectedType === 'wifi' ? 'selected' : '' ?>>WLAN</option>
                    <option value="text" <?= $selectedType === 'text' ? 'selected' : '' ?>>Freier Text</option>
                    <option value="email" <?= $selectedType === 'email' ? 'selected' : '' ?>>E-Mail</option>
                    <option value="sms" <?= $selectedType === 'sms' ? 'selected' : '' ?>>SMS</option>
                    <option value="geo" <?= $selectedType === 'geo' ? 'selected' : '' ?>>Geo-Standort</option>
                </select>
            </div>

            <div class="form-variant" data-type="url" <?= $selectedType === 'url' ? '' : 'hidden' ?> >
                <div class="form-group">
                    <label for="url">URL</label>
                    <input type="url" id="url" name="url" placeholder="https://example.com" value="<?= old('url') ?>" required>
                </div>
            </div>

            <div class="form-variant" data-type="wifi" <?= $selectedType === 'wifi' ? '' : 'hidden' ?> >
                <div class="form-group">
                    <label for="wifi_ssid">SSID</label>
                    <input type="text" id="wifi_ssid" name="wifi_ssid" value="<?= old('wifi_ssid') ?>" required>
                </div>
                <div class="form-group">
                    <label for="wifi_encryption">Verschlüsselung</label>
                    <select id="wifi_encryption" name="wifi_encryption">
                        <?php
                        $wifiOptions = [
                            'WPA' => 'WPA/WPA2',
                            'WPA2' => 'WPA2',
                            'WEP' => 'WEP',
                            'NOPASS' => 'Ohne Passwort',
                        ];
                        $selectedEncryption = strtoupper((string)($_POST['wifi_encryption'] ?? 'WPA'));
                        foreach ($wifiOptions as $value => $label):
                        ?>
                            <option value="<?= $value ?>" <?= $selectedEncryption === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="wifi_password">Passwort</label>
                    <input type="text" id="wifi_password" name="wifi_password" value="<?= old('wifi_password') ?>" placeholder="Nur bei verschlüsselten Netzwerken erforderlich">
                </div>
                <div class="form-group form-group-inline">
                    <input type="checkbox" id="wifi_hidden" name="wifi_hidden" value="1" <?= checked('wifi_hidden') ?>>
                    <label for="wifi_hidden">SSID versteckt</label>
                </div>
            </div>

            <div class="form-variant" data-type="text" <?= $selectedType === 'text' ? '' : 'hidden' ?> >
                <div class="form-group">
                    <label for="text">Text</label>
                    <textarea id="text" name="text" rows="4" required><?= old('text') ?></textarea>
                </div>
            </div>

            <div class="form-variant" data-type="email" <?= $selectedType === 'email' ? '' : 'hidden' ?> >
                <div class="form-group">
                    <label for="email_address">E-Mail-Adresse</label>
                    <input type="email" id="email_address" name="email_address" value="<?= old('email_address') ?>" required>
                </div>
                <div class="form-group">
                    <label for="email_subject">Betreff (optional)</label>
                    <input type="text" id="email_subject" name="email_subject" value="<?= old('email_subject') ?>">
                </div>
                <div class="form-group">
                    <label for="email_body">Nachricht (optional)</label>
                    <textarea id="email_body" name="email_body" rows="3"><?= old('email_body') ?></textarea>
                </div>
            </div>

            <div class="form-variant" data-type="sms" <?= $selectedType === 'sms' ? '' : 'hidden' ?> >
                <div class="form-group">
                    <label for="sms_number">Telefonnummer</label>
                    <input type="text" id="sms_number" name="sms_number" value="<?= old('sms_number') ?>" required>
                </div>
                <div class="form-group">
                    <label for="sms_message">Nachricht (optional)</label>
                    <textarea id="sms_message" name="sms_message" rows="3"><?= old('sms_message') ?></textarea>
                </div>
            </div>

            <div class="form-variant" data-type="geo" <?= $selectedType === 'geo' ? '' : 'hidden' ?> >
                <div class="form-group">
                    <label for="geo_lat">Breitengrad</label>
                    <input type="text" id="geo_lat" name="geo_lat" value="<?= old('geo_lat') ?>" placeholder="z. B. 48.137154" required>
                </div>
                <div class="form-group">
                    <label for="geo_lng">Längengrad</label>
                    <input type="text" id="geo_lng" name="geo_lng" value="<?= old('geo_lng') ?>" placeholder="z. B. 11.576124" required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="size">Größe (px)</label>
                    <input type="number" id="size" name="size" min="100" max="600" value="<?= old('size', '300') ?>">
                </div>
                <div class="form-group">
                    <label for="margin">Rand</label>
                    <input type="number" id="margin" name="margin" min="0" max="25" value="<?= old('margin', '2') ?>">
                </div>
                <div class="form-group">
                    <label for="ecc">Fehlerkorrektur</label>
                    <?php
                    $eccOptions = ['L' => 'L (7%)', 'M' => 'M (15%)', 'Q' => 'Q (25%)', 'H' => 'H (30%)'];
                    $selectedEcc = strtoupper((string)($_POST['ecc'] ?? 'M'));
                    ?>
                    <select id="ecc" name="ecc">
                        <?php foreach ($eccOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $selectedEcc === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">QR-Code erstellen</button>
            </div>
        </form>

        <?php if ($qrImage !== null && $payload !== null): ?>
            <div class="result">
                <h2>Ergebnis</h2>
                <div class="qr-preview">
                    <img src="<?= $qrImage ?>" alt="Generierter QR-Code" loading="lazy">
                </div>
                <div class="result-actions">
                    <a class="btn" href="<?= $qrImage ?>" download="qr-code.png">Download</a>
                </div>
                <div class="qr-data">
                    <h3>QR-Inhalt</h3>
                    <textarea readonly rows="4"><?= htmlspecialchars($payload['data'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="info">
        <h2>Unterstützte QR-Code Typen</h2>
        <ul>
            <li><strong>WLAN:</strong> Verbindet Smartphones automatisch mit deinem Netzwerk.</li>
            <li><strong>Link / URL:</strong> Öffnet Webseiten oder Dateien.</li>
            <li><strong>Freier Text:</strong> Ideal für kurze Nachrichten oder Notizen.</li>
            <li><strong>E-Mail:</strong> Öffnet den Mail-Client mit vor ausgefüllten Feldern.</li>
            <li><strong>SMS:</strong> Startet eine SMS mit vordefiniertem Empfänger und Text.</li>
            <li><strong>Geo-Standort:</strong> Öffnet Karten-Apps mit festem Standort.</li>
        </ul>
    </section>
</main>

<footer class="page-footer">
    <div class="container">
        <p>Erstellt mit PHP. Für weitere Formate kann der Code erweitert werden.</p>
    </div>
</footer>

<script src="assets/app.js"></script>
</body>
</html>
