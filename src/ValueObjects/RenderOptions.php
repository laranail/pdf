<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\ValueObjects;

/**
 * The render settings both drivers can honour, plus an escape hatch for the
 * ones only a specific driver understands.
 *
 * Kept deliberately small. A union of every option Gotenberg and Dompdf accept
 * would be a large object where most fields silently do nothing depending on
 * which driver ran — the shape would promise portability the drivers cannot
 * deliver. So the typed fields are the genuinely common ones, and anything
 * driver-specific goes in `extra`, where it reads as what it is.
 */
final readonly class RenderOptions
{
    /**
     * @param array<string, mixed> $extra driver-specific settings, passed through untouched
     */
    public function __construct(
        public ?string $filename = null,
        public ?string $paperSize = null,
        public ?string $orientation = null,
        public ?float $marginTop = null,
        public ?float $marginRight = null,
        public ?float $marginBottom = null,
        public ?float $marginLeft = null,
        public ?string $header = null,
        public ?string $footer = null,
        public ?bool $printBackground = null,
        public array $extra = [],
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public static function fromArray(array $options): self
    {
        $known = [
            'filename', 'paperSize', 'orientation', 'marginTop', 'marginRight',
            'marginBottom', 'marginLeft', 'header', 'footer', 'printBackground',
        ];

        return new self(
            filename: self::string($options, 'filename'),
            paperSize: self::string($options, 'paperSize'),
            orientation: self::string($options, 'orientation'),
            marginTop: self::float($options, 'marginTop'),
            marginRight: self::float($options, 'marginRight'),
            marginBottom: self::float($options, 'marginBottom'),
            marginLeft: self::float($options, 'marginLeft'),
            header: self::string($options, 'header'),
            footer: self::string($options, 'footer'),
            printBackground: isset($options['printBackground']) ? (bool) $options['printBackground'] : null,
            extra: array_diff_key($options, array_flip($known)),
        );
    }

    public function isLandscape(): bool
    {
        return strtolower((string) $this->orientation) === 'landscape';
    }

    public function hasMargins(): bool
    {
        return $this->marginTop !== null
            || $this->marginRight !== null
            || $this->marginBottom !== null
            || $this->marginLeft !== null;
    }

    public function filenameOr(string $fallback): string
    {
        return $this->filename ?? $fallback;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function string(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function float(array $options, string $key): ?float
    {
        $value = $options[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }
}
