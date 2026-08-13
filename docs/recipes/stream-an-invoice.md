# Stream an invoice to the browser

Return the document. `PdfDocument` is `Responsable`, so the response streams without you arranging it.

```php
use Simtabi\Laranail\Pdf\Facades\Pdf;

final class InvoiceController
{
    public function show(Invoice $invoice)
    {
        return Pdf::view('invoices.show', ['invoice' => $invoice])
            ->withFilename("invoice-{$invoice->number}.pdf");
    }

    public function download(Invoice $invoice)
    {
        return Pdf::view('invoices.show', ['invoice' => $invoice])
            ->download("invoice-{$invoice->number}.pdf");
    }
}
```

`show()` opens in the browser's viewer; `download()` sets an attachment disposition. Neither writes
anything to disk, and neither renders until the response is sent.

See [`PdfDocument`](../tools/pdf-document.md).

---

[← Docs index](../../README.md#documentation)
