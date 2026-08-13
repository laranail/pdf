# The driver seam

Four capability interfaces, a string-keyed registry, and one rule: a driver cannot claim what it has
not implemented. Backed by `Simtabi\Laranail\Pdf\{DriverRegistry, Contracts\PdfDriver, Enums\Capability}`.

## The four capabilities

| Capability | Interface | Method |
|---|---|---|
| `Capability::Html` | `Contracts\Capabilities\RendersHtml` | `html(string $html, ?RenderOptions $options)` |
| `Capability::Url` | `Contracts\Capabilities\RendersUrl` | `url(string $url, ?RenderOptions $options)` |
| `Capability::Office` | `Contracts\Capabilities\ConvertsDocuments` | `convert(string $path, ?RenderOptions $options)` |
| `Capability::Merge` | `Contracts\Capabilities\MergesPdfs` | `merge(array $paths, ?RenderOptions $options)` |

Each extends `PdfDriver`. `Capability::contract()` maps a case to its interface, and that pairing is
the single source of truth — the enum cannot describe a capability the type system does not enforce.

## What the bundled drivers do

| Driver | Html | Url | Office | Merge |
|---|:---:|:---:|:---:|:---:|
| `gotenberg` | ✓ | ✓ | ✓ | ✓ |
| `dompdf` | ✓ | | | |

## Asking

```php
use Simtabi\Laranail\Pdf\Enums\Capability;
use Simtabi\Laranail\Pdf\Facades\Pdf;

Pdf::supports(Capability::Merge);              // the default driver
Pdf::supports(Capability::Merge, 'dompdf');    // false

Pdf::driver('gotenberg')->capabilities();      // [Html, Url, Office, Merge]
Pdf::driver()->isAvailable();
Pdf::driver()->unavailableReason();            // null when available
```

`isAvailable()` and `unavailableReason()` are separate so a doctor command can distinguish "the
optional package is not installed" from "it is, but nothing configured it" — and can ask without
triggering the failure.

## Being refused

```php
Pdf::merge($paths, driver: 'dompdf');
```

```
Simtabi\Laranail\Pdf\Exceptions\UnsupportedCapability (6002)
The [dompdf] PDF driver does not support Merge PDFs. Drivers that do: gotenberg.
```

Thrown at resolve time, before any work starts. The alternatives list only includes drivers that are
both capable **and available** — naming one whose package is not installed would send the reader down
the wrong path.

## Registering your own

```php
use Illuminate\Contracts\Container\Container;
use Simtabi\Laranail\Pdf\Contracts\Capabilities\RendersHtml;
use Simtabi\Laranail\Pdf\Drivers\Driver;
use Simtabi\Laranail\Pdf\Facades\Pdf;
use Simtabi\Laranail\Pdf\ValueObjects\PdfDocument;
use Simtabi\Laranail\Pdf\ValueObjects\RenderOptions;

final class WkhtmltopdfDriver extends Driver implements RendersHtml
{
    public function name(): string
    {
        return 'wkhtmltopdf';
    }

    public function unavailableReason(): ?string
    {
        return is_executable('/usr/bin/wkhtmltopdf') ? null : 'wkhtmltopdf is not on PATH.';
    }

    public function html(string $html, ?RenderOptions $options = null): PdfDocument
    {
        return new PdfDocument(
            resolver: fn () => $this->run($html),   // returns a StreamInterface
            filename: $options?->filenameOr('document.pdf') ?? 'document.pdf',
            driver: $this->name(),
        );
    }
}
```

Register it in a service provider's `boot()`:

```php
Pdf::extend('wkhtmltopdf', fn (Container $app): WkhtmltopdfDriver => new WkhtmltopdfDriver);
```

Three things the base gives you, and one rule:

- `capabilities()` and `supports()` are derived from `instanceof` and are **`final`** — you cannot
  claim `Merge` without implementing `MergesPdfs`.
- `isAvailable()` is `unavailableReason() === null`; implement the latter.
- `assertAvailable()` throws `DriverUnavailable` — call it inside the resolver, not the factory, so
  construction stays cheap.
- **Contain your exceptions.** Wrap whatever your engine throws in `RenderFailed::from()`. A caller
  catching `PdfException` must not have to know what is underneath.

## Why not `Illuminate\Support\Manager`

`Manager` resolves `'foo'` by calling `createFooDriver()`. A config value must never reach a method
name. Here the map is data and resolution is an array lookup, so an unknown name gives
`DriverNotFound` listing what is registered rather than a `BadMethodCallException` that leaks the
class's shape.

An enum of drivers was the other option, and closes the set — which fails the case above.

---

[← Docs index](../../README.md#documentation)
