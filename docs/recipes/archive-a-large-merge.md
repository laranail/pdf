# Archive a large merge to S3

Merging a year of statements produces a document you do not want in memory. `store()` pipes it.

```php
use Simtabi\Laranail\Pdf\Facades\Pdf;

$paths = $statements->map(fn (Statement $s): string => $s->absolutePath())->all();

Pdf::merge($paths, ['filename' => "statements-{$year}.pdf"])
    ->store("archives/{$customer->id}/{$year}.pdf", 's3');
```

The PSR-7 body goes straight to the filesystem, so peak memory is a buffer rather than the document.

**Do not call `store()` twice** if you need it on two disks — streaming consumes the document, so the
second call re-renders (and for Gotenberg, re-requests). Materialise once instead:

```php
$bytes = Pdf::merge($paths)->contents();

Storage::disk('s3')->put("archives/{$year}.pdf", $bytes);
Storage::disk('local')->put("cache/{$year}.pdf", $bytes);
```

That is the right trade only when the document fits in memory. For a genuinely large merge, store once
and copy on the filesystem.

See [`PdfDocument`](../tools/pdf-document.md).

---

[← Docs index](../../README.md#documentation)
