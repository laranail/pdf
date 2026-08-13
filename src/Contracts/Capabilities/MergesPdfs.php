<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Contracts\Capabilities;

use Simtabi\Laranail\Pdf\Contracts\PdfDriver;
use Simtabi\Laranail\Pdf\ValueObjects\PdfDocument;
use Simtabi\Laranail\Pdf\ValueObjects\RenderOptions;

/**
 * Merges several PDFs into one, in the order given.
 *
 * The capability most likely to produce something large, which is why the
 * return contract is a lazy `PdfDocument` rather than a string.
 */
interface MergesPdfs extends PdfDriver
{
    /**
     * @param list<string> $paths
     */
    public function merge(array $paths, ?RenderOptions $options = null): PdfDocument;
}
