<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Exceptions;

use Simtabi\Laranail\Pdf\Enums\Capability;

/**
 * A driver was asked for something it does not implement.
 *
 * Thrown before any work starts, and it names the drivers that *could* do the
 * job — a caller hitting this needs to change a config value, and the message
 * should say which value to.
 */
final class UnsupportedCapability extends PdfException
{
    /**
     * @param list<string> $alternatives
     */
    public static function for(string $driver, Capability $capability, array $alternatives = []): self
    {
        $message = "The [{$driver}] PDF driver does not support {$capability->label()}.";

        $message .= $alternatives === []
            ? ' No installed driver does.'
            : ' Drivers that do: ' . implode(', ', $alternatives) . '.';

        $e = new self($message, 6002);
        $e->context = [
            'driver'       => $driver,
            'capability'   => $capability->value,
            'alternatives' => $alternatives,
        ];

        return $e;
    }
}
