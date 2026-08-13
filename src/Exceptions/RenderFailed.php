<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Exceptions;

use Throwable;

/**
 * A render was attempted and the renderer failed.
 *
 * Always wraps the underlying failure rather than letting it through, so a
 * caller can `catch (PdfException)` without knowing — or being able to name —
 * whichever optional vendor class actually threw.
 */
final class RenderFailed extends PdfException
{
    /**
     * @param array<string, mixed> $context
     */
    public static function from(string $driver, string $operation, Throwable $previous, array $context = []): self
    {
        $e = new self(
            "The [{$driver}] driver failed to render ({$operation}): {$previous->getMessage()}",
            6004,
            $previous,
        );
        $e->context = ['driver' => $driver, 'operation' => $operation, ...$context];

        return $e;
    }
}
