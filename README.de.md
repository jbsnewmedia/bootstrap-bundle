# Bootstrap Bundle

[![Packagist Version](https://img.shields.io/packagist/v/jbsnewmedia/bootstrap-bundle)](https://packagist.org/packages/jbsnewmedia/bootstrap-bundle)
[![Packagist Downloads](https://img.shields.io/packagist/dt/jbsnewmedia/bootstrap-bundle)](https://packagist.org/packages/jbsnewmedia/bootstrap-bundle)
[![PHP Version Require](https://img.shields.io/packagist/php-v/jbsnewmedia/bootstrap-bundle)](https://packagist.org/packages/jbsnewmedia/bootstrap-bundle)
[![Symfony Version](https://img.shields.io/badge/symfony-%5E7.4-673ab7?logo=symfony)](https://symfony.com)
[![License](https://img.shields.io/packagist/l/jbsnewmedia/bootstrap-bundle)](https://packagist.org/packages/jbsnewmedia/bootstrap-bundle)
[![Tests](https://github.com/jbsnewmedia/bootstrap-bundle/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/jbsnewmedia/bootstrap-bundle/actions/workflows/tests.yml)
[![PHP CS Fixer](https://img.shields.io/badge/php--cs--fixer-checked-brightgreen)](https://github.com/jbsnewmedia/bootstrap-bundle/actions/workflows/tests.yml)
[![PHPStan](https://img.shields.io/badge/phpstan-analysed-brightgreen)](https://github.com/jbsnewmedia/bootstrap-bundle/actions/workflows/tests.yml)
[![Rector](https://img.shields.io/badge/rector-checked-brightgreen)](https://github.com/jbsnewmedia/bootstrap-bundle/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/jbsnewmedia/bootstrap-bundle/branch/main/graph/badge.svg)](https://codecov.io/gh/jbsnewmedia/bootstrap-bundle)

Ein leichtgewichtiges Symfony-Bundle, das Dir beim Gerüstbau und der Kompilierung von Bootstrap-SCSS mit [scssphp](https://github.com/scssphp/scssphp) hilft. Es enthält drei Konsolenbefehle:

- `bootstrap:init` — erstellt SCSS-Einstiegsdateien unter `assets/scss/`.
- `bootstrap:compile` — kompiliert SCSS zu CSS (mit sinnvollen Standardwerten und vendor-bewussten Importpfaden).
- `bootstrap:purge` — bereinigt kompiliertes Bootstrap-CSS durch Scannen Deiner Templates.

---

## 🚀 Funktionen

- Sofort einsatzbereite SCSS-Einträge (hell und dunkel)
- SCSS → CSS via scssphp (reines PHP, kein Node erforderlich)
- Schreibt lesbares und minifiziertes CSS in einem Durchlauf
- Optionale Source-Maps für jede Ausgabe (`--source-map`)
- Include-Pfade für `vendor/twbs/bootstrap/scss` direkt vorkonfiguriert
- Bereinigung von Bootstrap-CSS basierend auf Deinen Templates (`bootstrap:purge`)
- Saubere Standardwerte und sinnvolle Pfade

---

## ⚙️ Anforderungen

- PHP 8.2 oder höher
- Symfony 6.4 oder 7.x (framework-bundle, console)
- Composer
- Abhängigkeiten:
  - `twbs/bootstrap` (>= 5.3)
  - `scssphp/scssphp` (^2.0)
  - `jbsnewmedia/css-purger` (^1.0)

Hinweis: Dies ist ein reguläres Symfony-Bundle und erwartet einen Symfony-Kernel (es wird automatisch registriert).

---

## 📦 Installation

Installation über Composer:

```bash
composer require jbsnewmedia/bootstrap-bundle
```

Falls noch nicht vorhanden, installiert Composer die erforderlichen Pakete (`twbs/bootstrap`, `scssphp/scssphp`).

---

## 📋 Verwendung

### 1) SCSS-Einträge erstellen

Erstelle die Standard-SCSS-Einstiegsdateien unter `assets/scss/`:

```bash
php bin/console bootstrap:init
# Vorschau ohne Dateien zu schreiben
php bin/console bootstrap:init --dry-run
# Vorhandene Dateien überschreiben
php bin/console bootstrap:init --force
```

Erstellte Dateien:

- `assets/scss/bootstrap5-custom.scss`
- `assets/scss/bootstrap5-custom-dark.scss`

Beide Einträge importieren Bootstrap nach Deinen Variablen-Overrides in der richtigen Reihenfolge.

### 2) Kompilieren SCSS → CSS

Kompilieren mit sinnvollen Standardwerten:

```bash
php bin/console bootstrap:compile
```

Standardwerte:

- Input: `assets/scss/bootstrap5-custom.scss`
- Ausgaben:
  - lesbares CSS → `assets/css/bootstrap.css`
  - minifiziertes CSS → `assets/css/bootstrap.min.css`

Der Pfad für die lesbare Ausgabe kann über `--output-normal` angepasst werden.

Source-Map generieren:

```bash
php bin/console bootstrap:compile --source-map
```

Benutzerdefinierte Input/Output-Pfade:

```bash
php bin/console bootstrap:compile pfad/zu/entry.scss public/css/app.css
```

### 3) Unbenutztes Bootstrap-CSS entfernen (optional)

Nach dem Kompilieren kannst Du unbenutzte Selektoren entfernen, indem Du Deine Templates scannst:

```bash
php bin/console bootstrap:purge \
  --input=assets/css/bootstrap.css \
  --output=assets/css/bootstrap-purged.css \
  --templates-dir=templates \
  --include-dir=src \
  --include-file=assets/app.js \
  --selector=collapse --selector=show
```

---

## 🧩 Befehlsreferenz

### bootstrap:init

Erstellt Bootstrap-SCSS-Einstiegsdateien.

- Optionen:
  - `--dry-run` — zeigt an, was geschrieben würde, ohne Dateien zu erstellen
  - `-f, --force` — überschreibt vorhandene Dateien
- Alias: `boostrap:init` (häufiger Tippfehler)

Erstellt die folgenden Dateien in `assets/scss/`:

- `bootstrap5-custom.scss` (hell)
- `bootstrap5-custom-dark.scss` (dunkel)

Empfohlene Reihenfolge innerhalb der Dateien: Funktionen → Deine Variablen-Overrides → Bootstrap-Import.

### bootstrap:compile

Kompiliert SCSS zu CSS mit scssphp.

- Argumente:
  - `input` (optional) — SCSS-Einstiegsdatei; Standard `assets/scss/bootstrap5-custom.scss`
  - `output` (optional) — minifizierte CSS-Ausgabedatei; Standard `assets/css/bootstrap.min.css`
- Optionen:
  - `--output-normal`, `-O` — Pfad für lesbares (nicht minifiziertes) CSS; Standard `assets/css/bootstrap.css`
  - `--source-map` — schreibt eine `.map`-Datei neben jede CSS-Ausgabe (lesbar und minifiziert)

Vorkonfigurierte Include-Pfade (in dieser Reihenfolge):

1. `vendor/twbs/bootstrap/scss`
2. `vendor`
3. `assets/scss`
4. `assets`

Dies ermöglicht Importe wie:

```scss
@import "functions";
@import "variables";
@import "bootstrap";
```

### bootstrap:purge

Bereinigt Bootstrap-CSS durch Scannen Deiner Templates und behält nur die gefundenen Selektoren bei.

- Optionen:
  - `--input`, `-i` — Pfad zur Eingabe-CSS-Datei; Standard `assets/css/bootstrap.css`
  - `--output`, `-o` — Pfad zum Schreiben des bereinigten CSS; Standard `assets/css/bootstrap-purged.css`
  - `--templates-dir` — Template-Verzeichnisse zum Scannen (mehrere erlaubt)
  - `--include-dir`, `-D` — zusätzliche Verzeichnisse zum Scannen (mehrere erlaubt)
  - `--include-file`, `-F` — zusätzliche Dateien zum Scannen (mehrere erlaubt)
  - `--selector`, `-S` — Selektoren, die immer behalten werden sollen (mehrere erlaubt)
  - `--readable`, `-r` — generiert lesbare (schöne) CSS-Ausgabe
  - `--dry-run` — zeigt Statistiken an, ohne die Ausgabedatei zu schreiben

### Verhalten der Source-Maps

Bei Verwendung von `--source-map` wird für jede Ausgabe eine Map geschrieben:

- Lesbares CSS: `assets/css/bootstrap.css` + Map `assets/css/bootstrap.css.map`
- Minifiziertes CSS: `assets/css/bootstrap.min.css` + Map `assets/css/bootstrap.min.css.map`

Beispiel Konsolenausgabe:

```text
Compiled (readable) assets/scss/bootstrap5-custom.scss -> assets/css/bootstrap.css
Source map written: assets/css/bootstrap.css.map
Compiled (minified) assets/scss/bootstrap5-custom.scss -> assets/css/bootstrap.min.css
Source map written: assets/css/bootstrap.min.css.map
```

Wenn trotz `--source-map` keine Map geschrieben wird, prüfe bitte, ob Deine SCSS-Quelle tatsächlich Inhalt erzeugt und die Standardwerte nicht überschrieben wurden.

---

## ✍️ SCSS-Beispiel

Hell (erstellt durch `bootstrap:init`):

```scss
// Projektweite Bootstrap-Konfiguration
// -------------------------------------------------
// Reihenfolge ist wichtig: Zuerst Funktionen laden, dann Variablen überschreiben,
// dann Bootstrap importieren.

// 1) Bootstrap-Funktionen (verwendet in Variablen-Berechnungen)
@import "functions";

// 2) Deine Variablen-Overrides (ohne !default, damit sie tatsächlich angewendet werden)
$primary: #ff0000;

// 3) Optional: Bootstrap-Basisvariablen laden
@import "variables";

// 4) Vollständiges Bootstrap importieren
@import "bootstrap";
```

Dunkel (erstellt durch `bootstrap:init`):

```scss
// Dark-Mode-Build für Bootstrap
// -------------------------------------------------
// 1) Bootstrap-Funktionen laden
@import "functions";

// 2) Dunkelspezifische Variablen setzen (Beispiele nach Bedarf anpassen)
$body-bg: #121212;
$body-color: #e6e6e6;
$primary: #0d6efd;

// Optional: zusätzliche Maps/Variablen von Bootstrap laden
@import "variables";

// 3) Vollständiges Bootstrap importieren
@import "bootstrap";
```

---

## 🧭 Fehlerbehebung

- Eingabedatei nicht gefunden
  - Führe `php bin/console bootstrap:init` aus, um Standardeinträge zu erstellen, oder übergib Deinen eigenen Pfad an `bootstrap:compile`.
- Bootstrap-Importe werden nicht aufgelöst
  - Stelle sicher, dass `twbs/bootstrap` installiert ist: `composer require twbs/bootstrap`.
- Source-Map-Kommentar ist vorhanden, aber es wird keine Datei geschrieben
  - Bei der aktuellen Implementierung wird die Map nach der Kompilierung geschrieben. Prüfe, ob `--source-map` gesetzt ist und Dein SCSS Inhalt erzeugt.
- scssphp-Versionen
  - Dieses Bundle zielt auf `scssphp/scssphp` ^2.0 ab. Wenn Du eine andere Hauptversion verwendest, passe dies entsprechend an.

---

## 📜 Lizenz

Dieses Bundle ist unter der MIT-Lizenz lizenziert. Weitere Details findest Du in der Datei [LICENSE](LICENSE).

Entwickelt von Jürgen Schwind und weiteren Mitwirkenden.

---

## 🤝 Mitwirken

Beiträge sind willkommen! Wenn Du etwas beitragen möchtest, kontaktiere uns oder erstelle einen Fork des Repositories und sende einen Pull-Request mit Deinen Änderungen oder Verbesserungen.

---

## 📫 Kontakt

Wenn Du Fragen, Feature-Anfragen oder Probleme hast, eröffne bitte ein Issue in unserem [GitHub-Repository](https://github.com/jbsnewmedia/bootstrap-bundle) oder sende einen Pull-Request.

---

*Einfaches Bootstrap-SCSS-Gerüstbau und Kompilierung, Composer-nativ.*
