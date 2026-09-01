<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Drivers;

use Dompdf\Dompdf;
use Dompdf\Options;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\RendersHtml;
use Simtabi\Laranail\Pdf\Exceptions\RenderFailed;
use Simtabi\Laranail\Pdf\ValueObjects\PdfDocument;
use Simtabi\Laranail\Pdf\ValueObjects\RenderOptions;
use Throwable;

/**
 * Dompdf — HTML only, in-process, no external service.
 *
 * ## Why this driver exists
 *
 * Partly because it is genuinely useful: an invoice does not need a Chromium
 * container, and a package whose only driver needs Docker is a package most
 * applications cannot adopt.
 *
 * But mostly because it **cannot** do three of the four things Gotenberg can. A
 * capability seam with one driver behind it is not a seam, it is an abstraction
 * nobody has tested — the interfaces would have been drawn around whatever
 * Gotenberg happened to support and would fit the second driver badly. This one
 * implements `RendersHtml` and nothing else, so `supports(Capability::Merge)`
 * has a real answer and `UnsupportedCapability` has a real path to being thrown.
 *
 * ## `isRemoteEnabled` stays off
 *
 * Dompdf's remote fetching makes every `<img src>` and `@import` in the input a
 * request the server performs — the same SSRF exposure as URL rendering, but
 * reachable from any HTML string rather than from a guarded URL argument. It is
 * a config toggle rather than a hard-coded false, because a trusted-template
 * pipeline has a legitimate need, and the default is off.
 */
final class DompdfDriver extends Driver implements RendersHtml
{
    /**
     * The config keys this driver will act on.
     *
     * An explicit list rather than `'set'.ucfirst($key)`, for the same reason
     * the driver registry is not an `Illuminate\Support\Manager`: a config
     * string must never become a method name. `method_exists()` would narrow
     * that to "any setter on `Options`", which is still a config file choosing
     * which code runs, and still grows silently as the vendor class grows.
     */
    private const array SETTABLE = [
        'isRemoteEnabled', 'isHtml5ParserEnabled', 'isFontSubsettingEnabled',
        'isJavascriptEnabled', 'defaultFont', 'defaultPaperSize',
        'defaultPaperOrientation', 'dpi', 'fontHeightRatio', 'chroot',
        'fontDir', 'fontCache', 'tempDir', 'logOutputFile',
    ];

    /**
     * @param  array<string, mixed>  $options  passed to Dompdf\Options
     */
    public function __construct(
        private readonly array $options = [],
        private readonly string $defaultPaper = 'a4',
        private readonly string $defaultOrientation = 'portrait',
        private readonly string $defaultFilename = 'document.pdf',
    ) {}

    public function name(): string
    {
        return 'dompdf';
    }

    public function unavailableReason(): ?string
    {
        if (! class_exists(Dompdf::class)) {
            return 'dompdf/dompdf is not installed. Run `composer require dompdf/dompdf`.';
        }

        return null;
    }

    public function html(string $html, ?RenderOptions $options = null): PdfDocument
    {
        $options ??= new RenderOptions;

        return new PdfDocument(
            resolver: fn (): StreamInterface => $this->render($html, $options),
            filename: $options->filenameOr($this->defaultFilename),
            driver: $this->name(),
        );
    }

    private function render(string $html, RenderOptions $options): StreamInterface
    {
        $this->assertAvailable();

        try {
            $dompdf = new Dompdf($this->buildOptions());

            $dompdf->setPaper(
                $options->paperSize ?? $this->defaultPaper,
                $options->orientation ?? $this->defaultOrientation,
            );

            $dompdf->loadHtml($html);
            $dompdf->render();

            $output = $dompdf->output();

            if ($output === null) {
                throw new RuntimeException('Dompdf produced no output.');
            }

            return Utils::streamFor($output);
        } catch (Throwable $e) {
            throw RenderFailed::from($this->name(), 'html', $e);
        }
    }

    private function buildOptions(): Options
    {
        $options = new Options;

        // Set before the configured values so an explicit `isRemoteEnabled` in
        // config still wins — the default is safe, the override is deliberate.
        // `isPhpEnabled` is not overridable at all: it makes `<script
        // type="text/php">` in the input execute, which turns any HTML that
        // reaches this driver into arbitrary code execution.
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);

        foreach (self::SETTABLE as $key) {
            if (! array_key_exists($key, $this->options)) {
                continue;
            }

            $options->set($key, $this->options[$key]);
        }

        return $options;
    }
}
