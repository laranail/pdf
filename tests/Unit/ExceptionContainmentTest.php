<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Tests\Unit;

use Gotenberg\Gotenberg;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Simtabi\Laranail\Pdf\Drivers\DompdfDriver;
use Simtabi\Laranail\Pdf\Drivers\GotenbergDriver;
use Simtabi\Laranail\Pdf\Exceptions\DriverUnavailable;
use Simtabi\Laranail\Pdf\Exceptions\InvalidSource;
use Simtabi\Laranail\Pdf\Exceptions\PdfException;
use Simtabi\Laranail\Pdf\Exceptions\RenderFailed;
use Simtabi\Laranail\Pdf\Tests\TestCase;
use Simtabi\Laranail\Pdf\ValueObjects\PdfDocument;
use Throwable;

/**
 * The promise that makes the optional dependencies genuinely optional.
 *
 * You cannot write `catch (GotenbergApiErrored $e)` for a class that may not be
 * loaded. So a caller must be able to catch `PdfException` and be done —
 * whatever actually threw, and whether or not the vendor package is installed.
 */
final class ExceptionContainmentTest extends TestCase
{
    #[Test]
    public function a_transport_failure_surfaces_as_a_pdf_exception(): void
    {
        $this->skipWithoutGotenberg();

        $driver = new GotenbergDriver(
            'http://localhost:3000',
            $this->failingClient(new RuntimeException('Connection refused')),
        );

        $this->expectException(PdfException::class);

        $driver->html('<p>hi</p>')->contents();
    }

    #[Test]
    public function the_original_failure_is_kept_as_the_previous_exception(): void
    {
        $this->skipWithoutGotenberg();

        // Wrapped, not swallowed. A log line that lost the underlying cause
        // would make this containment a downgrade rather than an improvement.
        $underlying = new RuntimeException('Gotenberg said no');

        $driver = new GotenbergDriver('http://localhost:3000', $this->failingClient($underlying));

        try {
            $driver->html('<p>hi</p>')->contents();
            self::fail('Expected RenderFailed.');
        } catch (RenderFailed $e) {
            self::assertSame($underlying, $e->getPrevious());
            self::assertStringContainsString('Gotenberg said no', $e->getMessage());
            self::assertSame('gotenberg', $e->context()['driver']);
            self::assertSame('html', $e->context()['operation']);
        }
    }

    #[Test]
    public function every_capability_contains_its_failures(): void
    {
        $this->skipWithoutGotenberg();

        $driver = new GotenbergDriver(
            'http://localhost:3000',
            $this->failingClient(new RuntimeException('boom')),
        );

        $file = tempnam(sys_get_temp_dir(), 'pdf');
        self::assertIsString($file);
        file_put_contents($file, '%PDF-1.4');

        try {
            foreach ([
                'html' => fn (): PdfDocument => $driver->html('<p>x</p>'),
                'url' => fn (): PdfDocument => $driver->url('https://example.com'),
                'office' => fn (): PdfDocument => $driver->convert($file),
                'merge' => fn (): PdfDocument => $driver->merge([$file]),
            ] as $operation => $call) {
                try {
                    $call()->contents();
                    self::fail("The {$operation} capability did not fail.");
                } catch (PdfException) {
                    self::assertTrue(true);
                }
            }
        } finally {
            @unlink($file);
        }
    }

    // -----------------------------------------------------------------
    // Unavailability
    // -----------------------------------------------------------------

    #[Test]
    public function rendering_without_a_client_is_a_pdf_exception(): void
    {
        $driver = new GotenbergDriver('http://localhost:3000');

        $this->expectException(DriverUnavailable::class);
        $this->expectExceptionCode(6003);

        $driver->html('<p>hi</p>')->contents();
    }

    #[Test]
    public function unavailability_is_reported_before_it_is_thrown(): void
    {
        // A doctor command needs to ask without triggering the failure. The
        // reason differs by what is missing — the package, the base URL, or the
        // HTTP client — and all three have to be actionable.
        $driver = new GotenbergDriver('');

        self::assertFalse($driver->isAvailable());

        $reason = (string) $driver->unavailableReason();

        self::assertNotSame('', $reason);
        self::assertTrue(
            str_contains($reason, 'base URL') || str_contains($reason, 'gotenberg/gotenberg-php'),
            "The reason names neither the missing config nor the missing package: {$reason}",
        );
    }

    // -----------------------------------------------------------------
    // Input refusal
    // -----------------------------------------------------------------

    #[Test]
    public function a_missing_file_is_refused_eagerly(): void
    {
        // Eagerly — before the document is even constructed — because a lazy
        // refusal would surface at read time, far from the call that was wrong.
        $driver = new GotenbergDriver('http://localhost:3000');

        $this->expectException(InvalidSource::class);
        $this->expectExceptionCode(6005);

        $driver->convert('/no/such/file.docx');
    }

    #[Test]
    public function merging_nothing_is_refused(): void
    {
        $driver = new GotenbergDriver('http://localhost:3000');

        $this->expectException(InvalidSource::class);
        $this->expectExceptionCode(6006);

        $driver->merge([]);
    }

    // -----------------------------------------------------------------
    // Every exception this package throws is catchable as one type
    // -----------------------------------------------------------------

    #[Test]
    public function every_exception_class_extends_the_package_base(): void
    {
        $directory = __DIR__.'/../../src/Exceptions';

        foreach (glob($directory.'/*.php') ?: [] as $file) {
            $class = 'Simtabi\\Laranail\\Pdf\\Exceptions\\'.basename($file, '.php');

            if ($class === PdfException::class) {
                continue;
            }

            self::assertTrue(
                is_subclass_of($class, PdfException::class),
                "{$class} is not catchable as PdfException, so a caller would have to name it directly.",
            );
        }
    }

    #[Test]
    public function dompdf_contains_its_failures_too(): void
    {
        // Not Gotenberg-specific. A driver added later that let its vendor
        // exception through would break the contract for everyone.
        $driver = new DompdfDriver;

        if (! $driver->isAvailable()) {
            self::markTestSkipped('dompdf/dompdf is not installed.');
        }

        // Dompdf accepts almost any input, so the reachable failure is the
        // unavailability path rather than a parse error. What matters is that
        // whatever surfaces is a PdfException.
        try {
            $bytes = $driver->html('<p>hello</p>')->contents();
            self::assertStringStartsWith('%PDF', $bytes);
        } catch (PdfException $e) {
            self::assertInstanceOf(PdfException::class, $e);
        }
    }

    private function failingClient(Throwable $failure): ClientInterface
    {
        return new readonly class($failure) implements ClientInterface
        {
            public function __construct(private Throwable $failure) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw $this->failure;
            }
        };
    }

    // -----------------------------------------------------------------
    // Render failures
    // -----------------------------------------------------------------

    private function skipWithoutGotenberg(): void
    {
        if (! class_exists(Gotenberg::class)) {
            self::markTestSkipped('gotenberg/gotenberg-php is not installed; the transport cannot be reached.');
        }
    }
}
