<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Drivers;

/**
 * Paper sizes in inches, because Gotenberg wants dimensions and callers want
 * names.
 *
 * Kept as one table so `a4` means the same thing to every driver. An unknown
 * name falls back to A4 rather than throwing: a typo in a paper size is not
 * worth failing a render over, and the wrong-but-reasonable page is obvious the
 * moment anyone looks at it.
 */
final class PaperSize
{
    /** @var array<string, array{float, float}> */
    private const array SIZES = [
        'a3' => [11.7, 16.5],
        'a4' => [8.27, 11.7],
        'a5' => [5.83, 8.27],
        'a6' => [4.13, 5.83],
        'letter' => [8.5, 11.0],
        'legal' => [8.5, 14.0],
        'tabloid' => [11.0, 17.0],
        'ledger' => [17.0, 11.0],
    ];

    /**
     * @return array{float, float} [width, height]
     */
    public static function inches(string $name): array
    {
        return self::SIZES[strtolower(trim($name))] ?? self::SIZES['a4'];
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::SIZES);
    }

    public static function isKnown(string $name): bool
    {
        return isset(self::SIZES[strtolower(trim($name))]);
    }
}
