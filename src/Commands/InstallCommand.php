<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Commands;

use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\Pdf\DriverRegistry;

/**
 * Publishes the config and says what is still needed.
 *
 * The second half is the useful half: this package works only once an optional
 * package is installed and, for Gotenberg, a container is running. Publishing a
 * config file and stopping would leave the reader with a working-looking install
 * and a driver that renders nothing.
 */
final class InstallCommand extends Command
{
    use SupportsNamespacedNames;

    /** @var string */
    protected $signature = 'laranail::pdf.install {--force : Overwrite an existing config file}';

    /** @var string */
    protected $description = 'Publish the laranail/pdf config and report what else is needed.';

    public function handle(DriverRegistry $registry): int
    {
        $this->callSilently('vendor:publish', array_filter([
            '--tag' => 'pdf-config',
            '--force' => (bool) $this->option('force'),
        ]));

        $this->info('Published config/pdf.php.');
        $this->line('');

        $missing = [];

        foreach ($registry->all() as $name => $driver) {
            if ($driver->isAvailable()) {
                $this->line("  <info>✓</info> {$name}");

                continue;
            }

            $missing[] = $name;
            $this->line("  <comment>✗</comment> {$name} — ".$driver->unavailableReason());
        }

        if ($missing === []) {
            $this->line('');
            $this->info('Every driver is ready. Try: php artisan laranail::pdf.doctor --probe');

            return self::SUCCESS;
        }

        $this->line('');
        $this->comment('Next steps');

        if (in_array('gotenberg', $missing, true)) {
            $this->line('  composer require gotenberg/gotenberg-php guzzlehttp/guzzle');
            $this->line('  docker run --rm -p 3000:3000 gotenberg/gotenberg:8');
        }

        if (in_array('dompdf', $missing, true)) {
            $this->line('  composer require dompdf/dompdf');
        }

        $this->line('');
        $this->line('Then: php artisan laranail::pdf.doctor');

        // Not a failure. A fresh install with nothing else installed yet is the
        // expected state, and a non-zero exit here would break a scripted setup
        // that runs install before composer require.
        return self::SUCCESS;
    }
}
