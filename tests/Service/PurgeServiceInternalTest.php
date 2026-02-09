<?php

declare(strict_types=1);

namespace JBSNewMedia\BootstrapBundle\Tests\Service;

use JBSNewMedia\BootstrapBundle\Service\PurgeService;
use PHPUnit\Framework\TestCase;

class PurgeServiceInternalTest extends TestCase
{
    public function testIsScannableFile(): void
    {
        $svc = new PurgeService();
        $ref = new \ReflectionClass($svc);
        $m = $ref->getMethod('isScannableFile');
        $m->setAccessible(true);
        $this->assertTrue($m->invoke($svc, '/tmp/a.twig'));
        $this->assertTrue($m->invoke($svc, '/tmp/a.html'));
        $this->assertFalse($m->invoke($svc, '/tmp/a.txt'));
    }

    public function testNormalizeSelectors(): void
    {
        $svc = new PurgeService();
        $ref = new \ReflectionClass($svc);
        $m = $ref->getMethod('normalizeSelectors');
        $m->setAccessible(true);

        $out = $m->invoke($svc, ['.a', '#id', 'div', '[data-bs-theme=dark]', ':root', ' invalid ', '1bad', '.bad name']);
        $this->assertContains('.a', $out);
        $this->assertContains('#id', $out);
        $this->assertContains('div', $out);
        $this->assertContains('[data-bs-theme=dark]', $out);
        $this->assertContains(':root', $out);
        $this->assertContains('invalid', $out);
    }

    public function testExtractSelectors(): void
    {
        $svc = new PurgeService();
        $ref = new \ReflectionClass($svc);
        $m = $ref->getMethod('extractSelectors');
        $m->setAccessible(true);

        $content = <<<'TXT'
        {# twig comment #}
        {{ "foo" }}
        {% set x = 'bar' %}
        <div class="cls1 cls2"></div>
        <div id="id1"></div>
        <div data-bs-theme="dark"></div>
        <script>el.classList.add('x','y');</script>
        <h1>Hello</h1>
        TXT;

        $selectors = $m->invoke($svc, $content);
        $this->assertContains('.foo', $selectors);
        $this->assertContains('.bar', $selectors);
        $this->assertContains('.cls1', $selectors);
        $this->assertContains('.cls2', $selectors);
        $this->assertContains('#id1', $selectors);
        $this->assertContains('[data-bs-theme=dark]', $selectors);
        $this->assertContains('h1', $selectors);
        $this->assertContains('.x', $selectors);
        $this->assertContains('.y', $selectors);
    }

    public function testExtractSelectorsJsToggle(): void
    {
        $svc = new PurgeService();
        $ref = new \ReflectionClass($svc);
        $m = $ref->getMethod('extractSelectors');
        $m->setAccessible(true);

        $content = 'classList.toggle("toggle-class")';
        $selectors = $m->invoke($svc, $content);
        $this->assertContains('.toggle-class', $selectors);
    }

    public function testNormalizeSelectorsEdges(): void
    {
        $svc = new PurgeService();
        $ref = new \ReflectionClass($svc);
        $m = $ref->getMethod('normalizeSelectors');
        $m->setAccessible(true);

        // Empty string, invalid first char
        $out = $m->invoke($svc, ['', '  ', '1abc', '!!!']);
        $this->assertEmpty($out);
    }
    public function testCollectContentsWithNonExistentPath(): void
    {
        $svc = new PurgeService();
        $ref = new \ReflectionClass($svc);
        $m = $ref->getMethod('collectContents');
        $m->setAccessible(true);

        [$content, $scannedFiles] = $m->invoke($svc, ['/non/existent/path']);
        $this->assertEquals('', $content);
        $this->assertEmpty($scannedFiles);
    }
}
