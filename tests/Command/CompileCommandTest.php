<?php

declare(strict_types=1);

namespace JBSNewMedia\BootstrapBundle\Tests\Command;

use JBSNewMedia\BootstrapBundle\Command\CompileCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

class CompileCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bb_compile_cmd_' . uniqid();
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

    public function testFailsWhenInputDoesNotExist(): void
    {
        $kernel = $this->makeKernelMock();
        $command = new CompileCommand($kernel);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:compile'));

        $exit = $tester->execute(['input' => 'assets/scss/missing.scss']);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Input file not found', $tester->getDisplay());
    }

    public function testCompilesSimpleScss(): void
    {
        $scssDir = $this->tempDir . '/assets/scss';
        @mkdir($scssDir, 0777, true);
        $in = $scssDir . '/simple.scss';
        file_put_contents($in, '$primary: red; body { color: $primary; }');

        $kernel = $this->makeKernelMock();
        $command = new CompileCommand($kernel); // Testing default factory instantiation

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
        $this->assertFileExists($this->tempDir . '/assets/css/out.min.css');
        $this->assertFileExists($this->tempDir . '/assets/css/out.css');
        $this->assertFileExists($this->tempDir . '/assets/css/out.min.css.map');
        $this->assertFileExists($this->tempDir . '/assets/css/out.css.map');
    }

    public function testReadableCompilationFailureWithInvalidScss(): void
    {
        $scssDir = $this->tempDir . '/assets/scss';
        @mkdir($scssDir, 0777, true);
        $in = $scssDir . '/invalid.scss';
        // Invalid SCSS that will cause compilation to fail
        file_put_contents($in, '$primary: ; body { color: $undefined_var; }}}}}');

        $kernel = $this->makeKernelMock();
        $command = new CompileCommand($kernel);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:compile'));

        $exit = $tester->execute([
            'input' => 'assets/scss/invalid.scss',
            'output' => 'assets/css/out.min.css',
            '--output-normal' => 'assets/css/out.css',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('SCSS compilation (readable) failed', $tester->getDisplay());
    }

    public function testCompilerNotAvailable(): void
    {
        $scssDir = $this->tempDir . '/assets/scss';
        @mkdir($scssDir, 0777, true);
        $in = $scssDir . '/simple.scss';
        file_put_contents($in, 'body { color: red; }');

        $kernel = $this->makeKernelMock();

        // Create a factory that reports not available
        $factory = new class extends \JBSNewMedia\BootstrapBundle\Service\ScssCompilerFactory {
            public function isAvailable(): bool
            {
                return false;
            }
        };

        $command = new CompileCommand($kernel, $factory);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:compile'));

        $exit = $tester->execute([
            'input' => 'assets/scss/simple.scss',
            'output' => 'assets/css/out.min.css',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Required class ScssPhp\ScssPhp\Compiler not found', $tester->getDisplay());
    }

    public function testFailsToReadInputFile(): void
    {
        $scssDir = $this->tempDir . '/assets/scss';
        @mkdir($scssDir, 0777, true);
        // Create a symlink to a non-existent file (file_exists returns true but cannot be read)
        $in = $scssDir . '/unreadable.scss';
        $target = $scssDir . '/nonexistent_target.scss';
        // Create a broken symlink
        symlink($target, $in);

        $kernel = $this->makeKernelMock();
        $command = new CompileCommand($kernel);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:compile'));

        $exit = $tester->execute([
            'input' => 'assets/scss/unreadable.scss',
            'output' => 'assets/css/out.min.css',
        ]);

        // Broken symlink should trigger "Input file not found" since file_exists returns false for broken symlinks
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Input file not found', $tester->getDisplay());
    }

    public function testFailsToCreateOutputDirectory(): void
    {
        $scssDir = $this->tempDir . '/assets/scss';
        @mkdir($scssDir, 0777, true);
        $in = $scssDir . '/simple.scss';
        file_put_contents($in, 'body { color: red; }');

        // Create a file where the output directory should be
        $blockedDir = $this->tempDir . '/blocked';
        file_put_contents($blockedDir, 'blocking file');

        $kernel = $this->makeKernelMock();
        $command = new CompileCommand($kernel);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:compile'));

        $exit = $tester->execute([
            'input' => 'assets/scss/simple.scss',
            'output' => 'blocked/out.min.css',
            '--output-normal' => 'blocked/out.css',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Failed to create output directory', $tester->getDisplay());
    }

    public function testCompilationReadableFailure(): void
    {
        $scssDir = $this->tempDir . '/assets/scss';
        @mkdir($scssDir, 0777, true);
        $in = $scssDir . '/invalid.scss';
        // Invalid SCSS that should trigger a compilation error
        file_put_contents($in, 'body { color: $non-existent; }');

        $kernel = $this->makeKernelMock();
        $command = new CompileCommand($kernel);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:compile'));

        $exit = $tester->execute([
            'input' => 'assets/scss/invalid.scss',
            'output' => 'assets/css/out.min.css',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('SCSS compilation (readable) failed', $tester->getDisplay());
    }

    public function testMinifiedCompilationFailure(): void
    {
        $scssDir = $this->tempDir . '/assets/scss';
        @mkdir($scssDir, 0777, true);
        $in = $scssDir . '/simple.scss';
        file_put_contents($in, 'body { color: red; }');

        $kernel = $this->makeKernelMock();

        $compilerMock = new class {
            private int $count = 0;
            public function setImportPaths($paths) {}
            public function setSourceMap($mode) {}
            public function setSourceMapOptions($options) {}
            public function setOutputStyle($style)
            {
                if ($style === \ScssPhp\ScssPhp\OutputStyle::COMPRESSED) {
                    $this->count = 1; // Mark that next call should fail
                }
            }
            public function compileString($scss, $path)
            {
                if ($this->count === 1) {
                    throw new \RuntimeException('Minified failed');
                }
                return new class {
                    public function getCss() { return 'body { color: red; }'; }
                    public function getSourceMap() { return ''; }
                };
            }
        };

        $factory = new class($compilerMock) extends \JBSNewMedia\BootstrapBundle\Service\ScssCompilerFactory {
            public function __construct(private $mock) {}
            public function isAvailable(): bool { return true; }
            public function create() { return $this->mock; }
        };

        $command = new CompileCommand($kernel, $factory);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:compile'));

        $exit = $tester->execute([
            'input' => 'assets/scss/simple.scss',
            'output' => 'assets/css/out.min.css',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('SCSS compilation (minified) failed', $tester->getDisplay());
    }

    public function testMakePathRelativeReturnsAbsolutePathIfNoPrefix(): void
    {
        $kernel = $this->makeKernelMock();
        $command = new CompileCommand($kernel);

        $method = new \ReflectionMethod($command, 'makePathRelative');
        $method->setAccessible(true);

        $result = $method->invoke($command, '/some/other/path');
        $this->assertSame('/some/other/path', $result);
    }

    public function testExecuteWithSourceMap(): void
    {
        $scssDir = $this->tempDir . '/assets/scss';
        @mkdir($scssDir, 0777, true);
        $in = $scssDir . '/simple.scss';
        file_put_contents($in, 'body { color: red; }');

        $kernel = $this->makeKernelMock();
        $command = new CompileCommand($kernel);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:compile'));

        $exit = $tester->execute([
            'input' => 'assets/scss/simple.scss',
            'output' => 'assets/css/out.min.css',
            '--source-map' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Source map written:', $tester->getDisplay());

        // ScssPhp generates maps only if they are not empty.
        // With simple CSS, they might be generated. Let's check.
    }

    public function testExecuteWithSpecificInRelHints(): void
    {
        $kernel = $this->makeKernelMock();
        $command = new CompileCommand($kernel);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:compile'));

        // Test hint for bootstrap.scss
        $exit = $tester->execute([
            'input' => 'vendor/twbs/bootstrap/scss/bootstrap.scss',
        ]);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Hint: Make sure the package "twbs/bootstrap" is installed', $tester->getDisplay());

        // Test hint for bootstrap5-custom.scss
        $exit = $tester->execute([
            'input' => 'assets/scss/bootstrap5-custom.scss',
        ]);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Hint: Create the file assets/scss/bootstrap5-custom.scss', $tester->getDisplay());
    }

}
