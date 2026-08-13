<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Drivers;

use Closure;
use Gotenberg\Gotenberg;
use Gotenberg\Modules\ChromiumPdf;
use Gotenberg\Stream;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\ConvertsDocuments;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\MergesPdfs;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\RendersHtml;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\RendersUrl;
use Simtabi\Laranail\Pdf\Exceptions\InvalidSource;
use Simtabi\Laranail\Pdf\Exceptions\RenderFailed;
use Simtabi\Laranail\Pdf\Support\UrlGuard;
use Simtabi\Laranail\Pdf\ValueObjects\PdfDocument;
use Simtabi\Laranail\Pdf\ValueObjects\RenderOptions;
use Throwable;

/**
 * Gotenberg — the driver that can do everything.
 *
 * Renders through a running Gotenberg instance: Chromium for HTML and URLs,
 * LibreOffice for Office documents, and its PDF engines for merging.
 *
 * ## The base URL comes from config, never from a caller
 *
 * There is no `baseUrl()` setter and no per-call override. A renderer whose
 * target host is caller-supplied is an SSRF primitive with extra steps, and the
 * URL capability is already the risky one — `UrlGuard` covers what gets fetched;
 * this covers who does the fetching.
 *
 * ## Nothing from `gotenberg/gotenberg-php` escapes
 *
 * The package is optional, so its exception classes may not exist at runtime,
 * and a caller cannot write `catch (GotenbergApiErrored)` for a class that might
 * not be loaded. Every call site rethrows as `RenderFailed`.
 */
final class GotenbergDriver extends Driver implements ConvertsDocuments, MergesPdfs, RendersHtml, RendersUrl
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?ClientInterface $client = null,
        private readonly ?UrlGuard $urlGuard = null,
        private readonly string $defaultFilename = 'document.pdf',
    ) {}

    public function name(): string
    {
        return 'gotenberg';
    }

    public function unavailableReason(): ?string
    {
        if (! class_exists(Gotenberg::class)) {
            return 'gotenberg/gotenberg-php is not installed. Run `composer require gotenberg/gotenberg-php`.';
        }

        if (trim($this->baseUrl) === '') {
            return 'No base URL is configured. Set LARANAIL_PDF_GOTENBERG_URL or laranail.pdf.drivers.gotenberg.base_url.';
        }

        if (! $this->client instanceof ClientInterface) {
            return 'No PSR-18 HTTP client is available. Run `composer require guzzlehttp/guzzle`.';
        }

        return null;
    }

    public function html(string $html, ?RenderOptions $options = null): PdfDocument
    {
        $options ??= new RenderOptions;

        return $this->document(
            fn (): StreamInterface => $this->send(
                fn (): RequestInterface => $this->chromium($options)->html(Stream::string('index.html', $html)),
                'html',
            ),
            $options,
        );
    }

    public function url(string $url, ?RenderOptions $options = null): PdfDocument
    {
        $options ??= new RenderOptions;

        // Before anything is built, so a refused URL costs nothing and the
        // exception names the URL rather than a Gotenberg trace.
        $this->urlGuard?->assertAllowed($url);

        return $this->document(
            fn (): StreamInterface => $this->send(
                fn (): RequestInterface => $this->chromium($options)->url($url),
                'url',
                ['url' => $url],
            ),
            $options,
        );
    }

    public function convert(string $path, ?RenderOptions $options = null): PdfDocument
    {
        $options ??= new RenderOptions;

        if (! is_file($path) || ! is_readable($path)) {
            throw InvalidSource::fileNotFound($path);
        }

        return $this->document(
            fn (): StreamInterface => $this->send(
                function () use ($path, $options): RequestInterface {
                    $builder = Gotenberg::libreOffice($this->baseUrl);

                    if (($filename = $options->filename) !== null) {
                        $builder = $builder->outputFilename($filename);
                    }

                    return $builder->convert(Stream::path($path));
                },
                'office',
                ['path' => $path],
            ),
            $options,
            basename($path, '.' . pathinfo($path, PATHINFO_EXTENSION)) . '.pdf',
        );
    }

    public function merge(array $paths, ?RenderOptions $options = null): PdfDocument
    {
        $options ??= new RenderOptions;

        if ($paths === []) {
            throw InvalidSource::noFiles();
        }

        foreach ($paths as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                throw InvalidSource::fileNotFound($path);
            }
        }

        return $this->document(
            fn (): StreamInterface => $this->send(
                function () use ($paths, $options): RequestInterface {
                    $builder = Gotenberg::pdfEngines($this->baseUrl);

                    if (($filename = $options->filename) !== null) {
                        $builder = $builder->outputFilename($filename);
                    }

                    return $builder->merge(...array_map(
                        static fn (string $path): Stream => Stream::path($path),
                        $paths,
                    ));
                },
                'merge',
                ['files' => count($paths)],
            ),
            $options,
            'merged.pdf',
        );
    }

    /**
     * Build the request, send it, and return the response body.
     *
     * @param Closure():RequestInterface $build
     * @param array<string, mixed> $context
     */
    private function send(Closure $build, string $operation, array $context = []): StreamInterface
    {
        $this->assertAvailable();

        try {
            $request = $build();
            $response = Gotenberg::send($request, $this->client);

            return $response->getBody();
        } catch (Throwable $e) {
            // Everything, not just GotenbergApiErrored: a connection failure
            // surfaces as a Guzzle exception, and a caller catching
            // PdfException should not have to know the difference.
            throw RenderFailed::from($this->name(), $operation, $e, $context);
        }
    }

    private function chromium(RenderOptions $options): ChromiumPdf
    {
        $builder = Gotenberg::chromium($this->baseUrl)->pdf();

        if (($filename = $options->filename) !== null) {
            $builder = $builder->outputFilename($filename);
        }

        if ($options->paperSize !== null) {
            $builder = $this->applyPaperSize($builder, $options);
        }

        if ($options->hasMargins()) {
            $builder = $builder->margins(
                $options->marginTop ?? 0.39,
                $options->marginBottom ?? 0.39,
                $options->marginLeft ?? 0.39,
                $options->marginRight ?? 0.39,
            );
        }

        if ($options->printBackground === true) {
            $builder = $builder->printBackground();
        }

        if ($options->header !== null) {
            $builder = $builder->header(Stream::string('header.html', $options->header));
        }

        if ($options->footer !== null) {
            return $builder->footer(Stream::string('footer.html', $options->footer));
        }

        return $builder;
    }

    /**
     * Gotenberg wants inches, not a name.
     */
    private function applyPaperSize(ChromiumPdf $builder, RenderOptions $options): ChromiumPdf
    {
        [$width, $height] = PaperSize::inches($options->paperSize ?? 'a4');

        if ($options->isLandscape()) {
            [$width, $height] = [$height, $width];
        }

        return $builder->paperSize($width, $height);
    }

    /**
     * @param Closure():StreamInterface $resolver
     */
    private function document(Closure $resolver, RenderOptions $options, ?string $fallback = null): PdfDocument
    {
        return new PdfDocument(
            resolver: $resolver,
            filename: $options->filenameOr($fallback ?? $this->defaultFilename),
            driver: $this->name(),
        );
    }
}
