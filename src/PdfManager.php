<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf;

use Closure;
use Illuminate\Contracts\Container\Container;
use Simtabi\Laranail\Pdf\Contracts\PdfDriver;
use Simtabi\Laranail\Pdf\Enums\Capability;
use Simtabi\Laranail\Pdf\ValueObjects\PdfDocument;
use Simtabi\Laranail\Pdf\ValueObjects\RenderOptions;

/**
 * What sits behind the `Pdf` facade.
 *
 * A thin front over `DriverRegistry` — the four verbs, plus the introspection a
 * doctor command and a caller deciding what is possible both need. Each verb
 * resolves through the registry's capability-checked accessor, so asking Dompdf
 * to merge fails with `UnsupportedCapability` naming the drivers that could,
 * rather than a `BadMethodCallException`.
 */
final readonly class PdfManager
{
    public function __construct(private DriverRegistry $registry) {}

    /**
     * @param array<string, mixed>|RenderOptions|null $options
     */
    public function html(string $html, array|RenderOptions|null $options = null, ?string $driver = null): PdfDocument
    {
        return $this->registry->forHtml($driver)->html($html, $this->options($options));
    }

    /**
     * @param array<string, mixed>|RenderOptions|null $options
     */
    public function url(string $url, array|RenderOptions|null $options = null, ?string $driver = null): PdfDocument
    {
        return $this->registry->forUrl($driver)->url($url, $this->options($options));
    }

    /**
     * @param array<string, mixed>|RenderOptions|null $options
     */
    public function convert(string $path, array|RenderOptions|null $options = null, ?string $driver = null): PdfDocument
    {
        return $this->registry->forDocuments($driver)->convert($path, $this->options($options));
    }

    /**
     * @param list<string> $paths
     * @param array<string, mixed>|RenderOptions|null $options
     */
    public function merge(array $paths, array|RenderOptions|null $options = null, ?string $driver = null): PdfDocument
    {
        return $this->registry->forMerge($driver)->merge($paths, $this->options($options));
    }

    /**
     * Render a Blade view.
     *
     * Sugar over `html()`, and the shape most applications actually want — the
     * alternative is `Pdf::html(view('invoice', $data)->render())` at every call
     * site.
     *
     * @param view-string $view
     * @param array<string, mixed> $data
     * @param array<string, mixed>|RenderOptions|null $options
     */
    public function view(string $view, array $data = [], array|RenderOptions|null $options = null, ?string $driver = null): PdfDocument
    {
        return $this->html(view($view, $data)->render(), $options, $driver);
    }

    public function driver(?string $name = null): PdfDriver
    {
        return $this->registry->driver($name);
    }

    public function registry(): DriverRegistry
    {
        return $this->registry;
    }

    /**
     * @param Closure(Container): PdfDriver $factory
     */
    public function extend(string $name, Closure $factory): self
    {
        $this->registry->extend($name, $factory);

        return $this;
    }

    public function supports(Capability $capability, ?string $driver = null): bool
    {
        return $this->registry->driver($driver)->supports($capability);
    }

    /**
     * @return list<string>
     */
    public function drivers(): array
    {
        return $this->registry->names();
    }

    /**
     * @param array<string, mixed>|RenderOptions|null $options
     */
    private function options(array|RenderOptions|null $options): ?RenderOptions
    {
        return is_array($options) ? RenderOptions::fromArray($options) : $options;
    }
}
