# Render options

The settings both drivers can honour, plus an escape hatch for the ones only one of them understands.
Backed by `Simtabi\Laranail\Pdf\ValueObjects\RenderOptions`.

```php
Pdf::view('reports.annual', $data, [
    'paperSize' => 'a4',
    'orientation' => 'landscape',
    'marginTop' => 0.5,
    'printBackground' => true,
    'filename' => 'annual-report.pdf',
]);
```

An array is converted with `RenderOptions::fromArray()`; you can pass the object directly instead.

| Option | Type | Honoured by |
|---|---|---|
| `filename` | `?string` | both |
| `paperSize` | `?string` | both |
| `orientation` | `?string` (`portrait`/`landscape`) | both |
| `marginTop` / `marginRight` / `marginBottom` / `marginLeft` | `?float` (inches) | gotenberg |
| `header` / `footer` | `?string` (HTML) | gotenberg |
| `printBackground` | `?bool` | gotenberg |
| `extra` | `array` | driver-specific |

## Why the list is short

A union of everything Gotenberg and Dompdf accept would be a large object where most fields silently do
nothing depending on which driver ran — promising a portability the drivers cannot deliver. So the
typed fields are the genuinely common ones, and anything driver-specific goes in `extra`, where it
reads as what it is.

Unknown keys passed to `fromArray()` land in `extra` automatically.

## Paper sizes

`a3` · `a4` · `a5` · `a6` · `letter` · `legal` · `tabloid` · `ledger`

Gotenberg wants inches rather than a name, so `Drivers\PaperSize` translates. One table, so `a4` means
the same thing to every driver. An unknown name falls back to A4 rather than throwing — a typo in a
paper size is not worth failing a render over, and the wrong-but-reasonable page is obvious to anyone
who looks at it.

```php
use Simtabi\Laranail\Pdf\Drivers\PaperSize;

PaperSize::inches('letter');   // [8.5, 11.0]
PaperSize::isKnown('a9');      // false
PaperSize::names();
```

Landscape swaps width and height rather than being a separate size.

---

[← Docs index](../../README.md#documentation)
