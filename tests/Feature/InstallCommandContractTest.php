<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Artisan;
use Simtabi\Laranail\Pdf\DriverRegistry;
use Simtabi\Laranail\Pdf\Tests\TestCase;
use Simtabi\Laranail\Pdf\Commands\InstallCommand;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\InteractsWithConsoleWriter;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\InteractsWithConsoleServices;
use Simtabi\Laranail\Package\Tools\Commands\InstallCommand as PackageToolsInstallCommand;

/**
 * The install command's observable surface, pinned.
 *
 * Its base class moved from laranail/console's `Command` to laranail/package-tools'
 * `InstallCommand`, with console's display API kept by `use`-ing its two traits. A base swap is
 * where a name, an option, the listing visibility or a line of output changes without anyone
 * deciding it should, so every one of those is asserted here against what the command did before.
 */
final class InstallCommandContractTest extends TestCase
{
    #[Test]
    public function it_keeps_its_name_aliases_description_and_listing_visibility(): void
    {
        $command = $this->installCommand();

        self::assertSame('laranail::pdf.install', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame('Publish the laranail/pdf config and report what else is needed.', $command->getDescription());
        self::assertFalse($command->isHidden());
    }

    #[Test]
    public function it_keeps_exactly_one_option_of_its_own_and_no_arguments(): void
    {
        $definition = $this->installCommand()->getNativeDefinition();

        self::assertSame(['force'], array_keys($definition->getOptions()));
        self::assertSame([], $definition->getArguments());

        $option = $definition->getOption('force');

        self::assertFalse($option->acceptValue());
        self::assertSame('Overwrite an existing config file', $option->getDescription());
    }

    #[Test]
    public function it_publishes_the_config_and_reports_every_driver(): void
    {
        $this->artisan('laranail::pdf.install')
            ->expectsOutputToContain('Published config/pdf.php.')
            ->expectsOutputToContain('gotenberg')
            ->expectsOutputToContain('dompdf')
            ->expectsOutputToContain($this->closingLine())
            ->assertExitCode(0);
    }

    #[Test]
    public function it_accepts_force_and_still_exits_zero(): void
    {
        $this->artisan('laranail::pdf.install', ['--force' => true])
            ->expectsOutputToContain('Published config/pdf.php.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_names_the_next_steps_when_a_driver_is_unavailable(): void
    {
        config()->set('laranail.pdf.drivers.gotenberg.base_url', '');
        $this->app->make(DriverRegistry::class)->flush();

        $this->artisan('laranail::pdf.install')
            ->expectsOutputToContain('Next steps')
            ->expectsOutputToContain('Then: php artisan laranail::pdf.doctor')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_extends_the_package_tools_install_base_and_keeps_both_console_traits(): void
    {
        self::assertTrue(is_subclass_of(InstallCommand::class, PackageToolsInstallCommand::class));

        $traits = class_uses(InstallCommand::class);

        self::assertArrayHasKey(InteractsWithConsoleServices::class, $traits);
        self::assertArrayHasKey(InteractsWithConsoleWriter::class, $traits);
    }

    private function installCommand(): InstallCommand
    {
        $command = Artisan::all()['laranail::pdf.install'] ?? null;

        self::assertInstanceOf(InstallCommand::class, $command);

        return $command;
    }

    /**
     * The last line depends on what happens to be installed, which differs between the full CI
     * job and the optional-dependencies-removed one. Either way the command must end on one of
     * these two lines.
     */
    private function closingLine(): string
    {
        foreach ($this->app->make(DriverRegistry::class)->all() as $driver) {
            if (! $driver->isAvailable()) {
                return 'Then: php artisan laranail::pdf.doctor';
            }
        }

        return 'Every driver is ready. Try: php artisan laranail::pdf.doctor --probe';
    }
}
