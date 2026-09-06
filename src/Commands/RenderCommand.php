<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Commands;

use Simtabi\Laranail\Pdf\PdfManager;
use Simtabi\Laranail\Pdf\Exceptions\PdfException;
use Simtabi\Laranail\Pdf\ValueObjects\PdfDocument;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Pdf\ValueObjects\RenderOptions;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * Render one thing to one file, from the shell.
 *
 * Mostly for proving a driver works end to end without writing a route — the
 * step between `doctor` saying a driver is available and trusting it in an
 * application.
 */
final class RenderCommand extends Command
{
    use SupportsNamespacedNames;

    /** @var string */
    protected $signature = 'laranail::pdf.render
        {source           : A URL, a file path, or - to read HTML from stdin}
        {output           : Where to write the PDF}
        {--driver=        : Render with this driver instead of the default}
        {--paper=         : Paper size (a3, a4, a5, letter, legal, tabloid, ledger)}
        {--orientation=   : portrait or landscape}
        {--merge=*        : Additional PDFs to merge with the source}';

    /** @var string */
    protected $description = 'Render a URL, file or HTML string to a PDF.';

    public function handle(PdfManager $pdf): int
    {
        $source = $this->stringArgument('source');
        $output = $this->stringArgument('output');
        $driver = $this->stringOption('driver');

        $options = new RenderOptions(
            filename: basename($output),
            paperSize: $this->stringOption('paper'),
            orientation: $this->stringOption('orientation'),
        );

        try {
            $document = $this->build($pdf, $source, $options, $driver);
            $document->saveTo($output);
        } catch (PdfException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Wrote %s (%s) with the %s driver.',
            $output,
            $this->describeSize($output),
            $document->driver,
        ));

        return self::SUCCESS;
    }

    private function build(PdfManager $pdf, string $source, RenderOptions $options, ?string $driver): PdfDocument
    {
        $merge = array_values(array_filter((array) $this->option('merge'), is_string(...)));

        if ($merge !== []) {
            return $pdf->merge([$source, ...$merge], $options, $driver);
        }

        if ($source === '-') {
            return $pdf->html((string) file_get_contents('php://stdin'), $options, $driver);
        }

        if (preg_match('#^https?://#i', $source) === 1) {
            return $pdf->url($source, $options, $driver);
        }

        if (str_ends_with(strtolower($source), '.html') || str_ends_with(strtolower($source), '.htm')) {
            return $pdf->html((string) file_get_contents($source), $options, $driver);
        }

        return $pdf->convert($source, $options, $driver);
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }

    private function describeSize(string $path): string
    {
        if (! is_file($path)) {
            return 'unknown size';
        }

        $bytes = filesize($path);

        return $bytes === false ? 'unknown size' : number_format($bytes / 1024, 1) . ' KB';
    }
}
