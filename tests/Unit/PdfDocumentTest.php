<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Tests\Unit;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\StreamInterface;
use Simtabi\Laranail\Pdf\Exceptions\PdfException;
use Simtabi\Laranail\Pdf\Tests\TestCase;
use Simtabi\Laranail\Pdf\ValueObjects\PdfDocument;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The return contract, and the reason it is not a path.
 */
final class PdfDocumentTest extends TestCase
{
    private int $renders = 0;

    // -----------------------------------------------------------------
    // Laziness
    // -----------------------------------------------------------------

    #[Test]
    public function constructing_renders_nothing(): void
    {
        // The whole point of the change: a controller that builds a document
        // and then decides not to send it should not have paid to render it,
        // and should certainly not have left a file on disk.
        $this->document();

        self::assertSame(0, $this->renders);
    }

    #[Test]
    public function the_render_happens_once_however_many_times_it_is_read(): void
    {
        $document = $this->document();

        $document->size();
        $document->contents();
        $document->contents();

        self::assertSame(1, $this->renders);
    }

    #[Test]
    public function contents_is_repeatable(): void
    {
        // A memoized stream that was already read to the end would return an
        // empty string the second time without the rewind.
        $document = $this->document('%PDF-1.4 body');

        self::assertSame('%PDF-1.4 body', $document->contents());
        self::assertSame('%PDF-1.4 body', $document->contents());
    }

    // -----------------------------------------------------------------
    // Storing
    // -----------------------------------------------------------------

    #[Test]
    public function it_stores_to_a_disk(): void
    {
        Storage::fake('reports');

        $path = $this->document('%PDF-1.4 stored')->store('2026/invoice.pdf', 'reports');

        self::assertSame('2026/invoice.pdf', $path);
        Storage::disk('reports')->assertExists('2026/invoice.pdf');
        self::assertSame('%PDF-1.4 stored', Storage::disk('reports')->get('2026/invoice.pdf'));
    }

    #[Test]
    public function storing_twice_writes_the_same_bytes_both_times(): void
    {
        // The regression this guards: Laravel's put() calls detach() on a PSR-7
        // stream and Flysystem closes the handle, so the memoized copy is spent.
        // Without clearing the memo, the second store silently wrote zero bytes
        // and reported success.
        Storage::fake('reports');

        $document = $this->document('%PDF-1.4 twice');
        $document->store('a.pdf', 'reports');
        $document->store('b.pdf', 'reports');

        self::assertSame('%PDF-1.4 twice', Storage::disk('reports')->get('a.pdf'));
        self::assertSame(
            '%PDF-1.4 twice',
            Storage::disk('reports')->get('b.pdf'),
            'The second store wrote nothing — the stream was consumed and not rewound.',
        );
    }

    #[Test]
    public function storing_consumes_the_document_and_a_second_read_re_renders(): void
    {
        // The cost of the fix above, asserted rather than hidden: streaming to a
        // disk hands the stream away, so anything read afterwards has to be
        // produced again. For Gotenberg that is a second HTTP request. Read
        // contents() once if you need the same bytes in several places.
        Storage::fake('reports');

        $document = $this->document('%PDF-1.4 cost');
        $document->store('a.pdf', 'reports');

        self::assertSame(1, $this->renders);

        $document->contents();

        self::assertSame(2, $this->renders, 'A read after store() should re-render, not return nothing.');
    }

    #[Test]
    public function saving_to_an_unwritable_path_throws_rather_than_reporting_success(): void
    {
        // The regression this guards: saveTo() swallowed a failed fopen() and
        // returned the path anyway, so an unwritable directory — the common
        // case — looked like a successful write and the caller went on to
        // reference a file that was never created.
        $document = $this->document();

        $this->expectException(PdfException::class);

        $document->saveTo('/no/such/directory/report.pdf');
    }

    #[Test]
    public function a_failed_save_leaves_no_file_behind(): void
    {
        $document = $this->document();

        try {
            $document->saveTo('/no/such/directory/report.pdf');
        } catch (PdfException) {
            // expected
        }

        self::assertFileDoesNotExist('/no/such/directory/report.pdf');
    }

    #[Test]
    public function it_saves_to_a_local_path(): void
    {
        $target = sys_get_temp_dir().'/laranail-pdf-'.bin2hex(random_bytes(6)).'.pdf';

        try {
            $this->document('%PDF-1.4 local')->saveTo($target);

            self::assertFileExists($target);
            self::assertSame('%PDF-1.4 local', file_get_contents($target));
        } finally {
            @unlink($target);
        }
    }

    // -----------------------------------------------------------------
    // Responses
    // -----------------------------------------------------------------

    #[Test]
    public function it_is_responsable_and_streams_inline_by_default(): void
    {
        $response = $this->document()->toResponse(request());

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function download_sets_an_attachment_disposition(): void
    {
        $response = $this->document()->download();

        self::assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('invoice.pdf', (string) $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function a_response_streams_rather_than_buffering(): void
    {
        $response = $this->document('%PDF-1.4 streamed')->inline();

        ob_start();
        $response->sendContent();
        $sent = (string) ob_get_clean();

        self::assertSame('%PDF-1.4 streamed', $sent);
    }

    #[Test]
    public function the_filename_can_be_overridden_per_response(): void
    {
        $response = $this->document()->download('statement-2026.pdf');

        self::assertStringContainsString('statement-2026.pdf', (string) $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function a_quoted_filename_cannot_break_out_of_the_header(): void
    {
        $response = $this->document()->download('in"jected.pdf');

        self::assertStringNotContainsString(
            '"in"jected',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    #[Test]
    public function with_filename_carries_the_rendered_stream_across(): void
    {
        $document = $this->document();
        $document->contents();

        $renamed = $document->withFilename('renamed.pdf');

        self::assertSame('renamed.pdf', $renamed->filename);
        self::assertSame(1, $this->renders, 'Renaming re-rendered the document.');
    }

    #[Test]
    public function the_driver_that_rendered_it_is_recorded(): void
    {
        $response = $this->document()->inline();

        self::assertSame('test', $response->headers->get('X-Pdf-Driver'));
    }

    private function document(string $body = '%PDF-1.4 fake'): PdfDocument
    {
        $this->renders = 0;

        return new PdfDocument(
            resolver: function () use ($body): StreamInterface {
                $this->renders++;

                return Utils::streamFor($body);
            },
            filename: 'invoice.pdf',
            driver: 'test',
        );
    }
}
