<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Pdf\DriverRegistry;
use Simtabi\Laranail\Pdf\Exceptions\InvalidSource;
use Simtabi\Laranail\Pdf\Facades\Pdf;
use Simtabi\Laranail\Pdf\Tests\TestCase;

/**
 * Against a real Gotenberg container.
 *
 * Excluded from the default suite and run by its own CI job. Gotenberg is an
 * HTTP service, so the driver's real behaviour — that a request it builds is one
 * Gotenberg accepts, and that bytes come back — cannot be asserted against a
 * mock. A mock only proves the driver calls the builder the way the test author
 * believed it should.
 */
#[Group('gotenberg')]
final class GotenbergIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $baseUrl = (string) (getenv('LARANAIL_PDF_GOTENBERG_URL') ?: 'http://localhost:3000');

        config()->set('laranail.pdf.default', 'gotenberg');
        config()->set('laranail.pdf.drivers.gotenberg.base_url', $baseUrl);

        $this->app->make(DriverRegistry::class)->flush();

        if (! $this->app->make(DriverRegistry::class)->driver('gotenberg')->isAvailable()) {
            self::markTestSkipped('The Gotenberg driver is not available.');
        }
    }

    private function fixture(string $contents, string $extension): string
    {
        $path = sys_get_temp_dir() . '/laranail-gotenberg-' . bin2hex(random_bytes(6)) . '.' . $extension;
        file_put_contents($path, $contents);

        return $path;
    }

    #[Test]
    public function it_renders_html(): void
    {
        $bytes = Pdf::html('<h1>Integration</h1>')->contents();

        self::assertStringStartsWith('%PDF', $bytes);
        self::assertGreaterThan(500, strlen($bytes));
    }

    #[Test]
    public function it_honours_paper_size_and_orientation(): void
    {
        // Not asserting page dimensions — that would mean parsing the PDF. What
        // this proves is that the options reach Gotenberg in a form it accepts
        // rather than being silently rejected, which is the failure that
        // actually happens.
        $bytes = Pdf::html('<h1>Wide</h1>', [
            'paperSize' => 'a3',
            'orientation' => 'landscape',
            'printBackground' => true,
            'marginTop' => 0.5,
        ])->contents();

        self::assertStringStartsWith('%PDF', $bytes);
    }

    #[Test]
    public function it_renders_a_url(): void
    {
        $bytes = Pdf::url('https://example.com/')->contents();

        self::assertStringStartsWith('%PDF', $bytes);
    }

    #[Test]
    public function it_merges_pdfs(): void
    {
        $first = $this->fixture(Pdf::html('<h1>One</h1>')->contents(), 'pdf');
        $second = $this->fixture(Pdf::html('<h1>Two</h1>')->contents(), 'pdf');

        try {
            $merged = Pdf::merge([$first, $second])->contents();

            self::assertStringStartsWith('%PDF', $merged);
            self::assertGreaterThan(
                strlen((string) file_get_contents($first)),
                strlen($merged),
                'The merge is not larger than one of its inputs.',
            );
        } finally {
            @unlink($first);
            @unlink($second);
        }
    }

    #[Test]
    public function the_guard_still_applies_against_a_live_service(): void
    {
        // The guard runs in the driver, before the request is built — so having
        // a working Gotenberg does not change the answer. Asserted here because
        // "it was blocked in a unit test" and "it is blocked in production" are
        // different claims.
        $this->expectException(InvalidSource::class);

        Pdf::url('http://169.254.169.254/latest/meta-data/')->contents();
    }

    #[Test]
    public function a_document_streams_to_a_disk_without_being_read_first(): void
    {
        Storage::fake('reports');

        Pdf::html('<h1>Stored</h1>')->store('integration.pdf', 'reports');

        Storage::disk('reports')->assertExists('integration.pdf');
        self::assertStringStartsWith(
            '%PDF',
            (string) Storage::disk('reports')->get('integration.pdf'),
        );
    }

    #[Test]
    public function doctor_probes_successfully_against_the_real_service(): void
    {
        $this->artisan('laranail::pdf.doctor', ['--driver' => 'gotenberg', '--probe' => true])
            ->expectsOutputToContain('ok (')
            ->assertExitCode(0);
    }
}
