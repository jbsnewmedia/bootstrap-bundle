<?php

declare(strict_types=1);

namespace JBSNewMedia\BootstrapBundle\Tests\Command;

use JBSNewMedia\BootstrapBundle\Command\InitCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

class InitCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/bb_init_cmd_'.uniqid();
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

    public function testExecuteCreatesDirectoryAndFiles(): void
    {
        $kernel = $this->makeKernelMock();
        $command = new InitCommand($kernel);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:init'));

        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('Created directory: assets/scss', $display);
        $this->assertStringContainsString('Created:', $display);
        $this->assertStringContainsString('bootstrap5-custom.scss', $display);
        $this->assertStringContainsString('bootstrap5-custom-dark.scss', $display);
        $this->assertStringContainsString('Done.', $display);

        $this->assertFileExists($this->tempDir.'/assets/scss/bootstrap5-custom.scss');
        $this->assertFileExists($this->tempDir.'/assets/scss/bootstrap5-custom-dark.scss');

        // Verify content contains expected SCSS
        $lightContent = file_get_contents($this->tempDir.'/assets/scss/bootstrap5-custom.scss');
        $this->assertStringContainsString('@import "bootstrap"', $lightContent);
        $this->assertStringContainsString('$primary:', $lightContent);

        $darkContent = file_get_contents($this->tempDir.'/assets/scss/bootstrap5-custom-dark.scss');
        $this->assertStringContainsString('@import "bootstrap"', $darkContent);
        $this->assertStringContainsString('$body-bg:', $darkContent);
    }

    public function testExecuteDryRunMode(): void
    {
        $kernel = $this->makeKernelMock();
        $command = new InitCommand($kernel);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:init'));

        $exitCode = $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('[dry-run] Would create directory: assets/scss', $display);
        $this->assertStringContainsString('[dry-run] Would create:', $display);
        $this->assertStringContainsString('Dry-run complete. No files were written.', $display);

        // Files should not exist
        $this->assertFileDoesNotExist($this->tempDir.'/assets/scss/bootstrap5-custom.scss');
        $this->assertFileDoesNotExist($this->tempDir.'/assets/scss/bootstrap5-custom-dark.scss');
    }

    public function testExecuteSkipsExistingFilesWithoutForce(): void
    {
        // Create directory and files first
        $scssDir = $this->tempDir.'/assets/scss';
        mkdir($scssDir, 0777, true);
        file_put_contents($scssDir.'/bootstrap5-custom.scss', 'existing content');
        file_put_contents($scssDir.'/bootstrap5-custom-dark.scss', 'existing dark content');

        $kernel = $this->makeKernelMock();
        $command = new InitCommand($kernel);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:init'));

        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('Skip existing:', $display);
        $this->assertStringContainsString('(use --force to overwrite)', $display);
        $this->assertStringContainsString('Skipped: 2', $display);

        // Files should still have original content
        $this->assertSame('existing content', file_get_contents($scssDir.'/bootstrap5-custom.scss'));
        $this->assertSame('existing dark content', file_get_contents($scssDir.'/bootstrap5-custom-dark.scss'));
    }

    public function testExecuteOverwritesWithForce(): void
    {
        // Create directory and files first
        $scssDir = $this->tempDir.'/assets/scss';
        mkdir($scssDir, 0777, true);
        file_put_contents($scssDir.'/bootstrap5-custom.scss', 'old content');
        file_put_contents($scssDir.'/bootstrap5-custom-dark.scss', 'old dark content');

        $kernel = $this->makeKernelMock();
        $command = new InitCommand($kernel);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:init'));

        $exitCode = $tester->execute(['--force' => true]);

        $this->assertSame(0, $exitCode);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('Overwrote:', $display);
        $this->assertStringContainsString('Overwritten: 2', $display);

        // Files should have new content
        $this->assertStringContainsString('@import "bootstrap"', file_get_contents($scssDir.'/bootstrap5-custom.scss'));
        $this->assertStringContainsString('@import "bootstrap"', file_get_contents($scssDir.'/bootstrap5-custom-dark.scss'));
    }

    public function testExecuteDryRunWithExistingFiles(): void
    {
        // Create directory and files first
        $scssDir = $this->tempDir.'/assets/scss';
        mkdir($scssDir, 0777, true);
        file_put_contents($scssDir.'/bootstrap5-custom.scss', 'existing');

        $kernel = $this->makeKernelMock();
        $command = new InitCommand($kernel);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:init'));

        $exitCode = $tester->execute(['--dry-run' => true, '--force' => true]);

        $this->assertSame(0, $exitCode);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('[dry-run] Would overwrite:', $display);
        $this->assertStringContainsString('[dry-run] Would create:', $display);

        // Files should not be modified
        $this->assertSame('existing', file_get_contents($scssDir.'/bootstrap5-custom.scss'));
    }

    public function testExecuteFailsOnMkdirError(): void
    {
        // Create a file where the directory should be
        $blockedPath = $this->tempDir.'/assets';
        file_put_contents($blockedPath, 'blocking file');

        $kernel = $this->makeKernelMock();
        $command = new InitCommand($kernel);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:init'));

        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('Failed to create directory: assets/scss', $display);
    }

    public function testExecuteFailsOnWriteError(): void
    {
        // Create a read-only directory
        $scssDir = $this->tempDir.'/assets/scss';
        mkdir($scssDir, 0777, true);
        // Create a directory where the file should be written
        mkdir($scssDir.'/bootstrap5-custom.scss', 0777, true);

        $kernel = $this->makeKernelMock();
        $command = new InitCommand($kernel);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:init'));

        $exitCode = $tester->execute(['--force' => true]);

        $this->assertSame(1, $exitCode);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('Failed to write file:', $display);
    }

    public function testCommandHasCorrectDescription(): void
    {
        $kernel = $this->makeKernelMock();
        $command = new InitCommand($kernel);

        $this->assertSame('bootstrap:init', $command->getName());
        $this->assertStringContainsString('Scaffold Bootstrap SCSS', $command->getDescription());
    }

    public function testCommandAliasWorks(): void
    {
        $kernel = $this->makeKernelMock();
        $command = new InitCommand($kernel);

        $aliases = $command->getAliases();
        $this->assertContains('boostrap:init', $aliases);
    }
}
