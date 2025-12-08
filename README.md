# JBSNewMedia Bootstrap Bundle

A lightweight Symfony bundle that helps you scaffold and compile Bootstrap SCSS with [scssphp](https://github.com/scssphp/scssphp). It ships three console commands:

- `bootstrap:init` — scaffolds SCSS entry files under `assets/scss/`.
- `bootstrap:compile` — compiles SCSS to CSS (with sensible defaults and vendor‑aware import paths).
- `bootstrap:purge` — purges compiled Bootstrap CSS by scanning your templates.

---

## 🚀 Features

- Ready‑to‑use SCSS entries (light and dark)
- SCSS → CSS via scssphp (pure PHP, no Node required)
- Writes readable and minified CSS in one run
- Optional source maps for each output (`--source-map`)
- Include paths for `vendor/twbs/bootstrap/scss` out of the box
- Purge Bootstrap CSS based on your templates (`bootstrap:purge`)
- Clean defaults and sensible paths

---

## ⚙️ Requirements

- PHP 8.2 or higher
- Symfony 6.4 or 7.x (framework‑bundle, console)
- Composer
- Dependencies:
  - `twbs/bootstrap` (>= 5.3)
  - `scssphp/scssphp` (^2.0)
  - `jbsnewmedia/css-purger` (^1.0)

Note: This is a regular Symfony bundle and expects a Symfony kernel (it is auto‑registered).

---

## 📦 Installation

Install via Composer:

```bash
composer require jbsnewmedia/bootstrap-bundle
```

If not already present, Composer will install the required packages (`twbs/bootstrap`, `scssphp/scssphp`).

---

## 📋 Usage

### 1) Scaffold SCSS entries

Create the default SCSS entry files under `assets/scss/`:

```bash
php bin/console bootstrap:init
# preview without writing files
php bin/console bootstrap:init --dry-run
# overwrite existing files
php bin/console bootstrap:init --force
```

Files created:

- `assets/scss/bootstrap5-custom.scss`
- `assets/scss/bootstrap5-custom-dark.scss`

Both entries import Bootstrap after your variable overrides in the correct order.

### 2) Compile SCSS → CSS

Compile with sensible defaults:

```bash
php bin/console bootstrap:compile
```

Defaults:

- Input: `assets/scss/bootstrap5-custom.scss`
- Outputs:
  - readable CSS → `assets/css/bootstrap.css`
  - minified CSS → `assets/css/bootstrap.min.css`

Readable output path can be adjusted via `--output-normal`.

Generate a source map:

```bash
php bin/console bootstrap:compile --source-map
```

Custom input/output paths:

```bash
php bin/console bootstrap:compile path/to/entry.scss public/css/app.css
```

### 3) Purge unused Bootstrap CSS (optional)

After compiling, you can purge unused selectors by scanning your templates:

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

## 🧩 Command reference

### bootstrap:init

Scaffolds Bootstrap SCSS entry files.

- Options:
  - `--dry-run` — show what would be written without creating files
  - `-f, --force` — overwrite existing files
- Alias: `boostrap:init` (common typo)

Creates the following files in `assets/scss/`:

- `bootstrap5-custom.scss` (light)
- `bootstrap5-custom-dark.scss` (dark)

Recommended order inside the files: functions → your variable overrides → Bootstrap import.

### bootstrap:compile

Compiles SCSS to CSS using scssphp.

- Arguments:
  - `input` (optional) — SCSS entry file; default `assets/scss/bootstrap5-custom.scss`
  - `output` (optional) — minified CSS output file; default `assets/css/bootstrap.min.css`
- Options:
  - `--output-normal`, `-O` — readable (non‑minified) CSS output path; default `assets/css/bootstrap.css`
  - `--source-map` — write a `.map` file next to each CSS output (readable and minified)

Preconfigured include paths (in this order):

1. `vendor/twbs/bootstrap/scss`
2. `vendor`
3. `assets/scss`
4. `assets`

This allows imports like:

```scss
@import "functions";
@import "variables";
@import "bootstrap";
```

### bootstrap:purge

Purges Bootstrap CSS by scanning your templates and keeping only the selectors that are found.

- Options:
  - `--input`, `-i` — path to input CSS file; default `assets/css/bootstrap.css`
  - `--output`, `-o` — path to write the purged CSS; default `assets/css/bootstrap-purged.css`
  - `--templates-dir` — template directories to scan (multiple allowed)
  - `--include-dir`, `-D` — additional directories to scan (multiple allowed)
  - `--include-file`, `-F` — additional files to scan (multiple allowed)
  - `--selector`, `-S` — selectors to always keep (multiple allowed)
  - `--readable`, `-r` — generate human‑readable (pretty) CSS output
  - `--dry-run` — show stats without writing the output file

### Source map behavior

When using `--source-map`, a map is written for each output:

- Readable CSS: `assets/css/bootstrap.css` + map `assets/css/bootstrap.css.map`
- Minified CSS: `assets/css/bootstrap.min.css` + map `assets/css/bootstrap.min.css.map`

Example console output:

```text
Compiled (readable) assets/scss/bootstrap5-custom.scss -> assets/css/bootstrap.css
Source map written: assets/css/bootstrap.css.map
Compiled (minified) assets/scss/bootstrap5-custom.scss -> assets/css/bootstrap.min.css
Source map written: assets/css/bootstrap.min.css.map
```

If no map is written even though `--source-map` is set, please check that your SCSS source actually produces output and that the defaults were not overridden.

---

## ✍️ Example SCSS

Light (created by `bootstrap:init`):

```scss
// Project-wide Bootstrap configuration
// -------------------------------------------------
// Order matters: load functions first, then override variables,
// then import Bootstrap.

// 1) Bootstrap functions (used in variable calculations)
@import "functions";

// 2) Your variable overrides (omit !default so they actually apply)
$primary: #ff0000;

// 3) Optional: load Bootstrap base variables
@import "variables";

// 4) Import full Bootstrap
@import "bootstrap";
```

Dark (created by `bootstrap:init`):

```scss
// Dark mode build for Bootstrap
// -------------------------------------------------
// 1) Load Bootstrap functions
@import "functions";

// 2) Set dark-specific variables (adjust examples as needed)
$body-bg: #121212;
$body-color: #e6e6e6;
$primary: #0d6efd;

// Optional: load additional maps/variables from Bootstrap
@import "variables";

// 3) Import full Bootstrap
@import "bootstrap";
```

---

## 🧭 Troubleshooting

- Input file not found
  - Run `php bin/console bootstrap:init` to create default entries, or pass your own path to `bootstrap:compile`.
- Bootstrap imports not resolved
  - Ensure `twbs/bootstrap` is installed: `composer require twbs/bootstrap`.
- Source map comment is present, but no file is written
  - With the current implementation the map is written after compilation. Check that `--source-map` is set and your SCSS produces content.
- scssphp versions
  - This bundle targets `scssphp/scssphp` ^2.0. If you use another major version, adjust accordingly.

---

## ℹ️ Git information

- Latest tag: `1.0.2`
- Last commit: `b66d748` — 2025-12-08

---

## 📜 License

Licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

Developed by Juergen Schwind and contributors.

---

## 🤝 Contribution

Contributions are welcome! Please fork the repository and open a pull request. For larger changes, consider opening an issue first to discuss your idea.

---

## 📫 Contact

For questions or issues, please open an issue or a pull request in the repository.

— Simple Bootstrap SCSS scaffolding and compilation, Composer‑native.
