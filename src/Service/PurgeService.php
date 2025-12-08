<?php

declare(strict_types=1);

namespace JBSNewMedia\BootstrapBundle\Service;

use JBSNewMedia\CssPurger\Vendors\Bootstrap as BootstrapPurger;

class PurgeService
{
    /**
     * Purge a CSS file using selectors found in given paths and extra selectors provided.
     *
     * @param string   $cssPath        Path to the input CSS file
     * @param string[] $pathsToScan    Files or directories to scan (recursive for dirs)
     * @param string[] $extraSelectors Additional selectors to keep
     * @param bool     $readable       If true, output will be formatted (not minified)
     *
     * @return array{0: array<int,string>, 1: string, 2: array<string,int>} [selectorsKept, cssOutput, stats]
     */
    public function purge(string $cssPath, array $pathsToScan = [], array $extraSelectors = [], bool $readable = false): array
    {
        $content = $this->collectContents($pathsToScan);
        $foundSelectors = $this->extractSelectors($content);

        // Merge extra selectors provided by user
        foreach ($extraSelectors as $sel) {
            $sel = trim((string) $sel);
            if ($sel !== '') {
                $foundSelectors[] = $sel;
            }
        }

        // Normalize and unique
        $normalized = $this->normalizeSelectors($foundSelectors);
        $normalized = array_values(array_unique($normalized));

        // Ensure the purger class is available. When the bundle is loaded from source via PSR-4
        // (and not required via Composer), the bundle's own vendor autoloader is not registered
        // in the host application. Try to load it locally as a fallback.
        if (!class_exists(BootstrapPurger::class)) {
            $bundleVendorAutoload = __DIR__ . '/../../vendor/autoload.php';
            if (is_file($bundleVendorAutoload)) {
                /** @noinspection PhpIncludeInspection */
                @require_once $bundleVendorAutoload;
            }
        }
        if (!class_exists(BootstrapPurger::class)) {
            throw new \RuntimeException(
                'Required class JBSNewMedia\\CssPurger\\Vendors\\Bootstrap not found. ' .
                'Please ensure the package "jbsnewmedia/css-purger" is installed. ' .
                'If you load the BootstrapBundle from source, either: ' .
                '1) require jbsnewmedia/css-purger in your application, or ' .
                '2) run "composer install" inside the bundle so its vendor/autoload.php exists.'
            );
        }

        $purger = new BootstrapPurger($cssPath);
        $purger->loadContent();
        $purger->prepareContent();
        $purger->runContent();
        if ($normalized) {
            $purger->addSelectors($normalized);
        }
        $css = $purger->generateOutput(!$readable); // library expects minify flag; invert readable

        return [$normalized, $css, [
            'found' => count($foundSelectors),
            'normalized' => count($normalized),
        ]];
    }

    /** @param string[] $paths */
    private function collectContents(array $paths): string
    {
        $buffer = '';
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            if (is_dir($path)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
                /** @var \SplFileInfo $file */
                foreach ($it as $file) {
                    if ($this->isScannableFile($file->getPathname())) {
                        $buffer .= "\n\n/* FILE: {$file->getPathname()} */\n" . @file_get_contents($file->getPathname());
                    }
                }
            } elseif (is_file($path) && $this->isScannableFile($path)) {
                $buffer .= "\n\n/* FILE: {$path} */\n" . @file_get_contents($path);
            }
        }

        return $buffer;
    }

    private function isScannableFile(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowed = ['twig', 'html', 'htm', 'php', 'phtml', 'js', 'ts', 'vue', 'jsx', 'tsx', 'md'];
        return in_array($ext, $allowed, true);
    }

    /**
     * Extracts class, id, tag and attribute-based selectors from raw content.
     * This is a heuristic using regex, good enough for most Twig/HTML/JS.
     *
     * @return string[] raw selector tokens (not yet normalized)
     */
    private function extractSelectors(string $content): array
    {
        $selectors = [];

        // class="foo bar" and class='foo bar'
        if (preg_match_all('/class\s*=\s*(["\'])(.*?)\1/si', $content, $m)) {
            foreach ($m[2] as $classList) {
                foreach (preg_split('/\s+/', trim((string)$classList)) as $cls) {
                    if ($cls !== '') {
                        $selectors[] = '.' . $cls;
                    }
                }
            }
        }

        // className="foo" (React/JSX)
        if (preg_match_all('/className\s*=\s*(["\'])(.*?)\1/si', $content, $m2)) {
            foreach ($m2[2] as $classList) {
                foreach (preg_split('/\s+/', trim((string)$classList)) as $cls) {
                    if ($cls !== '') {
                        $selectors[] = '.' . $cls;
                    }
                }
            }
        }

        // id="foo" and id='foo'
        if (preg_match_all('/id\s*=\s*(["\'])(.*?)\1/si', $content, $m3)) {
            foreach ($m3[2] as $idVal) {
                $idVal = trim((string)$idVal);
                if ($idVal !== '') {
                    $selectors[] = '#' . $idVal;
                }
            }
        }

        // data-bs-theme=value => attribute selector
        if (preg_match_all('/data-bs-theme\s*=\s*(["\']?)([a-zA-Z0-9_-]+)\1/si', $content, $m4)) {
            foreach ($m4[2] as $theme) {
                $selectors[] = '[data-bs-theme=' . $theme . ']';
            }
        }

        // HTML tags used, e.g., <body>, <h1>, <div>
        if (preg_match_all('/<\s*([a-z][a-z0-9-]*)[^>]*>/i', $content, $m5)) {
            foreach ($m5[1] as $tag) {
                $selectors[] = strtolower($tag);
            }
        }

        // JS: element.classList.add('foo','bar')
        if (preg_match_all('/classList\.(?:add|toggle)\s*\(([^\)]*)\)/si', $content, $m6)) {
            foreach ($m6[1] as $args) {
                if (preg_match_all('/["\']([a-zA-Z0-9_-]+)["\']/', (string)$args, $mArgs)) {
                    foreach ($mArgs[1] as $cls) {
                        $selectors[] = '.' . $cls;
                    }
                }
            }
        }

        return $selectors;
    }

    /** @param string[] $selectors */
    private function normalizeSelectors(array $selectors): array
    {
        $out = [];
        foreach ($selectors as $sel) {
            $sel = trim((string) $sel);
            if ($sel === '') {
                continue;
            }
            // Ensure proper prefix for plain words
            if ($sel[0] !== '.' && $sel[0] !== '#' && $sel[0] !== '[' && $sel[0] !== ':' && !preg_match('/^[a-z]/i', $sel)) {
                // skip weird tokens
                continue;
            }
            // Sanitize multiple spaces
            $sel = preg_replace('/\s+/', ' ', $sel);
            $out[] = $sel;
        }

        return $out;
    }
}
