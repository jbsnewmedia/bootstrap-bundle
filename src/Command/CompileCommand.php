<?php

declare(strict_types=1);

namespace JBSNewMedia\BootstrapBundle\Command;

use ScssPhp\ScssPhp\Compiler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'bootstrap:compile',
    description: 'Compile SCSS to CSS (default: assets/scss -> assets/css, imports Bootstrap from vendor)'
)]
class CompileCommand extends Command
{
    private string $projectDir;

    public function __construct(KernelInterface $kernel)
    {
        parent::__construct();
        $this->projectDir = $kernel->getProjectDir();
    }
    protected function configure(): void
    {
        $this
            ->addArgument('input', InputArgument::OPTIONAL, 'Input SCSS entry file (relative to project root)', 'assets/scss/bootstrap5-custom.scss')
            ->addArgument('output', InputArgument::OPTIONAL, 'Output CSS file (relative to project root)', 'assets/css/bootstrap.min.css')
            ->addOption('source-map', null, InputOption::VALUE_NONE, 'Generate source map alongside the CSS');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inRel = (string) $input->getArgument('input');
        $outRel = (string) $input->getArgument('output');
        $in = $this->projectDir . DIRECTORY_SEPARATOR . $inRel;
        $out = $this->projectDir . DIRECTORY_SEPARATOR . $outRel;

        if (!file_exists($in)) {
            $output->writeln("<error>Input file not found: {$inRel}</error>");
            if ($inRel === 'vendor/twbs/bootstrap/scss/bootstrap.scss') {
                $output->writeln('<comment>Hint: Make sure the package "twbs/bootstrap" is installed (composer require twbs/bootstrap) and the path is correct.</comment>');
            }
            if ($inRel === 'assets/scss/bootstrap5-custom.scss') {
                $output->writeln('<comment>Hint: Create the file assets/scss/bootstrap5-custom.scss (override variables, then add "@import \"bootstrap\";") or explicitly use the vendor entry: vendor/twbs/bootstrap/scss/bootstrap.scss</comment>');
            }
            return Command::FAILURE;
        }

        $outDir = dirname($out);
        if (!is_dir($outDir)) {
            if (!mkdir($outDir, 0777, true) && !is_dir($outDir)) {
                $output->writeln('<error>Failed to create output directory: ' . $outDir . '</error>');
                return Command::FAILURE;
            }
        }

        $compiler = new Compiler();
        $compiler->setImportPaths([
            $this->projectDir . '/vendor/twbs/bootstrap/scss',
            $this->projectDir . '/vendor',
            $this->projectDir . '/assets/scss',
            $this->projectDir . '/assets',
        ]);

        $compiler->setOutputStyle(\ScssPhp\ScssPhp\OutputStyle::COMPRESSED);

        $sourceMap = (bool) $input->getOption('source-map');
        if ($sourceMap) {
            $compiler->setSourceMap(Compiler::SOURCE_MAP_FILE);
            $compiler->setSourceMapOptions([
                'sourceMapWriteTo' => $out . '.map',
                'sourceMapURL' => basename($out) . '.map',
                'sourceMapFilename' => basename($out),
                'sourceMapBasepath' => $this->projectDir,
                'sourceRoot' => '/',
            ]);
        }

        $scss = file_get_contents($in);
        if ($scss === false) {
            $output->writeln('<error>Failed to read input file.</error>');
            return Command::FAILURE;
        }

        try {
            $result = $compiler->compileString($scss, $in);
            $css = $result->getCss();
        } catch (\Throwable $e) {
            $output->writeln('<error>SCSS compilation failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        file_put_contents($out, $css);
        $output->writeln('<info>Compiled ' . $inRel . ' -> ' . $outRel . '</info>');

        if ($sourceMap ?? false) {
            $mapContent = $result->getSourceMap();
            if (is_string($mapContent) && $mapContent !== '') {
                $mapPath = $out . '.map';
                file_put_contents($mapPath, $mapContent);
                $output->writeln('<info>Source map written: ' . $this->makePathRelative($mapPath) . '</info>');
            } else {
                $output->writeln('<comment>Hinweis: Der Compiler lieferte keinen Source-Map-Inhalt zurück. Prüfen Sie Ihre SCSS-Quelldateien und Optionen.</comment>');
            }
        }

        return Command::SUCCESS;
    }

    private function makePathRelative(string $absPath): string
    {
        $prefix = rtrim($this->projectDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($absPath, $prefix)) {
            return substr($absPath, strlen($prefix));
        }
        return $absPath;
    }
}
