<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Contracts\Capabilities;

use Simtabi\Laranail\Pdf\Contracts\PdfDriver;
use Simtabi\Laranail\Pdf\ValueObjects\PdfDocument;
use Simtabi\Laranail\Pdf\ValueObjects\RenderOptions;

/**
 * Renders an HTML string.
 *
 * The one capability every driver is expected to have; it is still an interface
 * rather than a method on `PdfDriver` so the rule stays uniform — a capability
 * is a type, always.
 */
interface RendersHtml extends PdfDriver
{
    public function html(string $html, ?RenderOptions $options = null): PdfDocument;
}
