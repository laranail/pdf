<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Tests\Unit;

use ReflectionClass;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Pdf\DriverRegistry;
use Simtabi\Laranail\Pdf\Drivers\Driver;
use Simtabi\Laranail\Pdf\Tests\TestCase;
use Simtabi\Laranail\Pdf\Enums\Capability;
use Simtabi\Laranail\Pdf\Contracts\PdfDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use Simtabi\Laranail\Pdf\Drivers\DompdfDriver;
use Simtabi\Laranail\Pdf\Drivers\GotenbergDriver;
use Simtabi\Laranail\Pdf\Exceptions\UnsupportedCapability;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\MergesPdfs;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\RendersUrl;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\RendersHtml;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\ConvertsDocuments;

/**
 * The claim this package makes about itself: a driver cannot say it can do
 * something it has not implemented, and cannot implement something without
 * saying so.
 */
final class DriverContractTest extends TestCase
{
    /** @return array<string, array{PdfDriver, list<Capability>}> */
    public static function drivers(): array
    {
        return [
            'gotenberg' => [
                new GotenbergDriver('http://localhost:3000'),
                [Capability::Html, Capability::Url, Capability::Office, Capability::Merge],
            ],
            'dompdf' => [
                new DompdfDriver,
                [Capability::Html],
            ],
        ];
    }

    /**
     * @param list<Capability> $expected
     */
    #[Test]
    #[DataProvider('drivers')]
    public function a_driver_declares_exactly_what_it_implements(PdfDriver $driver, array $expected): void
    {
        self::assertEqualsCanonicalizing($expected, $driver->capabilities());
    }

    /**
     * @param list<Capability> $expected
     */
    #[Test]
    #[DataProvider('drivers')]
    public function every_declared_capability_is_backed_by_its_interface(PdfDriver $driver, array $expected): void
    {
        // The inverse of the design claim: a capability that reports true must
        // have the interface behind it, or the typed accessors on the registry
        // would return something you cannot call the method on.
        foreach (Capability::cases() as $capability) {
            self::assertSame(
                $driver instanceof ($capability->contract()),
                $driver->supports($capability),
                "supports({$capability->value}) disagrees with instanceof on {$driver->name()}.",
            );
        }

        self::assertEqualsCanonicalizing($expected, $driver->capabilities());
    }

    #[Test]
    public function a_driver_cannot_override_its_own_capability_claim(): void
    {
        // capabilities() and supports() are final on the Driver base. If they
        // were not, a driver could claim Merge without implementing MergesPdfs
        // and the registry's typed accessor would hand back something whose
        // merge() does not exist.
        $reflection = new ReflectionClass(Driver::class);

        self::assertTrue($reflection->getMethod('capabilities')->isFinal());
        self::assertTrue($reflection->getMethod('supports')->isFinal());
    }

    // -----------------------------------------------------------------
    // The registry enforces it at resolve time
    // -----------------------------------------------------------------

    #[Test]
    public function asking_dompdf_to_merge_fails_before_any_work_starts(): void
    {
        $registry = $this->registry();

        $this->expectException(UnsupportedCapability::class);
        $this->expectExceptionCode(6002);

        $registry->forMerge('dompdf');
    }

    #[Test]
    public function the_refusal_names_a_driver_that_could_have_done_it(): void
    {
        // An error saying only "unsupported" leaves the reader guessing which
        // config value to change.
        $registry = $this->registry();

        $gotenbergUsable = $registry->driver('gotenberg')->isAvailable();

        try {
            $registry->forMerge('dompdf');
            self::fail('Expected UnsupportedCapability.');
        } catch (UnsupportedCapability $e) {
            if ($gotenbergUsable) {
                self::assertStringContainsString('gotenberg', $e->getMessage());
                self::assertSame(['gotenberg'], $e->context()['alternatives']);

                return;
            }

            // With no capable driver installed, the message must say so rather
            // than name one that cannot run — pointing at an uninstalled driver
            // reads as a config problem when it is an install one.
            self::assertStringContainsString('No installed driver does', $e->getMessage());
            self::assertSame([], $e->context()['alternatives']);
        }
    }

    #[Test]
    public function the_typed_accessors_return_the_capability_interface(): void
    {
        $registry = $this->registry();

        self::assertInstanceOf(RendersHtml::class, $registry->forHtml('dompdf'));
        self::assertInstanceOf(RendersHtml::class, $registry->forHtml('gotenberg'));
        self::assertInstanceOf(RendersUrl::class, $registry->forUrl('gotenberg'));
        self::assertInstanceOf(ConvertsDocuments::class, $registry->forDocuments('gotenberg'));
        self::assertInstanceOf(MergesPdfs::class, $registry->forMerge('gotenberg'));
    }

    #[Test]
    public function drivers_for_omits_a_capable_but_unavailable_driver(): void
    {
        // Naming a driver whose package is not installed sends someone down the
        // wrong path — it looks like a config problem when it is an install one.
        $registry = new DriverRegistry($this->app, 'gotenberg');
        $registry->extend('broken', static fn (): GotenbergDriver => new GotenbergDriver(''));

        self::assertSame([], $registry->driversFor(Capability::Merge));
    }

    private function registry(): DriverRegistry
    {
        return $this->app->make(DriverRegistry::class);
    }
}
