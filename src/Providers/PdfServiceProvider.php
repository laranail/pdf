<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Providers;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Psr\Http\Client\ClientInterface;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Pdf\Commands\DoctorCommand;
use Simtabi\Laranail\Pdf\Commands\InstallCommand;
use Simtabi\Laranail\Pdf\Commands\RenderCommand;
use Simtabi\Laranail\Pdf\Contracts\PdfDriver;
use Simtabi\Laranail\Pdf\DriverRegistry;
use Simtabi\Laranail\Pdf\Drivers\DompdfDriver;
use Simtabi\Laranail\Pdf\Drivers\GotenbergDriver;
use Simtabi\Laranail\Pdf\PdfManager;
use Simtabi\Laranail\Pdf\Support\PdfConfig;
use Simtabi\Laranail\Pdf\Support\UrlGuard;

final class PdfServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/pdf')
            ->setPublishTagId('pdf')
            ->hasConfigFile('pdf')
            ->hasCommands([
                DoctorCommand::class,
                RenderCommand::class,
                InstallCommand::class,
            ]);
    }

    #[Override]
    public function packageRegistered(): void
    {
        $this->app->singleton(
            PdfConfig::class,
            static fn (Application $app): PdfConfig => new PdfConfig($app->make(ConfigRepository::class)),
        );

        $this->app->singleton(
            UrlGuard::class,
            static fn (Application $app): UrlGuard => UrlGuard::fromConfig(
                $app->make(PdfConfig::class)->array('security'),
            ),
        );

        $this->app->singleton(function (Application $app): DriverRegistry {
            $registry = new DriverRegistry(
                $app,
                $app->make(PdfConfig::class)->string('default', 'gotenberg'),
            );

            $this->registerBundledDrivers($registry);

            return $registry;
        });

        $this->app->singleton(
            PdfManager::class,
            static fn (Application $app): PdfManager => new PdfManager($app->make(DriverRegistry::class)),
        );

        // Resolving PdfDriver alone gives the configured default, so a class
        // that only ever renders HTML can type-hint the contract and stay out
        // of the registry entirely.
        $this->app->bind(
            PdfDriver::class,
            static fn (Application $app): PdfDriver => $app->make(DriverRegistry::class)->driver(),
        );
    }

    /**
     * Both drivers are always registered, whether or not their package is
     * installed.
     *
     * Registration is cheap — the factory is a closure and nothing constructs
     * until someone asks. Making it conditional would mean a missing optional
     * package produced `DriverNotFound: no driver named [dompdf]`, which sends
     * the reader looking for a typo. Registered-but-unavailable produces
     * "dompdf/dompdf is not installed", which is the actual problem.
     */
    private function registerBundledDrivers(DriverRegistry $registry): void
    {
        $registry->extend('gotenberg', function (Container $container): GotenbergDriver {
            $config = $container->make(PdfConfig::class);

            return new GotenbergDriver(
                baseUrl: $config->string('drivers.gotenberg.base_url'),
                client: $this->psrClient($container, $config),
                urlGuard: $container->make(UrlGuard::class),
                defaultFilename: $config->string('default_filename', 'document.pdf'),
            );
        });

        $registry->extend('dompdf', static function (Container $container): DompdfDriver {
            $config = $container->make(PdfConfig::class);

            return new DompdfDriver(
                options: $config->array('drivers.dompdf.options'),
                defaultPaper: $config->string('drivers.dompdf.paper', 'a4'),
                defaultOrientation: $config->string('drivers.dompdf.orientation', 'portrait'),
                defaultFilename: $config->string('default_filename', 'document.pdf'),
            );
        });
    }

    /**
     * A PSR-18 client, or null when none can be built.
     *
     * Null rather than a thrown exception: the driver reports it through
     * `unavailableReason()`, which is what the doctor command reads. A provider
     * that threw here would take the whole application down at boot because an
     * optional package is missing.
     */
    private function psrClient(Container $container, PdfConfig $config): ?ClientInterface
    {
        if ($container->bound(ClientInterface::class)) {
            $client = $container->make(ClientInterface::class);

            if ($client instanceof ClientInterface) {
                return $client;
            }
        }

        if (! class_exists(GuzzleClient::class)) {
            return null;
        }

        return new GuzzleClient([
            'timeout' => $config->float('drivers.gotenberg.timeout', 60.0),
            'verify' => $this->verifyOption($config),
        ]);
    }

    /**
     * A configured-but-missing CA bundle falls back to the system trust store,
     * never to `false`. Silently disabling verification because a path is wrong
     * is how a development shortcut reaches production.
     */
    private function verifyOption(PdfConfig $config): string|bool
    {
        if (! $config->bool('drivers.gotenberg.verify_ssl', true)) {
            return false;
        }

        return $config->existingPath('drivers.gotenberg.ca_cert') ?? true;
    }
}
