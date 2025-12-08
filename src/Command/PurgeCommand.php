<?php

declare(strict_types=1);

namespace JBSNewMedia\BootstrapBundle\Command;

use JBSNewMedia\BootstrapBundle\Service\PurgeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'bootstrap:purge',
    description: 'Purge Bootstrap CSS by scanning templates and keeping only used selectors.'
)]
class PurgeCommand extends Command
{
    private string $projectDir;
    public function __construct(
        KernelInterface $kernel,
        private readonly PurgeService $purgeService,
    ) {
        // Initialize projectDir BEFORE calling parent constructor because
        // Symfony calls configure() from Command::__construct(), and our
        // configure() relies on $this->projectDir for default option values.
        $this->projectDir = $kernel->getProjectDir();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('input', 'i', InputOption::VALUE_REQUIRED, 'Path to input CSS file', $this->projectDir . '/assets/css/bootstrap.min.css')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Path to write the purged CSS file', $this->projectDir . '/assets/css/bootstrap-purged.css')
            ->addOption('templates-dir', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Template directories to scan (multiple allowed)', [$this->projectDir . '/templates'])
            ->addOption('include-dir', 'D', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Additional directories to scan for selectors (multiple allowed)')
            ->addOption('include-file', 'F', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Additional files to scan for selectors (multiple allowed)')
            ->addOption('selector', 'S', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Selectors to always keep (multiple allowed)')
            ->addOption('readable', 'r', InputOption::VALUE_NONE, 'Generate human-readable (pretty) CSS output')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write output, only show statistics');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cssInput = (string)$input->getOption('input');
        $cssOutput = (string)$input->getOption('output');
        $templatesDirs = (array)$input->getOption('templates-dir');
        $includeDirs = (array)$input->getOption('include-dir');
        $includeFiles = (array)$input->getOption('include-file');
        $extraSelectors = (array)$input->getOption('selector');
        $readable = (bool)$input->getOption('readable');
        $dryRun = (bool)$input->getOption('dry-run');

        $pathsToScan = [];
        foreach ([...$templatesDirs, ...$includeDirs] as $dir) {
            if (is_string($dir) && $dir !== '' && is_dir($dir)) {
                $pathsToScan[] = $dir;
            }
        }
        foreach ($includeFiles as $file) {
            if (is_string($file) && $file !== '' && is_file($file)) {
                $pathsToScan[] = $file;
            }
        }

        [$keptSelectors, $purgedCss, $stats] = $this->purgeService->purge(
            cssPath: $cssInput,
            pathsToScan: $pathsToScan,
            extraSelectors: $extraSelectors,
            readable: $readable,
        );

        $output->writeln(sprintf('Found %d selectors in sources; keeping %d after normalization.', $stats['found'] ?? 0, count($keptSelectors)));
        $output->writeln(sprintf('Input CSS: %s (%s)', $cssInput, is_file($cssInput) ? (string)filesize($cssInput) . ' bytes' : 'missing'));
        $output->writeln(sprintf('Output CSS: %s%s', $cssOutput, $dryRun ? ' (dry-run, not written)' : ''));

        // Report: show all detected/kept selectors (includes HTML tags, classes, ids, attributes)
        if (!empty($keptSelectors)) {
            $output->writeln('Selectors found (normalized):');
            foreach ($keptSelectors as $sel) {
                $output->writeln('  - ' . $sel);
            }
        } else {
            $output->writeln('Selectors found (normalized): none');
        }

        if (!$dryRun) {
            if (!is_dir(dirname($cssOutput))) {
                @mkdir(dirname($cssOutput), 0777, true);
            }
            file_put_contents($cssOutput, $purgedCss);
        }

        return Command::SUCCESS;
    }
}
