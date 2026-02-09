<?php

declare(strict_types=1);

namespace JBSNewMedia\BootstrapBundle\Tests\Service;

use JBSNewMedia\BootstrapBundle\Service\PurgeService;
use PHPUnit\Framework\TestCase;

class PurgeServiceTest extends TestCase
{
    private string $tempDir;
    private PurgeService $service;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/purge_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->service = new PurgeService();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testPurgeBasic(): void
    {
        $cssFile = $this->tempDir . '/test.css';
        // CssPurger expects blocks separated by "\n}\n"
        $css = "\n}\n.keep{color:red;}\n}\n.drop{color:blue;}\n}\n";
        file_put_contents($cssFile, $css);

        $templateFile = $this->tempDir . '/index.html.twig';
        file_put_contents($templateFile, '<div class="keep"></div>');

        [$keptSelectors, $purgedCss, $stats] = $this->service->purge(
            cssPath: $cssFile,
            pathsToScan: [$this->tempDir]
        );

        $this->assertContains('.keep', $keptSelectors);
        $this->assertNotContains('.drop', $keptSelectors);
        // Ausgabe sollte nur den .keep-Block enthalten
        $this->assertStringContainsString('.keep', $purgedCss);
        $this->assertStringNotContainsString('.drop', $purgedCss);
        $this->assertGreaterThanOrEqual(1, $stats['found']);
    }

    public function testPurgeWithExtraSelectors(): void
    {
        $cssFile = $this->tempDir . '/test.css';
        $css = ".extra{color:red;}\n}\n";
        file_put_contents($cssFile, $css);

        [$keptSelectors, $purgedCss, $stats] = $this->service->purge(
            cssPath: $cssFile,
            pathsToScan: [],
            extraSelectors: ['.extra', '.not-in-css']
        );

        $this->assertContains('.extra', $keptSelectors);
        $this->assertContains('.not-in-css', $keptSelectors);
    }

    public function testExtractSelectorsFromDifferentSources(): void
    {
        $cssFile = $this->tempDir . '/test.css';
        file_put_contents($cssFile, '.c1 { color: r; } #id1 { color: g; } .c2 { color: b; }');

        $html = '
            <div class="c1"></div>
            <div id="id1"></div>
            <div className="c2"></div>
            <script>const x = "c1";</script>
        ';
        $sourceFile = $this->tempDir . '/source.html';
        file_put_contents($sourceFile, $html);

        [$keptSelectors, $purgedCss, $stats] = $this->service->purge($cssFile, [$sourceFile]);

        $this->assertContains('.c1', $keptSelectors);
        $this->assertContains('#id1', $keptSelectors);
        $this->assertContains('.c2', $keptSelectors);
    }

    public function testCollectContentsRecursive(): void
    {
        $subDir = $this->tempDir . '/sub';
        mkdir($subDir);
        file_put_contents($subDir . '/test.html', 'class="deep"');

        $emptySub = $subDir . '/empty';
        mkdir($emptySub);

        $cssFile = $this->tempDir . '/test.css';
        file_put_contents($cssFile, '.deep { color: red; }');

        [$keptSelectors] = $this->service->purge($cssFile, [$this->tempDir]);
        $this->assertContains('.deep', $keptSelectors);
    }

    public function testPurgeWithEmptySelectors(): void
    {
        $cssFile = $this->tempDir . '/test.css';
        file_put_contents($cssFile, ".keep{color:red;}\n}\n");

        [$keptSelectors, $purgedCss, $stats] = $this->service->purge(
            cssPath: $cssFile,
            pathsToScan: [],
            extraSelectors: []
        );

        $this->assertEmpty($keptSelectors);
        $this->assertNotSame('', $purgedCss);
    }

    public function testCollectContentsWithEmptyAndInvalidPaths(): void
    {
        $cssFile = $this->tempDir . '/test.css';
        file_put_contents($cssFile, ".keep{color:red;}\n}\n");

        // Create a valid file to scan
        $validFile = $this->tempDir . '/valid.html';
        file_put_contents($validFile, '<div class="keep"></div>');

        [$keptSelectors, $purgedCss, $stats] = $this->service->purge(
            cssPath: $cssFile,
            pathsToScan: [
                '',          // empty string - should be skipped (line 81)
                $validFile,  // valid file
            ],
            extraSelectors: []
        );

        $this->assertContains('.keep', $keptSelectors);
        $this->assertCount(1, $stats['scanned_files']);
    }

    public function testCollectContentsWithNonScannableFile(): void
    {
        $cssFile = $this->tempDir . '/test.css';
        file_put_contents($cssFile, ".keep{color:red;}\n}\n");

        // Create a file with non-scannable extension
        $binaryFile = $this->tempDir . '/file.bin';
        file_put_contents($binaryFile, 'binary content');

        [$keptSelectors, $purgedCss, $stats] = $this->service->purge(
            cssPath: $cssFile,
            pathsToScan: [$binaryFile],
            extraSelectors: []
        );

        $this->assertEmpty($keptSelectors);
        $this->assertCount(0, $stats['scanned_files']);
    }

    public function testExtractSelectorsFromTwigComments(): void
    {
        $cssFile = $this->tempDir . '/test.css';
        file_put_contents($cssFile, ".visible{color:red;}\n}\n.hidden{color:blue;}\n}\n");

        // Twig comment should be stripped, but visible class should be found
        $twigFile = $this->tempDir . '/template.html.twig';
        file_put_contents($twigFile, '{# comment with class="hidden" #}<div class="visible"></div>');

        [$keptSelectors, $purgedCss, $stats] = $this->service->purge(
            cssPath: $cssFile,
            pathsToScan: [$twigFile],
            extraSelectors: []
        );

        $this->assertContains('.visible', $keptSelectors);
        $this->assertNotContains('.hidden', $keptSelectors);
    }

    public function testExtractSelectorsFromJsClassListMethods(): void
    {
        $cssFile = $this->tempDir . '/test.css';
        file_put_contents($cssFile, ".dynamic{color:red;}\n}\n.toggled{color:blue;}\n}\n");

        // Test classList.add and classList.toggle patterns
        $jsFile = $this->tempDir . '/script.js';
        file_put_contents($jsFile, "element.classList.add('dynamic', 'toggled');");

        [$keptSelectors, $purgedCss, $stats] = $this->service->purge(
            cssPath: $cssFile,
            pathsToScan: [$jsFile],
            extraSelectors: []
        );

        $this->assertContains('.dynamic', $keptSelectors);
        $this->assertContains('.toggled', $keptSelectors);
    }

    public function testExtractSelectorsFromDataBsTheme(): void
    {
        $cssFile = $this->tempDir . '/test.css';
        file_put_contents($cssFile, "[data-bs-theme=dark]{color:white;}\n}\n");

        $htmlFile = $this->tempDir . '/index.html';
        file_put_contents($htmlFile, '<html data-bs-theme="dark">');

        [$keptSelectors, $purgedCss, $stats] = $this->service->purge(
            cssPath: $cssFile,
            pathsToScan: [$htmlFile],
            extraSelectors: []
        );

        $this->assertContains('[data-bs-theme=dark]', $keptSelectors);
    }

    public function testNormalizeSelectorRejectsInvalidSelectors(): void
    {
        $cssFile = $this->tempDir . '/test.css';
        file_put_contents($cssFile, ".valid{color:red;}\n}\n");

        [$keptSelectors, $purgedCss, $stats] = $this->service->purge(
            cssPath: $cssFile,
            pathsToScan: [],
            extraSelectors: [
                '.valid',           // valid class
                '#validId',         // valid id
                'body',             // valid tag
                ':root',            // valid special selector
                '[data-bs-theme=dark]', // valid attribute
                '.123invalid',      // invalid: starts with number
                '#123invalid',      // invalid: starts with number
                ':invalid-pseudo',  // invalid: not :root
                '..double-dot',     // invalid format
                '',                 // empty
                '   ',              // whitespace only
            ]
        );

        $this->assertContains('.valid', $keptSelectors);
        $this->assertContains('#validId', $keptSelectors);
        $this->assertContains('body', $keptSelectors);
        $this->assertContains(':root', $keptSelectors);
        $this->assertContains('[data-bs-theme=dark]', $keptSelectors);
        $this->assertNotContains('.123invalid', $keptSelectors);
        $this->assertNotContains('#123invalid', $keptSelectors);
        $this->assertNotContains(':invalid-pseudo', $keptSelectors);
    }

    public function testPurgeWithWhitespaceOnlyExtraSelector(): void
    {
        $cssFile = $this->tempDir . '/test.css';
        file_put_contents($cssFile, ".keep{color:red;}\n}\n");

        [$keptSelectors, $purgedCss, $stats] = $this->service->purge(
            cssPath: $cssFile,
            pathsToScan: [],
            extraSelectors: ['   ', '  .keep  ', '']
        );

        $this->assertContains('.keep', $keptSelectors);
        // Whitespace-only selectors should be filtered out
        $this->assertNotContains('', $keptSelectors);
    }
}
