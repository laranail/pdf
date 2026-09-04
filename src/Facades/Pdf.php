<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Facades;

use Closure;
use Simtabi\Laranail\Pdf\PdfManager;
use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Pdf\DriverRegistry;
use Simtabi\Laranail\Pdf\Enums\Capability;
use Simtabi\Laranail\Pdf\Contracts\PdfDriver;
use Simtabi\Laranail\Pdf\ValueObjects\PdfDocument;
use Simtabi\Laranail\Pdf\ValueObjects\RenderOptions;

/**
 * @method static PdfDocument html(string $html, array<string, mixed>|RenderOptions|null $options = null, ?string $driver = null)
 * @method static PdfDocument url(string $url, array<string, mixed>|RenderOptions|null $options = null, ?string $driver = null)
 * @method static PdfDocument convert(string $path, array<string, mixed>|RenderOptions|null $options = null, ?string $driver = null)
 * @method static PdfDocument merge(list<string> $paths, array<string, mixed>|RenderOptions|null $options = null, ?string $driver = null)
 * @method static PdfDocument view(string $view, array<string, mixed> $data = [], array<string, mixed>|RenderOptions|null $options = null, ?string $driver = null)
 * @method static PdfDriver driver(?string $name = null)
 * @method static DriverRegistry registry()
 * @method static PdfManager extend(string $name, Closure $factory)
 * @method static bool supports(Capability $capability, ?string $driver = null)
 * @method static list<string> drivers()
 *
 * @see PdfManager
 */
final class Pdf extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PdfManager::class;
    }
}
