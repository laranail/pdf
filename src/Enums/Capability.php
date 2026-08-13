<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Enums;

use Simtabi\Laranail\Pdf\Contracts\Capabilities\ConvertsDocuments;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\MergesPdfs;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\RendersHtml;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\RendersUrl;
use Simtabi\Laranail\Pdf\Contracts\PdfDriver;

/**
 * The four things a PDF driver might be able to do.
 *
 * Each case is paired with the interface that grants it. That pairing is the
 * single source of truth for `supports()`, so the enum cannot describe a
 * capability the type system does not also enforce.
 */
enum Capability: string
{
    case Html = 'html';
    case Url = 'url';
    case Office = 'office';
    case Merge = 'merge';

    /**
     * @return class-string
     */
    public function contract(): string
    {
        return match ($this) {
            self::Html => RendersHtml::class,
            self::Url => RendersUrl::class,
            self::Office => ConvertsDocuments::class,
            self::Merge => MergesPdfs::class,
        };
    }

    public function isImplementedBy(PdfDriver $driver): bool
    {
        return $driver instanceof ($this->contract());
    }

    /**
     * Every capability a driver actually implements.
     *
     * @return list<self>
     */
    public static function of(PdfDriver $driver): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $capability): bool => $capability->isImplementedBy($driver),
        ));
    }

    public function label(): string
    {
        return match ($this) {
            self::Html => 'HTML to PDF',
            self::Url => 'URL to PDF',
            self::Office => 'Office document to PDF',
            self::Merge => 'Merge PDFs',
        };
    }
}
