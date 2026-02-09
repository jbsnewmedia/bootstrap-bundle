<?php

declare(strict_types=1);

namespace JBSNewMedia\BootstrapBundle\Tests\Command;

use JBSNewMedia\BootstrapBundle\Command\CompileCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

class CompileCommandMoreTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bb_compile_more_' . uniqid();
        mkdir($this->tempDir, 0777, true);
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

    private function makeKernelMock(): KernelInterface
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tempDir);
        return $kernel;
    }

    public function testCompilesWithSourceMap(): void
    {
        $scssDir = $this->tempDir . '/assets/scss';
        @mkdir($scssDir, 0777, true);
        $in = $scssDir . '/simple.scss';
        file_put_contents($in, '$primary: red; body { color: $primary; }');

        $kernel = $this->makeKernelMock();
        $command = new CompileCommand($kernel);
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:compile'));

        $exit = $tester->execute([
            'input' => 'assets/scss/simple.scss',
            'output' => 'assets/css/out.min.css',
            '--output-normal' => 'assets/css/out.css',
            '--source-map' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertFileExists($this->tempDir . '/assets/css/out.min.css.map');
        $this->assertFileExists($this->tempDir . '/assets/css/out.css.map');
        $this->assertStringContainsString('Source map written:', $tester->getDisplay());
    }

    public function testFailsWhenOutputDirCannotBeCreated(): void
    {
        $scssDir = $this->tempDir . '/assets/scss';
        @mkdir($scssDir, 0777, true);
        $in = $scssDir . '/simple.scss';
        file_put_contents($in, 'body { color: red; }');

        // Create a file where a directory is expected to simulate mkdir failure
        $cssDir = $this->tempDir . '/assets/css';
        @mkdir(dirname($cssDir), 0777, true);
        file_put_contents($cssDir, 'not a dir');

        $kernel = $this->makeKernelMock();
        $command = new CompileCommand($kernel);
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:compile'));

        $exit = $tester->execute([
            'input' => 'assets/scss/simple.scss',
            'output' => 'assets/css/out.min.css',
            '--output-normal' => 'assets/css/out.css',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Failed to create output directory', $tester->getDisplay());
    }
}
