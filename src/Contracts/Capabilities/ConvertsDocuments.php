<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Contracts\Capabilities;

use Simtabi\Laranail\Pdf\Contracts\PdfDriver;
use Simtabi\Laranail\Pdf\ValueObjects\PdfDocument;
use Simtabi\Laranail\Pdf\ValueObjects\RenderOptions;

/**
 * Converts an Office document — DOCX, XLSX, PPTX, ODT and friends.
 */
interface ConvertsDocuments extends PdfDriver
{
    public function convert(string $path, ?RenderOptions $options = null): PdfDocument;
}
