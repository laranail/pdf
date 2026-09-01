<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf;

use Closure;
use Illuminate\Contracts\Container\Container;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\ConvertsDocuments;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\MergesPdfs;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\RendersHtml;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\RendersUrl;
use Simtabi\Laranail\Pdf\Contracts\PdfDriver;
use Simtabi\Laranail\Pdf\Enums\Capability;
use Simtabi\Laranail\Pdf\Exceptions\DriverNotFound;
use Simtabi\Laranail\Pdf\Exceptions\UnsupportedCapability;

/**
 * Resolves a driver by name, and refuses to hand back one that cannot do the
 * job being asked of it.
 *
 * ## Why not `Illuminate\Support\Manager`
 *
 * Because `Manager` resolves a driver by turning the configured string into a
 * method name — `createFooDriver()` for `'foo'`. A config value must never
 * reach a method name. `laranail/captcha` settled this the same way in its
 * `AdapterFactory`, and this follows it: the map is data, resolution is an array
 * lookup, and an unknown name produces `DriverNotFound` rather than a
 * `BadMethodCallException` that leaks the shape of the class.
 *
 * ## Why not an enum of drivers
 *
 * A closed set cannot be extended, and third-party drivers are a real
 * requirement — someone will want wkhtmltopdf, or a hosted API. `extend()` is
 * the seam, and a registered factory is resolved lazily so an unused driver
 * never has to be constructible.
 */
final class DriverRegistry
{
    /** @var array<string, Closure(Container): PdfDriver> */
    private array $factories = [];

    /** @var array<string, PdfDriver> */
    private array $resolved = [];

    public function __construct(
        private readonly Container $container,
        private readonly string $default = 'gotenberg',
    ) {}

    /**
     * Register a driver factory under a name.
     *
     * @param  Closure(Container): PdfDriver  $factory
     */
    public function extend(string $name, Closure $factory): self
    {
        $this->factories[$name] = $factory;
        unset($this->resolved[$name]);

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->factories[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->factories);
    }

    public function defaultName(): string
    {
        return $this->default;
    }

    /**
     * @throws DriverNotFound
     */
    public function driver(?string $name = null): PdfDriver
    {
        $name ??= $this->default;

        if (! isset($this->factories[$name])) {
            throw DriverNotFound::named($name, $this->names());
        }

        return $this->resolved[$name] ??= ($this->factories[$name])($this->container);
    }

    /**
     * A driver proven to implement the capability, typed as the capability.
     *
     * The `instanceof` here is what makes the return type honest — the caller
     * gets something it can call `html()` on because the check just proved it,
     * not because a config file said so.
     *
     * @throws DriverNotFound
     * @throws UnsupportedCapability
     */
    public function capable(Capability $capability, ?string $name = null): PdfDriver
    {
        $driver = $this->driver($name);

        if (! $capability->isImplementedBy($driver)) {
            throw UnsupportedCapability::for($driver->name(), $capability, $this->driversFor($capability));
        }

        return $driver;
    }

    /**
     * @throws DriverNotFound
     * @throws UnsupportedCapability
     */
    public function forHtml(?string $name = null): RendersHtml
    {
        $driver = $this->capable(Capability::Html, $name);
        assert($driver instanceof RendersHtml);

        return $driver;
    }

    /**
     * @throws DriverNotFound
     * @throws UnsupportedCapability
     */
    public function forUrl(?string $name = null): RendersUrl
    {
        $driver = $this->capable(Capability::Url, $name);
        assert($driver instanceof RendersUrl);

        return $driver;
    }

    /**
     * @throws DriverNotFound
     * @throws UnsupportedCapability
     */
    public function forDocuments(?string $name = null): ConvertsDocuments
    {
        $driver = $this->capable(Capability::Office, $name);
        assert($driver instanceof ConvertsDocuments);

        return $driver;
    }

    /**
     * @throws DriverNotFound
     * @throws UnsupportedCapability
     */
    public function forMerge(?string $name = null): MergesPdfs
    {
        $driver = $this->capable(Capability::Merge, $name);
        assert($driver instanceof MergesPdfs);

        return $driver;
    }

    /**
     * Which registered drivers could do this, for an error message worth reading.
     *
     * Availability is checked too: naming a driver that is registered but whose
     * package is not installed would send someone down the wrong path.
     *
     * @return list<string>
     */
    public function driversFor(Capability $capability): array
    {
        $names = [];

        foreach ($this->names() as $name) {
            try {
                $driver = $this->driver($name);
            } catch (DriverNotFound) {
                continue;
            }

            if ($capability->isImplementedBy($driver) && $driver->isAvailable()) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Every registered driver, resolved.
     *
     * @return array<string, PdfDriver>
     */
    public function all(): array
    {
        $drivers = [];

        foreach ($this->names() as $name) {
            $drivers[$name] = $this->driver($name);
        }

        return $drivers;
    }

    /**
     * Drop resolved instances, keeping the factories.
     */
    public function flush(): void
    {
        $this->resolved = [];
    }
}
