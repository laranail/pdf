<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Pdf\Contracts\PdfDriver;
use Simtabi\Laranail\Pdf\DriverRegistry;
use Simtabi\Laranail\Pdf\Enums\Capability;
use Simtabi\Laranail\Pdf\Exceptions\DriverUnavailable;
use Simtabi\Laranail\Pdf\Tests\TestCase;

/**
 * What happens when the rendering engines are not installed.
 *
 * The CI job that removes them runs this. An optional dependency nobody tests
 * the absence of is a required one that has not failed yet — and the failure,
 * when it comes, is a fatal error on a missing class at boot rather than a
 * message telling somebody what to install.
 */
final class AbsentDependenciesTest extends TestCase
{
    #[Test]
    public function the_application_boots_with_neither_engine_installed(): void
    {
        // Not conditional on the packages being absent: booting has to work
        // either way, and this is the assertion that would fail loudly if a
        // provider ever started touching a vendor class eagerly.
        self::assertInstanceOf(DriverRegistry::class, $this->app->make(DriverRegistry::class));
        self::assertInstanceOf(PdfDriver::class, $this->app->make(PdfDriver::class));
    }

    #[Test]
    public function both_drivers_are_still_registered(): void
    {
        $registry = $this->app->make(DriverRegistry::class);

        self::assertTrue($registry->has('gotenberg'));
        self::assertTrue($registry->has('dompdf'));
    }

    #[Test]
    public function a_driver_can_be_constructed_and_asked_without_its_package(): void
    {
        // Construction and introspection must never touch the vendor class.
        // This is what lets `doctor` report the problem instead of dying of it.
        $registry = $this->app->make(DriverRegistry::class);

        foreach (['gotenberg', 'dompdf'] as $name) {
            $driver = $registry->driver($name);

            self::assertSame($name, $driver->name());
            self::assertIsBool($driver->isAvailable());
            self::assertNotSame([], $driver->capabilities());
        }
    }

    #[Test]
    public function capability_reporting_does_not_depend_on_the_package(): void
    {
        // instanceof against an interface this package owns, so the answer is
        // the same whether or not the engine is installed.
        $registry = $this->app->make(DriverRegistry::class);

        self::assertTrue($registry->driver('gotenberg')->supports(Capability::Merge));
        self::assertFalse($registry->driver('dompdf')->supports(Capability::Merge));
    }

    #[Test]
    public function an_absent_package_names_itself_in_the_reason(): void
    {
        $registry = $this->app->make(DriverRegistry::class);

        foreach (['gotenberg' => 'gotenberg/gotenberg-php', 'dompdf' => 'dompdf/dompdf'] as $name => $package) {
            $driver = $registry->driver($name);

            if ($driver->isAvailable()) {
                // The other half of the contract, and the half that runs when
                // the packages ARE installed: available must mean no reason,
                // or `doctor` prints a complaint next to a working driver.
                self::assertNull(
                    $driver->unavailableReason(),
                    "{$name} reports available and still gives a reason.",
                );

                continue;
            }

            $reason = (string) $driver->unavailableReason();

            // Either the package is missing or it is present but unconfigured;
            // both must say which, and say it in a form somebody can act on.
            self::assertNotSame('', $reason, "{$name} is unavailable without saying why.");
            self::assertTrue(
                str_contains($reason, $package) || str_contains($reason, 'base URL') || str_contains($reason, 'HTTP client'),
                "{$name}'s reason does not name {$package} or the missing configuration: {$reason}",
            );
        }
    }

    #[Test]
    public function rendering_without_the_package_throws_rather_than_fataling(): void
    {
        $registry = $this->app->make(DriverRegistry::class);
        $driver = $registry->forHtml('dompdf');

        if ($driver->isAvailable()) {
            self::markTestSkipped('dompdf/dompdf is installed; this asserts the absent case.');
        }

        $this->expectException(DriverUnavailable::class);

        $driver->html('<p>hi</p>')->contents();
    }

    #[Test]
    public function doctor_runs_and_explains_itself_with_nothing_installed(): void
    {
        // The command has to work in exactly the situation it exists to
        // diagnose. It exits non-zero when nothing is usable, which is correct
        // for a gate — what matters is that it produces a report at all.
        $registry = $this->app->make(DriverRegistry::class);
        $anyAvailable = false;

        foreach ($registry->all() as $driver) {
            $anyAvailable = $anyAvailable || $driver->isAvailable();
        }

        $this->artisan('laranail::pdf.doctor')
            ->expectsOutputToContain('gotenberg')
            ->assertExitCode($anyAvailable ? 0 : 1);
    }

    #[Test]
    public function install_still_reports_next_steps(): void
    {
        $this->artisan('laranail::pdf.install')->assertExitCode(0);
    }
}
