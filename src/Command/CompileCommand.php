<?php

declare(strict_types=1);

namespace App\Command;

use ScssPhp\ScssPhp\Compiler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'boostrap:compile',
    description: 'SCSS kompilieen (Standard: assets/scss -> assets/css, importiert Bootstrap aus vendor)',
)]
class CompileCommand extends Command
{
    protected function configure(): void
    {
        $this
            // Default: nutze projektspezifische SCSS-Datei, die Bootstrap konfiguriert
            ->addArgument('input', InputArgument::OPTIONAL, 'Input SCSS entry file relative to project root', 'assets/scss/bootstrap5-custom.scss')
            // Standard-Ausgabe: in assets/css kompilieren
            ->addArgument('output', InputArgument::OPTIONAL, 'Output CSS file relative to project root', 'assets/css/bootstrap.min.css')
            ->addOption('source-map', null, InputOption::VALUE_NONE, 'Generate source map alongside the CSS');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectDir = dirname(__DIR__, 2);
        $inRel = (string) $input->getArgument('input');
        $outRel = (string) $input->getArgument('output');
        $in = $projectDir . DIRECTORY_SEPARATOR . $inRel;
        $out = $projectDir . DIRECTORY_SEPARATOR . $outRel;

        if (!file_exists($in)) {
            $output->writeln("<error>Input file not found: {$inRel}</error>");
            if ($inRel === 'vendor/twbs/bootstrap/scss/bootstrap.scss') {
                $output->writeln('<comment>Hinweis: Stelle sicher, dass das Paket "twbs/bootstrap" installiert ist (composer require twbs/bootstrap) und der Pfad korrekt ist.</comment>');
            }
            if ($inRel === 'assets/scss/bootstrap5-custom.scss') {
                $output->writeln('<comment>Hinweis: Lege die Datei assets/scss/bootstrap5-custom.scss an (Variablen überschreiben und anschließend "@import \"bootstrap\";") oder nutze explizit den Vendor-Einstieg: vendor/twbs/bootstrap/scss/bootstrap.scss</comment>');
            }
            return Command::FAILURE;
        }

        // Ensure output dir exists
        $outDir = dirname($out);
        if (!is_dir($outDir)) {
            if (!mkdir($outDir, 0777, true) && !is_dir($outDir)) {
                $output->writeln('<error>Failed to create output directory: ' . $outDir . '</error>');
                return Command::FAILURE;
            }
        }

        $compiler = new Compiler();
        // Include paths to resolve imports
        $compiler->setImportPaths([
            // Priorisiere Vendor-Bootstrap-SCSS
            $projectDir . '/vendor/twbs/bootstrap/scss',
            $projectDir . '/vendor',
            // Projektpfade (falls eigene Overrides vorhanden sind)
            $projectDir . '/assets/scss',
            $projectDir . '/assets',
            // Optional: Falls Bootstrap via npm/yarn vorhanden ist
            $projectDir . '/node_modules',
        ]);

        // Optional: output style compressed in prod
        $compiler->setOutputStyle(\ScssPhp\ScssPhp\OutputStyle::COMPRESSED);

        $sourceMap = (bool) $input->getOption('source-map');
        if ($sourceMap) {
            $compiler->setSourceMap(\ScssPhp\ScssPhp\SourceMap\SourceMapGenerator::SOURCE_MAP_FILE);
            $compiler->setSourceMapOptions([
                'sourceMapWriteTo' => $out . '.map',
                'sourceMapURL' => basename($out) . '.map',
                'sourceMapFilename' => basename($out),
                'sourceMapBasepath' => $projectDir,
                'sourceRoot' => '/',
            ]);
        }

        $scss = file_get_contents($in);
        if ($scss === false) {
            $output->writeln('<error>Failed to read input file.</error>');
            return Command::FAILURE;
        }

        try {
            $css = $compiler->compileString($scss, $in)->getCss();
        } catch (\Throwable $e) {
            $output->writeln('<error>SCSS compilation failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        file_put_contents($out, $css);
        $output->writeln('<info>Compiled ' . $inRel . ' -> ' . $outRel . '</info>');

        return Command::SUCCESS;
    }
}
