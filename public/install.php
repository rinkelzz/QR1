<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

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

$migrationFile = __DIR__ . '/../migrations/qr_requests.sql';
$messages = [];
$errors = [];

if (!file_exists($migrationFile)) {
    $errors[] = 'Die Migrationsdatei wurde nicht gefunden: ' . basename($migrationFile);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors === []) {
    if (empty($config['db_enabled'])) {
        $errors[] = 'Aktiviere die Datenbankverbindung in der config.php ("db_enabled" => true), bevor du die Installation startest.';
    } else {
        try {
            $pdo = new PDO((string)$config['db_dsn'], (string)$config['db_user'], (string)$config['db_password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $sql = file_get_contents($migrationFile);
            if ($sql === false) {
                throw new RuntimeException('Die Migrationsdatei konnte nicht gelesen werden.');
            }

            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $statement) {
                if ($statement === '') {
                    continue;
                }
                $pdo->exec($statement);
            }

            $messages[] = 'Installation erfolgreich abgeschlossen. Die Tabelle "qr_requests" ist nun verfügbar.';
        } catch (Throwable $exception) {
            $errors[] = 'Die Installation ist fehlgeschlagen: ' . $exception->getMessage();
        }
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$hasConfig = file_exists($configFile);
$dsn = (string)$config['db_dsn'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - QR-Code Generator</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="page-header">
    <div class="container">
        <h1>Datenbank-Installation</h1>
        <p>Lege die notwendigen Tabellen für das QR-Code Logging an.</p>
    </div>
</header>
<main class="container">
    <?php if ($messages !== []): ?>
        <div class="alert alert-success">
            <ul>
                <?php foreach ($messages as $message): ?>
                    <li><?= h($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="generator">
        <div class="form">
            <h2>Voraussetzungen</h2>
            <ul>
                <li>Konfiguration vorhanden: <strong><?= $hasConfig ? 'Ja' : 'Nein' ?></strong></li>
                <li>Datenbank aktiviert: <strong><?= !empty($config['db_enabled']) ? 'Ja' : 'Nein' ?></strong></li>
                <li>DSN: <code><?= h($dsn !== '' ? $dsn : 'nicht gesetzt') ?></code></li>
            </ul>

            <form method="post">
                <p>
                    Mit einem Klick auf <strong>Installation starten</strong> wird die Datei
                    <code><?= h(basename($migrationFile)) ?></code> ausgeführt und legt die benötigte Tabelle an.
                </p>
                <div class="form-actions">
                    <button type="submit" class="btn">Installation starten</button>
                </div>
            </form>
        </div>
    </section>
</main>
</body>
</html>
