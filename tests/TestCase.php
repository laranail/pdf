<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Tests;

use Override;
use Simtabi\Laranail\Pdf\Facades\Pdf;
use Simtabi\Laranail\Pdf\Providers\PdfServiceProvider;
use Simtabi\Laranail\Package\Tools\Testing\IsolatedTestCase;

abstract class TestCase extends IsolatedTestCase
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
