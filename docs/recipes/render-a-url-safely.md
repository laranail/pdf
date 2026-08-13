# Render a URL safely

`Pdf::url()` makes your server fetch a URL. If any part of that URL comes from user input, the
allow-list is not optional.

```php
// config/pdf.php
'security' => [
    'allowed_hosts' => ['reports.internal', '.example.com'],
    'block_private_addresses' => true,
],
```

```php
return Pdf::url("https://reports.internal/daily/{$date}");
```

A refused URL throws `InvalidSource` before anything is built:

```php
use Simtabi\Laranail\Pdf\Exceptions\InvalidSource;

try {
    return Pdf::url($request->string('url')->toString());
} catch (InvalidSource $e) {
    report($e);

    return back()->withErrors(['url' => 'That address cannot be rendered.']);
}
```

## What to know

- **The allow-list is the real defence.** The private-address check is lexical, so a hostname
  resolving to `127.0.0.1` passes it — and resolving in the guard would not help, because the renderer
  resolves again when it connects and can get a different answer.
- **A non-empty allow-list is authoritative**, including over the private-address check, so
  `10.0.0.5` can be named explicitly without disabling the check for every host.
- **Check what you shipped**: `php artisan laranail::pdf.doctor` reports the allow-list and asks the
  guard directly whether `169.254.169.254` is reachable.

See [Security](../configuration.md#security).

---

[← Docs index](../../README.md#documentation)
