<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Commands;

use Simtabi\Laranail\Pdf\DriverRegistry;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\InteractsWithConsoleWriter;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\InteractsWithConsoleServices;
use Simtabi\Laranail\Package\Tools\Commands\InstallCommand as PackageToolsInstallCommand;

/**
 * Publishes the config and says what is still needed.
 *
 * The second half is the useful half: this package works only once an optional
 * package is installed and, for Gotenberg, a container is running. Publishing a
 * config file and stopping would leave the reader with a working-looking install
 * and a driver that renders nothing.
 *
 * The base is package-tools' install command, which carries the `::` name support. laranail/console's
 * display API and managed run lifecycle come from its two traits rather than its base class, so
 * neither package has to depend on the other. `handle()` is this command's own: the base's generic
 * publish pipeline would print different steps, and the output here is the contract.
 */
final class InstallCommand extends PackageToolsInstallCommand
{
    use InteractsWithConsoleServices;
    use InteractsWithConsoleWriter;

    public const string SIGNATURE = 'laranail::pdf.install {--force : Overwrite an existing config file}';

    public const string DESCRIPTION = 'Publish the laranail/pdf config and report what else is needed.';

    public function __construct(Package $package)
    {
        // Listed in `php artisan list`, as it always has been: the base hides install commands by
        // default, so visibility is passed explicitly rather than inherited.
        parent::__construct($package, self::SIGNATURE, hidden: false);

        // The base writes `Install {package}` as the description during construction; restore the
        // one this command has always shown. Both the property and Symfony's copy are set, because
        // the parent constructor has already pushed the property through setDescription().
        $this->description = self::DESCRIPTION;
        $this->setDescription(self::DESCRIPTION);

        // Booted eagerly, as console's own base does, so `$this->services` exists straight after
        // construction rather than only once run() has been entered.
        $this->bootConsoleSupport();
    }

    /**
     * The registry is still injected by the container when Artisan calls this. It is optional
     * only because the base declares `handle(): int`, and a required parameter would not be a
     * compatible override.
     */
    public function handle(?DriverRegistry $registry = null): int
    {
        $registry ??= $this->laravel->make(DriverRegistry::class);

        $this->callSilently('vendor:publish', array_filter([
            '--tag'   => 'pdf-config',
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
            $this->line("  <comment>✗</comment> {$name} — " . $driver->unavailableReason());
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
