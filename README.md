# laranail/pdf

[![Packagist](https://img.shields.io/packagist/v/laranail/pdf.svg?style=flat-square)](https://packagist.org/packages/laranail/pdf)
[![Tests](https://img.shields.io/github/actions/workflow/status/laranail/pdf/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/laranail/pdf/actions/workflows/tests.yml)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/laranail/pdf/static-analysis.yml?branch=main&label=static%20analysis&style=flat-square)](https://github.com/laranail/pdf/actions/workflows/static-analysis.yml)
[![License MIT](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

> PDF rendering for Laravel behind a capability-checked driver seam, returning a lazy document that streams instead of a path on disk.

Requires PHP `^8.4.1 || ^8.5` and Laravel `^13.0`.

## Install

```bash
composer require laranail/pdf
```

Then install a driver — the rendering engines are optional dependencies, so you only pull in the one you use:

```bash
composer require gotenberg/gotenberg-php guzzlehttp/guzzle   # HTML, URL, Office, merge
composer require dompdf/dompdf                               # HTML, in-process
```

```bash
php artisan laranail::pdf.install
php artisan laranail::pdf.doctor
```

## Quick start

```php
use Simtabi\Laranail\Pdf\Facades\Pdf;

// Stream it to the browser — the controller returns the document directly.
return Pdf::view('invoices.show', ['invoice' => $invoice]);

// Or send it somewhere without it ever fully entering memory.
Pdf::merge($statementPaths)->store("statements/{$year}.pdf", 's3');
```

## The two decisions worth knowing

**A driver cannot claim a capability it has not implemented.** `supports()` is derived from `instanceof`
against four capability interfaces, so `Pdf::merge(..., driver: 'dompdf')` fails with a message naming
the drivers that could have done it — before any work starts, rather than at render time.

**Rendering returns a lazy `PdfDocument`, never a path.** Nothing renders until something reads it, and
`store()` pipes the PSR-7 body straight to the filesystem, so a 40 MB merge going to S3 never fully
enters PHP's memory. The service this generalises returned a path under `storage_path()` from a
directory its own constructor created with `mkdir()` — every render wrote to local disk whether anyone
wanted the file or not.

## <a name="documentation"></a>Documentation

Full documentation is at **[opensource.simtabi.com/documentation/laranail/pdf](https://opensource.simtabi.com/documentation/laranail/pdf/)** — installation, getting started, the capability-checked driver seam and how to register your own driver, the lazy `PdfDocument` return contract (streaming, storing, `Responsable` responses), the bundled Gotenberg and Dompdf drivers and what each can do, render options, the URL-rendering SSRF guard and what it does and does not protect against, the `laranail::pdf.{doctor,render,install}` commands, configuration, and the release process.

## License

MIT. See [LICENSE](LICENSE).
