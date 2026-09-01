<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Commands;

use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\RendersHtml;
use Simtabi\Laranail\Pdf\DriverRegistry;
use Simtabi\Laranail\Pdf\Exceptions\PdfException;
use Simtabi\Laranail\Pdf\Support\DriverReport;
use Simtabi\Laranail\Pdf\Support\PdfConfig;
use Simtabi\Laranail\Pdf\Support\UrlGuard;
use Throwable;

/**
 * Answers what is actually wrong, not what the config file looks like.
 *
 * PDF rendering fails in ways that leave the application looking fine: an
 * optional package that was never installed, a Gotenberg container that moved,
 * an SSRF allow-list nobody filled in so the URL renderer will fetch anything.
 * Each of those reads as healthy from `config:show`.
 */
final class DoctorCommand extends Command
{
    use SupportsNamespacedNames;

    /** @var string */
    protected $signature = 'laranail::pdf.doctor
        {--driver=  : Check only this driver}
        {--probe    : Actually render a one-line document with each available driver}
        {--json     : Machine-readable output}';

    /** @var string */
    protected $description = 'Check which PDF drivers are installed, configured and actually working.';

    public function handle(DriverRegistry $registry, UrlGuard $guard, PdfConfig $config): int
    {
        $only = $this->option('driver');
        $only = is_string($only) && $only !== '' ? $only : null;

        /** @var list<DriverReport> $reports */
        $reports = [];

        foreach ($registry->names() as $name) {
            if ($only !== null && $name !== $only) {
                continue;
            }

            $reports[] = $this->inspect($registry, $name);
        }

        if ($reports === []) {
            $this->error('No matching driver. Registered: '.implode(', ', $registry->names()).'.');

            return self::FAILURE;
        }

        $security = $this->inspectSecurity($guard, $config);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'default' => $registry->defaultName(),
                'drivers' => array_combine(
                    array_map(static fn (DriverReport $r): string => $r->name, $reports),
                    array_map(static fn (DriverReport $r): array => $r->toArray(), $reports),
                ),
                'security' => $security,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->render($registry->defaultName(), $reports, $security);
        }

        $usable = array_filter($reports, static fn (DriverReport $r): bool => $r->available);

        return $usable === [] ? self::FAILURE : self::SUCCESS;
    }

    private function inspect(DriverRegistry $registry, string $name): DriverReport
    {
        try {
            $driver = $registry->driver($name);
        } catch (PdfException $e) {
            return DriverReport::unresolvable($name, $e->getMessage());
        }

        $report = new DriverReport(
            name: $name,
            available: $driver->isAvailable(),
            reason: $driver->unavailableReason(),
            capabilities: $driver->capabilities(),
        );

        if (! $this->option('probe') || ! $driver->isAvailable() || ! $driver instanceof RendersHtml) {
            return $report;
        }

        return $this->probe($driver, $report);
    }

    /**
     * Render something trivial and see whether bytes come back.
     *
     * Opt-in, because it is the only check here that leaves the process — and
     * against Gotenberg that means a real HTTP request to a real container.
     */
    private function probe(RendersHtml $driver, DriverReport $report): DriverReport
    {
        try {
            $started = hrtime(true);
            $bytes = strlen($driver->html('<p>ok</p>')->contents());
            $ms = (int) round((hrtime(true) - $started) / 1_000_000);

            return $report->withProbe($bytes > 0, $bytes, $ms);
        } catch (Throwable $e) {
            return $report->withProbe(false, error: $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function inspectSecurity(UrlGuard $guard, PdfConfig $config): array
    {
        return [
            'allowed_hosts' => $config->strings('security.allowed_hosts'),
            'block_private_addresses' => $config->bool('security.block_private_addresses', true),
            // Not a config read but an actual question put to the guard, so a
            // rule that stops covering this stops claiming to.
            'metadata_endpoint_refused' => ! $guard->allows('http://169.254.169.254/latest/meta-data/'),
        ];
    }

    /**
     * @param  list<DriverReport>  $reports
     * @param  array<string, mixed>  $security
     */
    private function render(string $default, array $reports, array $security): void
    {
        $this->table(
            ['Driver', 'Available', 'Capabilities', 'Probe', 'Why not'],
            array_map(static fn (DriverReport $r): array => [
                $r->name.($r->name === $default ? ' (default)' : ''),
                $r->available ? 'yes' : 'no',
                $r->describeCapabilities(),
                $r->describeProbe(),
                $r->probeError ?? $r->reason ?? '',
            ], $reports),
        );

        $this->line('');
        $this->line('<comment>URL rendering</comment>');

        /** @var list<string> $hosts */
        $hosts = $security['allowed_hosts'];

        $this->line('  allowed hosts:   '.($hosts === [] ? 'any' : implode(', ', $hosts)));
        $this->line('  private blocked: '.($security['block_private_addresses'] === true ? 'yes' : 'NO'));

        if ($security['metadata_endpoint_refused'] !== true) {
            $this->warn(
                '  The cloud metadata endpoint (169.254.169.254) is reachable by the URL renderer. '
                .'If any caller can influence the URL, that is credential disclosure.',
            );
        }

        if ($hosts === []) {
            $this->line(
                '  <comment>Note:</comment> the private-address check is lexical, so a hostname resolving to an '
                .'internal address still passes it. Fill in allowed_hosts if callers can influence the URL.',
            );
        }
    }
}
