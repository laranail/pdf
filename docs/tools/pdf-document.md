# `PdfDocument`

What every render returns. A lazy handle on a PSR-7 stream — not a path, and not bytes until you ask.
Backed by `Simtabi\Laranail\Pdf\ValueObjects\PdfDocument`.

## Reading it

| Method | Returns | Memory |
|---|---|---|
| `stream()` | `StreamInterface` | streams |
| `contents()` | `string` | the whole document |
| `size()` | `?int` | streams, when the stream knows |
| `store($path, $disk, $options)` | `string` (the path) | streams |
| `saveTo($absolutePath)` | `string` (the path) | streams, 8 KB at a time |
| `inline(?$filename)` | `StreamedResponse` | streams |
| `download(?$filename)` | `StreamedResponse` | streams |
| `toResponse($request)` | `Response` | streams |

Read-only properties: `filename`, `driver`, `meta`.

## Nothing renders until you read

```php
$document = Pdf::url('https://example.com/report');  // no HTTP request yet
$document->contents();                                // renders now
$document->contents();                                // memoized — no second render
```

A controller that builds a document and then decides not to send it pays nothing and leaves nothing on
disk. The implementation this generalises returned a path, so every render wrote a file under
`storage_path()` whether anyone wanted it or not.

## Streaming to a disk

```php
Pdf::merge($paths)->store("statements/{$year}.pdf", 's3');
```

`store()` hands the PSR-7 stream to `Filesystem::put()`, which pipes it — so a 40 MB merge going to S3
never fully enters PHP's memory.

### It consumes the document

Laravel's `put()` calls `detach()` and Flysystem closes the handle, so the memoized stream is spent.
The memo is cleared and the next read **re-renders** — for Gotenberg, a second HTTP request.

```php
$document = Pdf::html($html);
$document->store('a.pdf', 's3');       // renders once
$document->store('b.pdf', 'local');    // renders again
```

Materialise once if you need the same bytes in several places:

```php
$bytes = Pdf::html($html)->contents();
```

The alternative was leaving the spent stream in place, which made the second `store()` silently write
zero bytes and report success. A visible second render is the better failure.

## Responses

`PdfDocument` implements `Responsable`, so returning one from a controller streams it:

```php
public function show(Invoice $invoice)
{
    return Pdf::view('invoices.show', compact('invoice'));
}
```

Inline by default. For a download:

```php
return Pdf::view('invoices.show', compact('invoice'))->download("invoice-{$invoice->id}.pdf");
```

Responses carry `Content-Type: application/pdf`, a `Content-Disposition` with the filename escaped, an
`X-Pdf-Driver` header naming what rendered it, and `Content-Length` when the stream knows its size.

## Renaming

```php
$document->withFilename('statement-2026.pdf');
```

Returns a new document carrying the already-rendered stream across, so renaming never costs a render.

---

[← Docs index](../../README.md#documentation)
