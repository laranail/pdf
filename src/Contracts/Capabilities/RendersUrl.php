<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Contracts\Capabilities;

use Simtabi\Laranail\Pdf\Contracts\PdfDriver;
use Simtabi\Laranail\Pdf\ValueObjects\PdfDocument;
use Simtabi\Laranail\Pdf\ValueObjects\RenderOptions;

/**
 * Fetches a URL and renders what comes back.
 *
 * This is the capability that turns a PDF renderer into an SSRF vector: the
 * caller names a URL and something inside the network fetches it. Implementers
 * must put the URL through `Support\UrlGuard` before it reaches the renderer.
 */
interface RendersUrl extends PdfDriver
{
    public function url(string $url, ?RenderOptions $options = null): PdfDocument;
}
