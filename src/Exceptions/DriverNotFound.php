<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Exceptions;

final class DriverNotFound extends PdfException
{
    /**
     * @param  list<string>  $registered
     */
    public static function named(string $name, array $registered): self
    {
        $known = $registered === [] ? '(none registered)' : implode(', ', $registered);

        $e = new self("No PDF driver is registered as [{$name}]. Registered: {$known}.", 6001);
        $e->context = ['driver' => $name, 'registered' => $registered];

        return $e;
    }
}
