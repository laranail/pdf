<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Support;

use Simtabi\Laranail\Pdf\Exceptions\InvalidSource;

/**
 * Decides whether a URL may be fetched by a renderer.
 *
 * ## Why a URL renderer needs a guard at all
 *
 * "Render this URL to a PDF" is a request for a machine inside your network to
 * fetch a URL an outside caller chose and return what it found. That is the
 * definition of SSRF. Left open, a form field reaching this method reads
 * `http://169.254.169.254/latest/meta-data/iam/security-credentials/` and puts
 * the answer in a PDF.
 *
 * ## What it refuses
 *
 * - **Anything but http/https.** `file://` reads the local disk and `gopher://`
 *   speaks to arbitrary TCP ports; neither belongs in a page renderer.
 * - **Userinfo in the URL.** `https://user:pass@host/` makes the renderer send
 *   credentials, and the full URL lands in its logs. It is also almost never
 *   what the caller meant.
 * - **Private and link-local address literals**, when `block_private` is on:
 *   loopback, RFC 1918, CGNAT, link-local — `169.254.169.254` above is the one
 *   that matters — and their IPv6 equivalents.
 * - **Any host outside `allowed_hosts`**, when that list is non-empty. This is
 *   the only setting that is actually safe against a determined caller, and it
 *   is why it exists: the checks above are lexical, so a hostname resolving to a
 *   private address passes them.
 *
 * ## What it does not do
 *
 * It does not resolve DNS. A host that resolves to `127.0.0.1` gets through a
 * literal-address check, and resolving here would not fix it — the renderer
 * resolves again when it connects, and the answer can differ between the two
 * (DNS rebinding). **The allow-list is the real defence**, and the docs say so
 * rather than implying the literal checks are sufficient.
 */
final readonly class UrlGuard
{
    /**
     * @param list<string> $allowedSchemes
     * @param list<string> $allowedHosts empty means "any host", subject to the checks below
     */
    public function __construct(
        private array $allowedSchemes = ['http', 'https'],
        private array $allowedHosts = [],
        private bool $blockPrivate = true,
    ) {}

    /**
     * @param array<string, mixed> $config the `laranail.pdf.security` block
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            allowedSchemes: self::stringList($config['allowed_schemes'] ?? ['http', 'https']),
            allowedHosts: self::stringList($config['allowed_hosts'] ?? []),
            blockPrivate: (bool) ($config['block_private_addresses'] ?? true),
        );
    }

    /**
     * @throws InvalidSource
     */
    public function assertAllowed(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw InvalidSource::malformedUrl($url);
        }

        $scheme = strtolower((string) $parts['scheme']);

        if (! in_array($scheme, $this->allowedSchemes, true)) {
            throw InvalidSource::disallowedScheme($url, $scheme, $this->allowedSchemes);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw InvalidSource::credentialsInUrl($url);
        }

        $host = strtolower((string) $parts['host']);

        if ($this->allowedHosts !== []) {
            if (! $this->hostIsAllowed($host)) {
                throw InvalidSource::hostNotAllowed($host, $this->allowedHosts);
            }

            // An explicit allow-list entry wins over the blanket private check.
            // Naming `10.0.0.5` in config is a deliberate statement about one
            // host; the alternative is making the operator set
            // `block_private_addresses => false` to reach it, which turns off
            // the check for **every** host to permit one. A narrow allow beats
            // a broad disable.
            return;
        }

        if ($this->blockPrivate) {
            $this->assertNotPrivate($host);
        }
    }

    public function allows(string $url): bool
    {
        try {
            $this->assertAllowed($url);

            return true;
        } catch (InvalidSource) {
            return false;
        }
    }

    /**
     * Exact match, or a `.suffix` match for a leading-dot entry.
     *
     * `.example.com` allows `api.example.com` but **not** `example.com` itself,
     * and never `notexample.com` — the dot is load-bearing. Suffix matching
     * without it would make `evil-example.com` a match for `example.com`.
     */
    private function hostIsAllowed(string $host): bool
    {
        foreach ($this->allowedHosts as $allowed) {
            $allowed = strtolower($allowed);

            if ($host === $allowed) {
                return true;
            }

            if (str_starts_with($allowed, '.') && str_ends_with($host, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws InvalidSource
     */
    private function assertNotPrivate(string $host): void
    {
        $host = trim($host, '[]');

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw InvalidSource::blockedHost($host, 'it is the loopback name');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            // A hostname. Not resolved here on purpose — see the class docblock;
            // the allow-list is what actually closes this.
            return;
        }

        $public = filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        if ($public === false) {
            throw InvalidSource::blockedHost($host, 'it is a private, loopback or reserved address');
        }

        // 100.64.0.0/10 — carrier-grade NAT. Not in PHP's reserved set, and
        // routinely the internal range in container and cloud networks.
        if ($this->inCgnat($host)) {
            throw InvalidSource::blockedHost($host, 'it is in the carrier-grade NAT range 100.64.0.0/10');
        }
    }

    private function inCgnat(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        $long = ip2long($ip);

        return $long !== false
            && $long >= ip2long('100.64.0.0')
            && $long <= ip2long('100.127.255.255');
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }
}
