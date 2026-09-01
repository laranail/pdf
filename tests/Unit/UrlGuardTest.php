<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Pdf\Exceptions\InvalidSource;
use Simtabi\Laranail\Pdf\Support\UrlGuard;
use Simtabi\Laranail\Pdf\Tests\TestCase;

/**
 * "Render this URL" asks a machine inside the network to fetch a URL a caller
 * chose. These are the requests it must refuse.
 */
final class UrlGuardTest extends TestCase
{
    private UrlGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = new UrlGuard;
    }

    /** @return array<string, array{string}> */
    public static function privateAddresses(): array
    {
        return [
            'loopback v4' => ['http://127.0.0.1/'],
            'loopback name' => ['http://localhost:8080/'],
            'loopback v6' => ['http://[::1]/'],
            'rfc1918 10/8' => ['http://10.0.0.5/'],
            'rfc1918 172.16/12' => ['http://172.16.4.1/'],
            'rfc1918 192.168/16' => ['http://192.168.1.1/'],
            'link-local' => ['http://169.254.1.1/'],
            'cgnat' => ['http://100.64.0.1/'],
            'zero' => ['http://0.0.0.0/'],
        ];
    }

    // -----------------------------------------------------------------
    // Schemes
    // -----------------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function badSchemes(): array
    {
        return [
            'file reads local disk' => ['file:///etc/passwd'],
            'gopher speaks to any port' => ['gopher://example.com:11211/'],
            'ftp' => ['ftp://example.com/x.pdf'],
            'data' => ['data:text/html,<h1>hi</h1>'],
        ];
    }

    // -----------------------------------------------------------------
    // The one that matters
    // -----------------------------------------------------------------

    #[Test]
    public function the_cloud_metadata_endpoint_is_refused(): void
    {
        // 169.254.169.254 returns IAM credentials on EC2, GCE and Azure. A URL
        // renderer that fetches it puts them in a PDF.
        $this->expectException(InvalidSource::class);
        $this->expectExceptionCode(6011);

        $this->guard->assertAllowed('http://169.254.169.254/latest/meta-data/iam/security-credentials/');
    }

    #[Test]
    #[DataProvider('privateAddresses')]
    public function private_and_reserved_addresses_are_refused(string $url): void
    {
        $this->expectException(InvalidSource::class);

        $this->guard->assertAllowed($url);
    }

    #[Test]
    #[DataProvider('badSchemes')]
    public function only_http_and_https_are_fetched(string $url): void
    {
        $this->expectException(InvalidSource::class);

        $this->guard->assertAllowed($url);
    }

    // -----------------------------------------------------------------
    // Credentials
    // -----------------------------------------------------------------

    #[Test]
    public function a_url_carrying_credentials_is_refused(): void
    {
        // The renderer would send them, and log the full URL alongside.
        $this->expectException(InvalidSource::class);
        $this->expectExceptionCode(6009);

        $this->guard->assertAllowed('https://user:secret@example.com/report');
    }

    #[Test]
    public function a_username_alone_is_also_refused(): void
    {
        $this->expectException(InvalidSource::class);

        $this->guard->assertAllowed('https://admin@example.com/report');
    }

    // -----------------------------------------------------------------
    // The allow-list
    // -----------------------------------------------------------------

    #[Test]
    public function an_empty_allow_list_permits_any_public_host(): void
    {
        $this->guard->assertAllowed('https://example.com/report');

        self::assertTrue($this->guard->allows('https://example.com/report'));
    }

    #[Test]
    public function a_non_empty_allow_list_refuses_everything_else(): void
    {
        $guard = new UrlGuard(allowedHosts: ['reports.example.com']);

        self::assertTrue($guard->allows('https://reports.example.com/x'));
        self::assertFalse($guard->allows('https://evil.example.com/x'));
        self::assertFalse($guard->allows('https://example.com/x'));
    }

    #[Test]
    public function a_leading_dot_matches_subdomains_and_nothing_else(): void
    {
        $guard = new UrlGuard(allowedHosts: ['.example.com']);

        self::assertTrue($guard->allows('https://api.example.com/x'));
        self::assertTrue($guard->allows('https://a.b.example.com/x'));

        // The dot is load-bearing. Plain suffix matching would let this through.
        self::assertFalse(
            $guard->allows('https://evil-example.com/x'),
            'Suffix matching without the separator matched a different domain.',
        );

        // `.example.com` is subdomains only — the apex is a separate entry.
        self::assertFalse($guard->allows('https://example.com/x'));
    }

    #[Test]
    public function the_allow_list_is_case_insensitive(): void
    {
        $guard = new UrlGuard(allowedHosts: ['Reports.Example.COM']);

        self::assertTrue($guard->allows('https://reports.example.com/x'));
    }

    #[Test]
    public function an_allow_listed_private_host_is_still_reachable(): void
    {
        // Rendering an internal reporting service is a legitimate use, and the
        // allow-list is the mechanism for it — so it has to win over the
        // private-address rule rather than be shadowed by it.
        $guard = new UrlGuard(allowedHosts: ['reports.internal'], blockPrivate: true);

        self::assertTrue($guard->allows('http://reports.internal/daily'));
    }

    #[Test]
    public function an_allow_listed_private_ip_literal_is_reachable_too(): void
    {
        // The case that made the allow-list authoritative. Without this, naming
        // 10.0.0.5 explicitly would still be refused, and the only way through
        // would be block_private_addresses => false — turning the check off for
        // every host in order to permit one. A narrow allow beats a broad
        // disable.
        $guard = new UrlGuard(allowedHosts: ['10.0.0.5'], blockPrivate: true);

        self::assertTrue($guard->allows('http://10.0.0.5/render'));
        self::assertFalse($guard->allows('http://10.0.0.6/render'), 'The allow-list stopped narrowing.');
        self::assertFalse(
            $guard->allows('http://169.254.169.254/latest/meta-data/'),
            'An allow-list must not open the metadata endpoint.',
        );
    }

    // -----------------------------------------------------------------
    // Malformed input
    // -----------------------------------------------------------------

    #[Test]
    public function a_url_with_no_host_is_refused(): void
    {
        $this->expectException(InvalidSource::class);
        $this->expectExceptionCode(6007);

        $this->guard->assertAllowed('not-a-url');
    }

    #[Test]
    public function allows_never_throws(): void
    {
        self::assertFalse($this->guard->allows('file:///etc/passwd'));
        self::assertFalse($this->guard->allows(''));
    }

    // -----------------------------------------------------------------
    // The documented limit
    // -----------------------------------------------------------------

    #[Test]
    public function a_hostname_resolving_privately_is_not_caught(): void
    {
        // Asserted so the limit is a known property rather than a surprise. The
        // check is lexical; resolving here would not fix it either, since the
        // renderer resolves again when it connects and can get a different
        // answer. The allow-list is the real defence, and the docs say so.
        self::assertTrue(
            $this->guard->allows('http://localtest.me/'),
            'If this now fails, DNS resolution was added — update the docs, which promise it is not done.',
        );
    }

    #[Test]
    public function config_drives_the_guard(): void
    {
        $guard = UrlGuard::fromConfig([
            'allowed_schemes' => ['https'],
            'allowed_hosts' => ['example.com'],
            'block_private_addresses' => true,
        ]);

        self::assertTrue($guard->allows('https://example.com/x'));
        self::assertFalse($guard->allows('http://example.com/x'), 'http was not in allowed_schemes.');
    }

    #[Test]
    public function turning_off_the_private_check_is_possible_and_explicit(): void
    {
        $guard = new UrlGuard(blockPrivate: false);

        self::assertTrue($guard->allows('http://127.0.0.1:9000/render'));
    }
}
