# JBSNewMedia Bootstrap Bundle

JBSNewMedia Bootstrap Bundle is a lightweight Composer package that helps you scaffold and compile Bootstrap SCSS within your PHP/Symfony projects. It ships two console commands:

- `bootstrap:init` — scaffolds Bootstrap SCSS entry files in `assets/scss/`.
- `bootstrap:compile` — compiles SCSS to CSS using scssphp, with sensible defaults and Bootstrap include paths.

This package is framework‑light: it does not require a full Symfony kernel to run the commands. A simple `bin/console` that registers the commands is enough.

---

## 🚀 Features

- Scaffold ready‑to‑use SCSS entries for Bootstrap (light and dark variants)
- Compile SCSS → CSS using [scssphp](https://github.com/scssphp/scssphp)
- Includes import paths for `vendor/twbs/bootstrap/scss` out of the box
- Optional source maps
- Works in any Composer project; Symfony optional
- Typo‑tolerant command aliases (`boostrap:*`)

---

## ⚙️ Requirements

- PHP 8.2 or higher
- Composer
- Bootstrap 5 via Composer (`twbs/bootstrap`)

---

## 📦 Installation

Install via Composer:

```bash
composer require jbsnewmedia/bootstrap-bundle
```

This package requires `twbs/bootstrap` and `scssphp/scssphp`. If not already present, Composer will install them.

---

## 📋 Usage

### 1) Initialize SCSS entries

Scaffold the default SCSS entry files under `assets/scss/`:

```bash
php bin/console bootstrap:init
# or preview without writing files
php bin/console bootstrap:init --dry-run
# overwrite if they already exist
php bin/console bootstrap:init --force
```

Files created:

- `assets/scss/bootstrap5-custom.scss`
- `assets/scss/bootstrap5-custom-dark.scss`

Both entries import Bootstrap after your variable overrides.

### 2) Compile SCSS → CSS

Compile with sensible defaults:

```bash
php bin/console bootstrap:compile
```

Defaults:

- Input: `assets/scss/bootstrap5-custom.scss`
- Output: `assets/css/bootstrap.min.css`
- Output style: compressed

Generate source maps:

```bash
php bin/console bootstrap:compile --source-map
```

Custom input/output:

```bash
php bin/console bootstrap:compile path/to/entry.scss public/css/app.css
```

Convenience: calling without a command runs the compiler with defaults

```bash
php bin/console
```

---

## 🧩 Commands reference

### bootstrap:init

Scaffolds Bootstrap SCSS entry files.

- Options:
  - `--dry-run` — show what would be written without creating files
  - `-f, --force` — overwrite existing files

Creates the following files in `assets/scss/`:

- `bootstrap5-custom.scss` (light)
- `bootstrap5-custom-dark.scss` (dark)

Both files follow the recommended order: functions → your variable overrides → Bootstrap import.

### bootstrap:compile

Compiles SCSS to CSS using scssphp.

- Arguments:
  - `input` (optional) — SCSS entry file; default `assets/scss/bootstrap5-custom.scss`
  - `output` (optional) — CSS output file; default `assets/css/bootstrap.min.css`
- Options:
  - `--source-map` — generate a `.map` file alongside the CSS

Import paths are preconfigured to resolve common Bootstrap imports:

1. `vendor/twbs/bootstrap/scss`
2. `vendor`
3. `assets/scss`
4. `assets`
5. `node_modules`

This lets you use imports like:

```scss
@import "functions";
@import "variables";
@import "bootstrap";
```

---

## ✍️ Example SCSS entries

Light (created by `bootstrap:init`):

```scss
// Project-wide Bootstrap configuration
// 1) Bootstrap functions
@import "functions";

// 2) Your variable overrides
$primary: #ff0000;

// 3) Optionally load Bootstrap variables
@import "variables";

// 4) Import full Bootstrap
@import "bootstrap";
```

Dark (created by `bootstrap:init`):

```scss
// Dark mode build for Bootstrap
@import "functions";
$body-bg: #121212;
$body-color: #e6e6e6;
$primary: #0d6efd;
@import "variables";
@import "bootstrap";
```

---

## 🧭 Troubleshooting

- Input file not found
  - Run `php bin/console bootstrap:init` to create default entries, or pass your own path to `bootstrap:compile`.
- Bootstrap imports not resolved
  - Ensure `twbs/bootstrap` is installed: `composer require twbs/bootstrap`.
- Typos in command names
  - Common typo aliases are supported: `boostrap:init`, `boostrap:compile`.

---

## 🧪 Development & QA

This package aims to be minimal and dependency‑light. Use any standard PHP QA tools you prefer (PHP-CS-Fixer, PHPStan, Rector). Example custom Composer binaries setup is outside the scope of this README.

---

## 📜 License

Licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

Developed by Juergen Schwind and contributors.

---

## 🤝 Contributing

Contributions are welcome! Please fork the repository and open a pull request. For larger changes, consider opening an issue first to discuss your idea.

---

## 📫 Contact

For questions or issues, please open an issue or a pull request in the repository.

— Simple Bootstrap SCSS scaffolding and compilation, Composer‑native.
