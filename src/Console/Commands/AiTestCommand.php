<?php

namespace AIToolkit\AIToolkit\Console\Commands;

use Illuminate\Console\Command;

class AiTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:test 
                            {--coverage : Run tests with coverage}
                            {--html : Generate HTML coverage report}
                            {--watch : Watch files for changes and re-run tests}
                            {--parallel : Run tests in parallel}
                            {--group= : Run only tests from a specific group}
                            {--exclude-group= : Exclude tests from a specific group}
                            {--filter= : Filter tests by name pattern}
                            {--verbose : Verbose output}
                            {--failing-tests : Show only failing tests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run AI toolkit tests with various options';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧪 Running AI Toolkit Tests...');

        $command = $this->buildTestCommand();

        $this->info("Executing: $command");
        $this->newLine();

        passthru($command, $exitCode);

        if ($exitCode === 0) {
            $this->newLine();
            $this->info('✅ All tests passed!');
        } else {
            $this->newLine();
            $this->error('❌ Some tests failed.');
        }

        return $exitCode;
    }

    /**
     * Build the test command based on options.
     */
    private function buildTestCommand(): string
    {
        $baseCommand = 'vendor/bin/pest';
        $options = [];

        // Coverage options
        if ($this->option('coverage')) {
            $options[] = '--coverage';
        }

        if ($this->option('html')) {
            $options[] = '--coverage-html=coverage';
        }

        // Test execution options
        if ($this->option('watch')) {
            $options[] = '--watch';
        }

        if ($this->option('parallel')) {
            $options[] = '--parallel';
        }

        // Filtering options
        if ($group = $this->option('group')) {
            $options[] = "--group=$group";
        }

        if ($excludeGroup = $this->option('exclude-group')) {
            $options[] = "--exclude-group=$excludeGroup";
        }

        if ($filter = $this->option('filter')) {
            $options[] = "--filter=$filter";
        }

        // Output options
        if ($this->option('verbose')) {
            $options[] = '--verbose';
        }

        if ($this->option('failing-tests')) {
            $options[] = '--failing-tests';
        }

        return trim($baseCommand.' '.implode(' ', $options));
    }

    /**
     * Show available test groups.
     */
    protected function showTestGroups(): void
    {
        $this->info('Available test groups:');

        $groups = [
            'unit' => 'Unit tests for individual components',
            'feature' => 'Feature tests for complete workflows',
            'cache' => 'Cache service tests',
            'security' => 'Security service tests',
            'monitoring' => 'Monitoring service tests',
            'provider' => 'AI provider tests',
            'integration' => 'Integration tests',
        ];

        foreach ($groups as $group => $description) {
            $this->line("  <comment>$group</comment> - $description");
        }

        $this->newLine();
        $this->info('Usage examples:');
        $this->line('  php artisan ai:test --group=cache');
        $this->line('  php artisan ai:test --coverage --group=security');
        $this->line('  php artisan ai:test --filter="test_.*_cache"');
    }
}
