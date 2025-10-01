# QR1

QR Code Generator

## Projektüberblick

Diese Anwendung stellt einen QR-Code-Generator auf Basis von PHP bereit. Unterstützt werden WLAN-, URL-, Text-, E-Mail-, SMS- und Geo-QR-Codes. Die Bilder werden mit Hilfe eines externen QR-Dienstes erzeugt und direkt als Base64 in der Seite angezeigt. Optional lassen sich erzeugte Codes in einer MySQL-Datenbank protokollieren.

## Voraussetzungen

- PHP 8.1 oder höher
- Webserver wie Apache oder Nginx (oder PHP Built-in Server)
- Internetzugang für den Abruf der QR-Code-Grafiken über die API `https://api.qrserver.com/`
- Optional: MySQL 5.7+/8.0+ zur Speicherung von QR-Anfragen

## Installation

1. Projektdateien auf den Webserver kopieren (z. B. nach `/var/www/html/qr1`).
2. Das Verzeichnis `public/` als Document Root konfigurieren.
3. (Optional) Für Datenbankspeicherung `public/config.example.php` nach `public/config.php` kopieren und Zugangsdaten hinterlegen:

   ```bash
   cp public/config.example.php public/config.php
   ```

4. (Optional) MySQL-Tabelle anlegen:

   * `public/config.example.php` nach `public/config.php` kopieren, Zugangsdaten eintragen und `db_enabled` auf `true` setzen.
   * `install.php` im Browser aufrufen (z. B. `http://localhost:8000/install.php`) und auf **Installation starten** klicken.
   * Alternativ kann die Migration manuell per SQL ausgeführt werden:

     ```sql
     SOURCE migrations/qr_requests.sql;
     ```

## Nutzung

1. Seite im Browser öffnen (z. B. `http://localhost:8000`).
2. QR-Typ wählen und Formular ausfüllen.
3. Auf "QR-Code erstellen" klicken. Das Ergebnis wird inklusive Downloadlink und QR-Inhalt angezeigt.

### QR-Code Typen

- **Link / URL** – validiert die Eingabe per `FILTER_VALIDATE_URL`.
- **WLAN** – unterstützt WPA/WPA2, WEP oder offene Netze. Passwörter werden nicht in der Datenbank gespeichert.
- **Text** – beliebiger Text, ideal für Notizen.
- **E-Mail** – erstellt ein `mailto:`-Schema inkl. optionalem Betreff und Nachricht.
- **SMS** – generiert ein `SMSTO:`-Schema.
- **Geo-Standort** – erzeugt `geo:lat,lng`-Verweise.

## Sicherheitshinweise

- Bei aktivierter Datenbankspeicherung werden nur Metadaten ohne Passwörter abgelegt.
- Der externe QR-Dienst erhält den Klartextinhalt. Bei sensiblen Daten sollte ein lokaler QR-Generator bevorzugt werden.

## Entwicklung

- Assets befinden sich unter `public/assets/`.
- Formularlogik und Rendering stehen in `public/index.php`.
- QR-Datenaufbereitung erfolgt über `public/lib/QrPayloadBuilder.php`.
- Der QR-Code wird aktuell über `public/lib/RemoteQrService.php` von `api.qrserver.com` abgefragt. Bei Bedarf kann hier eine lokale Bibliothek integriert werden.

## Lokaler Testserver

Mit PHP-CLI lässt sich ein Testserver starten:

```bash
php -S localhost:8000 -t public/
```

## Lizenz

Dieses Projekt dient als Beispielimplementierung und verwendet den externen Dienst `api.qrserver.com` für die QR-Code-Erstellung. Bitte prüfe die Nutzungsbedingungen des Dienstes vor produktivem Einsatz.
