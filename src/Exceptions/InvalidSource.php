<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Exceptions;

/**
 * The input was refused before any renderer saw it.
 *
 * Every constructor here is a refusal, and most of them are the SSRF guard: a
 * URL renderer is a machine inside the network that fetches whatever it is
 * given, so what it may be given is the security boundary.
 */
final class InvalidSource extends PdfException
{
    public static function fileNotFound(string $path): self
    {
        $e = new self("The file [{$path}] does not exist or is not readable.", 6005);
        $e->context = ['path' => $path];

        return $e;
    }

    public static function noFiles(): self
    {
        return new self('At least one file is required.', 6006);
    }

    public static function malformedUrl(string $url): self
    {
        $e = new self("The URL [{$url}] could not be parsed.", 6007);
        $e->context = ['url' => $url];

        return $e;
    }

    /**
     * @param  list<string>  $allowed
     */
    public static function disallowedScheme(string $url, string $scheme, array $allowed): self
    {
        $e = new self(
            "The URL [{$url}] uses the [{$scheme}] scheme; only ".implode('/', $allowed).' is allowed.',
            6008,
        );
        $e->context = ['url' => $url, 'scheme' => $scheme, 'allowed' => $allowed];

        return $e;
    }

    public static function credentialsInUrl(string $url): self
    {
        $e = new self(
            'A URL carrying a username or password will not be fetched: the credentials would be '
            .'sent by the renderer, logged by it, and are almost never what the caller intended.',
            6009,
        );
        $e->context = ['url' => $url];

        return $e;
    }

    /**
     * @param  list<string>  $allowed
     */
    public static function hostNotAllowed(string $host, array $allowed): self
    {
        $e = new self(
            "The host [{$host}] is not in laranail.pdf.security.allowed_hosts (".implode(', ', $allowed).').',
            6010,
        );
        $e->context = ['host' => $host, 'allowed' => $allowed];

        return $e;
    }

    public static function blockedHost(string $host, string $reason): self
    {
        $e = new self("The host [{$host}] will not be fetched: {$reason}.", 6011);
        $e->context = ['host' => $host, 'reason' => $reason];

        return $e;
    }
}
