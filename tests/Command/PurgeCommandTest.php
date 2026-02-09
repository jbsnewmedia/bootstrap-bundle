<?php

declare(strict_types=1);

namespace JBSNewMedia\BootstrapBundle\Tests\Command;

use JBSNewMedia\BootstrapBundle\Command\PurgeCommand;
use JBSNewMedia\BootstrapBundle\Service\PurgeService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

class PurgeCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bb_purge_cmd_' . uniqid();
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

    public function testDryRunOutputsStatsAndNoFileWritten(): void
    {
        $css = "\n}\n.keep{color:red;}\n}\n";
        $cssPath = $this->tempDir . '/assets/css/bootstrap.css';
        @mkdir(dirname($cssPath), 0777, true);
        file_put_contents($cssPath, $css);

        $tplDir = $this->tempDir . '/templates';
        mkdir($tplDir);
        file_put_contents($tplDir.'/index.html.twig', '<div class="keep"></div>');

        $kernel = $this->makeKernelMock();
        $service = new PurgeService();
        $command = new PurgeCommand($kernel, $service);

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:purge'));

        $outPath = $this->tempDir . '/assets/css/output.css';
        $exitCode = $tester->execute([
            '--input' => $cssPath,
            '--output' => $outPath,
            '--templates-dir' => [$tplDir],
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Selectors found (normalized):', $display);
        $this->assertStringContainsString('Input CSS:', $display);
        $this->assertStringContainsString('(dry-run, not written)', $display);
        $this->assertFileDoesNotExist($outPath);
    }

    public function testPurgeCommandBasic(): void
    {
        $css = "\n}\n.keep{color:red;}\n}\n";
        $cssPath = $this->tempDir . '/in.css';
        file_put_contents($cssPath, $css);

        // Add scan paths to cover includes
        $incDir = $this->tempDir . '/inc';
        mkdir($incDir);
        file_put_contents($incDir.'/inc.html', '<div class="keep"></div>');

        $kernel = $this->makeKernelMock();
        $service = new PurgeService();
        $command = new PurgeCommand($kernel, $service);
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:purge'));

        $outPath = $this->tempDir . '/out.css';
        $exitCode = $tester->execute([
            '--input' => $cssPath,
            '--output' => $outPath,
            '--selector' => ['.keep'],
            '--include-dir' => [$incDir],
            '--readable' => true,
        ]);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('HTML tags found:', $tester->getDisplay());
        $this->assertStringContainsString('- div', $tester->getDisplay());
    }

    public function testPurgeCommandWithTags(): void
    {
        $css = "\n}\nbody{color:red;}\n}\n";
        $cssPath = $this->tempDir . '/in.css';
        file_put_contents($cssPath, $css);
        $srcFile = $this->tempDir . '/source.html';
        file_put_contents($srcFile, '<body></body>');

        $kernel = $this->makeKernelMock();
        $service = new PurgeService();
        $command = new PurgeCommand($kernel, $service);
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:purge'));

        $outPath = $this->tempDir . '/out.css';
        $exitCode = $tester->execute([
            '--input' => $cssPath,
            '--output' => $outPath,
            '--include-file' => [$srcFile],
        ]);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('HTML tags found:', $tester->getDisplay());
        $this->assertStringContainsString('- body', $tester->getDisplay());
    }

    public function testPurgeCommandNoSelectors(): void
    {
        $css = "\n}\n.drop{color:red;}\n}\n";
        $cssPath = $this->tempDir . '/in.css';
        file_put_contents($cssPath, $css);

        $kernel = $this->makeKernelMock();
        $service = new PurgeService();
        $command = new PurgeCommand($kernel, $service);
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:purge'));

        $outPath = $this->tempDir . '/out.css';
        $exitCode = $tester->execute([
            '--input' => $cssPath,
            '--output' => $outPath,
        ]);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Selectors found (normalized): none', $tester->getDisplay());
    }

    public function testPurgeCommandFailsToCreateDir(): void
    {
        $css = "\n}\n.keep{color:red;}\n}\n";
        $cssPath = $this->tempDir . '/in.css';
        file_put_contents($cssPath, $css);

        // Create a file where directory should be
        $outDir = $this->tempDir . '/blocked_dir';
        file_put_contents($outDir, 'blocked');
        $outPath = $outDir . '/out.css';

        $kernel = $this->makeKernelMock();
        $service = new PurgeService();
        $command = new PurgeCommand($kernel, $service);
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:purge'));

        $exitCode = $tester->execute([
            '--input' => $cssPath,
            '--output' => $outPath,
        ]);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed to create output directory', $tester->getDisplay());
    }
    public function testPurgeCommandFailsToWriteFile(): void
    {
        $css = "\n}\n.keep{color:red;}\n}\n";
        $cssPath = $this->tempDir . '/in.css';
        file_put_contents($cssPath, $css);

        // Create a directory where the output file should be
        $outPath = $this->tempDir . '/blocked_file';
        mkdir($outPath);

        $kernel = $this->makeKernelMock();
        $service = new PurgeService();
        $command = new PurgeCommand($kernel, $service);
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:purge'));

        $exitCode = $tester->execute([
            '--input' => $cssPath,
            '--output' => $outPath,
        ]);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed to write output file', $tester->getDisplay());
    }

    public function testPurgeCommandWithSelectorsButNoHtmlTags(): void
    {
        // CSS with only class selectors, no HTML tags
        $css = "\n}\n.keep{color:red;}\n}\n#myid{color:blue;}\n}\n";
        $cssPath = $this->tempDir . '/in.css';
        file_put_contents($cssPath, $css);

        $kernel = $this->makeKernelMock();
        $service = new PurgeService();
        $command = new PurgeCommand($kernel, $service);
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('bootstrap:purge'));

        $outPath = $this->tempDir . '/out.css';
        $exitCode = $tester->execute([
            '--input' => $cssPath,
            '--output' => $outPath,
            '--selector' => ['.keep', '#myid'], // Only class and ID selectors, no tags
        ]);
        $this->assertSame(0, $exitCode);
        $display = $tester->getDisplay();

        // Should show selectors but no HTML tags
        $this->assertStringContainsString('Selectors found (normalized):', $display);
        $this->assertStringContainsString('HTML tags found: none', $display);
    }
}
