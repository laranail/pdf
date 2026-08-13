<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Simtabi\Laranail\Pdf\Facades\Pdf;
use Simtabi\Laranail\Pdf\Providers\PdfServiceProvider;

abstract class TestCase extends Orchestra
{
    /** @return array<int, class-string> */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PdfServiceProvider::class];
    }

    /** @return array<string, class-string> */
    #[Override]
    protected function getPackageAliases($app): array
    {
        return ['Pdf' => Pdf::class];
    }
}
