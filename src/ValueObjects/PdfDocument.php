<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\ValueObjects;

use Closure;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Simtabi\Laranail\Pdf\Exceptions\RenderFailed;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A rendered PDF that has not necessarily been read yet.
 *
 * ## Why this is not a path
 *
 * The service this generalises returned `string` — a path under `storage_path()`
 * into a directory its own constructor created with `mkdir()`. Three things
 * follow from that, and all three are why the contract changed:
 *
 * - Every render wrote to local disk, whether or not anyone wanted the file
 *   kept. A controller streaming a PDF straight to the browser still left one
 *   behind, and nothing cleaned them up.
 * - The destination was fixed at construction, so a caller could not send the
 *   output to S3 without writing it locally first and copying it.
 * - `mkdir()` in a constructor means the class cannot be instantiated in any
 *   context where that path is not writable — a test, a read-only container, a
 *   queue worker with a different HOME.
 *
 * ## Why it is lazy
 *
 * The body is a `StreamInterface` produced on demand. `store()` hands that
 * stream to `Filesystem::putStream()`, so a 40 MB merge going to S3 never fully
 * enters PHP's memory. `contents()` is available and materialises everything,
 * which is fine for a 200 KB invoice and exactly what you do not want for the
 * merge — the difference is now the caller's to make, and visible in the code.
 *
 * The stream is memoized, so `contents()` after `size()` does not re-render.
 */
final class PdfDocument implements Responsable
{
    private ?StreamInterface $stream = null;

    /**
     * @param  Closure(): StreamInterface  $resolver
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        private readonly Closure $resolver,
        public readonly string $filename = 'document.pdf',
        public readonly string $driver = 'unknown',
        public readonly array $meta = [],
    ) {}

    /**
     * The PSR-7 body, rendered on first access and reused after.
     */
    public function stream(): StreamInterface
    {
        return $this->stream ??= ($this->resolver)();
    }

    /**
     * The whole document in memory.
     *
     * Fine for anything page-sized. For a merge or a large report prefer
     * `store()` or `toResponse()`, both of which stream.
     */
    public function contents(): string
    {
        $stream = $this->stream();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $stream->getContents();
    }

    /**
     * Bytes, when the stream knows without reading itself.
     */
    public function size(): ?int
    {
        return $this->stream()->getSize();
    }

    /**
     * Write to a filesystem disk without materialising the document.
     *
     * This is the reason the class exists. `FilesystemAdapter::put()` accepts a
     * PSR-7 stream and pipes it, so the peak memory of storing a 40 MB merge to
     * S3 is a buffer rather than 40 MB — where a path-returning API would have
     * written it locally first and then read the whole thing back to copy it.
     *
     * **This consumes the document.** `put()` detaches the stream and Flysystem
     * closes the handle, so the memoized copy is spent afterwards. The memo is
     * therefore cleared, and the next read re-renders — which for Gotenberg
     * means a second HTTP request. Storing to two disks costs two renders; that
     * is the honest price of streaming, and the alternative was a second
     * `store()` silently writing zero bytes. Call `contents()` once and write
     * that if you need the same bytes in several places.
     *
     * @param  array<string, mixed>|string  $options
     */
    public function store(string $path, ?string $disk = null, array|string $options = []): string
    {
        $stream = $this->stream();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        try {
            Storage::disk($disk)->put($path, $stream, $options);
        } finally {
            $this->stream = null;
        }

        return $path;
    }

    /**
     * Write to a local absolute path.
     *
     * Throws rather than returning on failure. It previously swallowed a failed
     * `fopen()` and returned the path anyway, so an unwritable directory — the
     * common case — reported success and the caller went on to reference a file
     * that was never created. A short write is treated the same way: a full
     * disk stops `fwrite()` early, and a truncated PDF that claims to be whole
     * is worse than an exception.
     *
     * @throws RenderFailed when the file cannot be opened or fully written
     */
    public function saveTo(string $absolutePath): string
    {
        $handle = @fopen($absolutePath, 'wb');

        if ($handle === false) {
            throw RenderFailed::from(
                $this->driver,
                'save',
                new RuntimeException("Could not open [{$absolutePath}] for writing."),
                ['path' => $absolutePath],
            );
        }

        $stream = $this->stream();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        try {
            while (! $stream->eof()) {
                $chunk = $stream->read(8192);
                $written = fwrite($handle, $chunk);

                if ($written === false || $written < strlen($chunk)) {
                    throw RenderFailed::from(
                        $this->driver,
                        'save',
                        new RuntimeException("Short write to [{$absolutePath}] — the disk may be full."),
                        ['path' => $absolutePath],
                    );
                }
            }
        } finally {
            fclose($handle);
        }

        return $absolutePath;
    }

    /**
     * An inline response — the browser's PDF viewer opens it.
     */
    public function inline(?string $filename = null): StreamedResponse
    {
        return $this->response('inline', $filename);
    }

    /**
     * An attachment response — the browser downloads it.
     */
    public function download(?string $filename = null): StreamedResponse
    {
        return $this->response('attachment', $filename);
    }

    /**
     * `Responsable`, so a controller can `return $pdf;` and get streaming for
     * free rather than opting into it.
     */
    public function toResponse($request): StreamedResponse
    {
        return $this->inline();
    }

    public function withFilename(string $filename): self
    {
        $clone = new self($this->resolver, $filename, $this->driver, $this->meta);
        $clone->stream = $this->stream;

        return $clone;
    }

    private function response(string $disposition, ?string $filename): StreamedResponse
    {
        $name = $filename ?? $this->filename;
        $stream = $this->stream();

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, addslashes($name)),
            'X-Pdf-Driver' => $this->driver,
        ];

        if (($size = $stream->getSize()) !== null) {
            $headers['Content-Length'] = (string) $size;
        }

        return new StreamedResponse(function () use ($stream): void {
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            while (! $stream->eof()) {
                echo $stream->read(8192);
                flush();
            }
        }, Response::HTTP_OK, $headers);
    }
}
