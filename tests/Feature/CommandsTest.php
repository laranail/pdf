<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Tests\Feature;

use Simtabi\Laranail\Pdf\PdfManager;
use Simtabi\Laranail\Pdf\Facades\Pdf;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Pdf\DriverRegistry;
use Simtabi\Laranail\Pdf\Tests\TestCase;
use Simtabi\Laranail\Pdf\Support\UrlGuard;
use Simtabi\Laranail\Pdf\Contracts\PdfDriver;

final class CommandsTest extends TestCase
{
    // -----------------------------------------------------------------
    // Boot health
    // -----------------------------------------------------------------

    #[Test]
    public function every_binding_resolves_after_a_normal_boot(): void
    {
        self::assertInstanceOf(PdfManager::class, $this->app->make(PdfManager::class));
        self::assertInstanceOf(DriverRegistry::class, $this->app->make(DriverRegistry::class));
        self::assertInstanceOf(UrlGuard::class, $this->app->make(UrlGuard::class));
        self::assertInstanceOf(PdfDriver::class, $this->app->make(PdfDriver::class));
    }

    #[Test]
    public function the_facade_reaches_the_manager(): void
    {
        self::assertEqualsCanonicalizing(['gotenberg', 'dompdf'], Pdf::drivers());
    }

    #[Test]
    public function both_bundled_drivers_are_registered_regardless_of_availability(): void
    {
        // Registered-but-unavailable produces "dompdf/dompdf is not installed",
        // which is the actual problem. Not-registered would produce
        // "no driver named [dompdf]", which sends the reader hunting a typo.
        $registry = $this->app->make(DriverRegistry::class);

        self::assertTrue($registry->has('gotenberg'));
        self::assertTrue($registry->has('dompdf'));
    }

    #[Test]
    public function doctor_lists_every_driver_with_its_capabilities(): void
    {
        $this->artisan('laranail::pdf.doctor')
            ->expectsOutputToContain('gotenberg')
            ->expectsOutputToContain('dompdf')
            ->assertExitCode($this->doctorExitCode());
    }

    #[Test]
    public function doctor_reports_the_metadata_endpoint_as_refused_by_default(): void
    {
        $this->artisan('laranail::pdf.doctor', ['--json' => true])
            ->expectsOutputToContain('"metadata_endpoint_refused": true')
            ->assertExitCode($this->doctorExitCode());
    }

    #[Test]
    public function doctor_warns_when_the_metadata_endpoint_is_reachable(): void
    {
        config()->set('laranail.pdf.security.block_private_addresses', false);
        $this->app->forgetInstance(UrlGuard::class);

        $this->artisan('laranail::pdf.doctor')
            ->expectsOutputToContain('169.254.169.254')
            ->assertExitCode($this->doctorExitCode());
    }

    #[Test]
    public function doctor_can_narrow_to_one_driver(): void
    {
        $dompdfUsable = $this->app->make(DriverRegistry::class)->driver('dompdf')->isAvailable();

        $this->artisan('laranail::pdf.doctor', ['--driver' => 'dompdf'])
            ->doesntExpectOutputToContain('gotenberg')
            ->assertExitCode($dompdfUsable ? 0 : 1);
    }

    #[Test]
    public function doctor_fails_on_an_unknown_driver_rather_than_reporting_nothing(): void
    {
        $this->artisan('laranail::pdf.doctor', ['--driver' => 'nope'])->assertExitCode(1);
    }

    #[Test]
    public function doctor_fails_when_no_driver_is_usable(): void
    {
        // A doctor that exits 0 with nothing working is useless as a CI gate.
        $registry = $this->app->make(DriverRegistry::class);
        $registry->flush();

        config()->set('laranail.pdf.drivers.gotenberg.base_url', '');

        $this->artisan('laranail::pdf.doctor', ['--driver' => 'gotenberg'])->assertExitCode(1);
    }

    // -----------------------------------------------------------------
    // install
    // -----------------------------------------------------------------

    #[Test]
    public function install_succeeds_even_when_nothing_optional_is_ready(): void
    {
        // A non-zero exit here would break a scripted setup that runs install
        // before composer require, which is the expected order.
        $this->artisan('laranail::pdf.install')->assertExitCode(0);
    }

    // -----------------------------------------------------------------
    // render
    // -----------------------------------------------------------------

    #[Test]
    public function render_writes_a_pdf_from_stdin_html(): void
    {
        if (! $this->app->make(DriverRegistry::class)->driver('dompdf')->isAvailable()) {
            self::markTestSkipped('dompdf/dompdf is not installed.');
        }

        $output = sys_get_temp_dir() . '/laranail-render-' . bin2hex(random_bytes(6)) . '.pdf';
        $input = sys_get_temp_dir() . '/laranail-render-' . bin2hex(random_bytes(6)) . '.html';
        file_put_contents($input, '<h1>Report</h1>');

        try {
            $this->artisan('laranail::pdf.render', [
                'source'   => $input,
                'output'   => $output,
                '--driver' => 'dompdf',
            ])->assertExitCode(0);

            self::assertFileExists($output);
            self::assertStringStartsWith('%PDF', (string) file_get_contents($output));
        } finally {
            @unlink($output);
            @unlink($input);
        }
    }

    #[Test]
    public function render_refuses_a_capability_the_driver_lacks(): void
    {
        $this->artisan('laranail::pdf.render', [
            'source'   => '/tmp/a.pdf',
            'output'   => '/tmp/out.pdf',
            '--driver' => 'dompdf',
            '--merge'  => ['/tmp/b.pdf'],
        ])->assertExitCode(1);
    }

    #[Test]
    public function render_refuses_a_url_the_guard_blocks(): void
    {
        $output = sys_get_temp_dir() . '/laranail-render-' . bin2hex(random_bytes(6)) . '.pdf';

        $this->artisan('laranail::pdf.render', [
            'source' => 'http://169.254.169.254/latest/meta-data/',
            'output' => $output,
        ])->assertExitCode(1);

        self::assertFileDoesNotExist($output);
    }

    // -----------------------------------------------------------------
    // doctor
    // -----------------------------------------------------------------

    /**
     * The doctor exits non-zero when nothing is usable — correct for a gate,
     * and the state the optional-dependencies-removed CI job runs in. Tests
     * about what it *prints* must not also assert an exit code that depends on
     * what happens to be installed.
     */
    private function doctorExitCode(): int
    {
        foreach ($this->app->make(DriverRegistry::class)->all() as $driver) {
            if ($driver->isAvailable()) {
                return 0;
            }
        }

        return 1;
    }
}
