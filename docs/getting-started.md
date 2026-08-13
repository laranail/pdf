# Getting started

Rendering a PDF, sending it somewhere, and the two things that will surprise you if nobody mentions them.

## Render

```php
use Simtabi\Laranail\Pdf\Facades\Pdf;

$document = Pdf::html('<h1>Invoice #1042</h1>');
$document = Pdf::view('invoices.show', ['invoice' => $invoice]);
$document = Pdf::url('https://example.com/report');
$document = Pdf::convert(storage_path('proposals/draft.docx'));
$document = Pdf::merge([$coverPath, $bodyPath, $appendixPath]);
```

Every one returns a `PdfDocument`. **None of them has rendered anything yet.**

## Send it

```php
// Stream to the browser. PdfDocument is Responsable, so this is enough.
return Pdf::view('invoices.show', compact('invoice'));

// Force a download, with a name.
return Pdf::view('invoices.show', compact('invoice'))->download("invoice-{$invoice->id}.pdf");

// To a filesystem disk, piped rather than buffered.
Pdf::merge($paths)->store("statements/{$year}.pdf", 's3');

// To a local path.
Pdf::html($html)->saveTo(storage_path('app/report.pdf'));

// In memory, when it is small and you need the bytes.
$bytes = Pdf::html($html)->contents();
```

## Surprise one: nothing renders until you read it

```php
$document = Pdf::url('https://example.com/huge-report');   // no HTTP request yet
$document->contents();                                      // now it renders
```

This is the point of the design. A controller that builds a document and then decides not to send it
pays nothing, and leaves nothing on disk. The implementation this generalises returned a path — so
every render wrote a file under `storage_path()` whether or not anyone wanted it kept, and nothing ever
cleaned them up.

The render is memoized, so reading twice costs one render.

## Surprise two: `store()` consumes the document

Streaming hands the stream away — Laravel's `put()` detaches it and Flysystem closes the handle. So a
read after `store()` **re-renders**, which for Gotenberg means a second HTTP request:

```php
$document = Pdf::html($html);
$document->store('a.pdf', 's3');     // one render
$document->store('b.pdf', 'local');  // a second render
```

If you need the same bytes in more than one place, materialise once:

```php
$bytes = Pdf::html($html)->contents();
Storage::disk('s3')->put('a.pdf', $bytes);
Storage::disk('local')->put('b.pdf', $bytes);
```

The alternative — keeping the stream — would have made the second `store()` silently write zero bytes
and report success. Re-rendering is the honest cost of streaming.

## Options

```php
Pdf::view('reports.annual', $data, [
    'paperSize' => 'a4',
    'orientation' => 'landscape',
    'marginTop' => 0.5,
    'printBackground' => true,
    'filename' => 'annual-report.pdf',
]);
```

Only genuinely portable options are typed; anything driver-specific goes in `extra`. See
[Render options](tools/render-options.md).

## Choosing a driver

```php
Pdf::html($html, driver: 'dompdf');
```

Ask for something a driver cannot do and it fails immediately, naming one that can:

```
The [dompdf] PDF driver does not support Merge PDFs. Drivers that do: gotenberg.
```

## Next

- [The driver seam](tools/drivers.md) — how capabilities work, and registering your own driver
- [PdfDocument](tools/pdf-document.md) — the full return contract
- [Configuration](configuration.md) — including the SSRF guard on URL rendering

---

[← Docs index](../README.md#documentation)
