<?php

declare(strict_types=1);

namespace JBSNewMedia\BootstrapBundle\Tests\Command;

use JBSNewMedia\BootstrapBundle\Command\CompileCommand;
use JBSNewMedia\BootstrapBundle\Service\ScssCompilerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

class CompileCommandEdgeCasesTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bb_compile_edge_' . uniqid();
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
            (is_dir("$dir/$file")) ? $this->removeDir("$dir/$file") : @unlink("$dir/$file");
        }
        @rmdir($dir);
    }

    private function makeKernelMock(): KernelInterface
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tempDir);
        return $kernel;
    }

    public function testUnavailableCompilerEarlyExit(): void
    {
        $kernel = $this->makeKernelMock();
        $fakeFactory = new class extends ScssCompilerFactory {
            public function isAvailable(): bool { return false; }
        };
        $command = new CompileCommand($kernel, $fakeFactory);
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:compile'));
        $exit = $tester->execute([]);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Required class ScssPhp\\ScssPhp\\Compiler not found', $tester->getDisplay());
    }

    public function testFailedToReadInputFile(): void
    {
        $scssFile = $this->tempDir . '/assets/scss/simple.scss';
        @mkdir(dirname($scssFile), 0777, true);
        file_put_contents($scssFile, 'body { color: red; }');

        // Make file unreadable
        chmod($scssFile, 0000);

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

        // Reset permissions so tearDown can delete it
        chmod($scssFile, 0666);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Failed to read input file.', $tester->getDisplay());
    }

    public function testFailsWhenInputDoesNotExistWithHints(): void
    {
        $kernel = $this->makeKernelMock();
        $command = new CompileCommand($kernel);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:compile'));

        $tester->execute(['input' => 'vendor/twbs/bootstrap/scss/bootstrap.scss']);
        $this->assertStringContainsString('Hint: Make sure the package "twbs/bootstrap" is installed', $tester->getDisplay());

        $tester->execute(['input' => 'assets/scss/bootstrap5-custom.scss']);
        $this->assertStringContainsString('Hint: Create the file assets/scss/bootstrap5-custom.scss', $tester->getDisplay());
    }

    public function testMakePathRelativeFallback(): void
    {
        $kernel = $this->makeKernelMock();
        $command = new CompileCommand($kernel);

        $ref = new \ReflectionClass($command);
        $method = $ref->getMethod('makePathRelative');
        $method->setAccessible(true);

        $res = $method->invoke($command, '/outside/path.map');
        $this->assertEquals('/outside/path.map', $res);
    }

    public function testReadableCompilationThrows(): void
    {
        $scssDir = $this->tempDir . '/assets/scss';
        @mkdir($scssDir, 0777, true);
        $in = $scssDir . '/bad.scss';
        // Broken SCSS to trigger exception
        file_put_contents($in, 'body { color: $unknown_variable');

        $kernel = $this->makeKernelMock();
        $command = new CompileCommand($kernel);
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:compile'));

        $exit = $tester->execute([
            'input' => 'assets/scss/bad.scss',
            'output' => 'assets/css/out.min.css',
            '--output-normal' => 'assets/css/out.css',
        ]);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('SCSS compilation (readable) failed', $tester->getDisplay());
    }

    public function testFailedToCreateOutputDir(): void
    {
        $scssFile = $this->tempDir . '/assets/scss/simple.scss';
        @mkdir(dirname($scssFile), 0777, true);
        file_put_contents($scssFile, 'body { color: red; }');

        // Create a file where directory should be
        $blockedDir = $this->tempDir . '/assets/css';
        file_put_contents($blockedDir, 'not a dir');

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
