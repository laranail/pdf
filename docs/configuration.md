# Configuration

Everything in `config/pdf.php`, published by `php artisan laranail::pdf.install`. Keys live under
`laranail.pdf.*`.

## Default driver

```php
'default' => env('LARANAIL_PDF_DRIVER', 'gotenberg'),
'default_filename' => 'document.pdf',
```

The driver used when a call does not name one. Both bundled drivers are always registered; whether
either can run depends on its optional package being installed.

## Drivers

### `gotenberg`

| Key | Default | Notes |
|---|---|---|
| `base_url` | `http://localhost:3000` | The Gotenberg instance. **Config only** — there is no per-call override and no `baseUrl()` setter, because a renderer whose target host is caller-supplied is an SSRF primitive. |
| `timeout` | `60` | Seconds. |
| `verify_ssl` | `true` | |
| `ca_cert` | `null` | A CA bundle, e.g. mkcert's. A set-but-missing path falls back to the **system trust store**, never to no verification — silently disabling TLS because a path is wrong is how a development shortcut reaches production. |

### `dompdf`

| Key | Default | Notes |
|---|---|---|
| `paper` | `a4` | |
| `orientation` | `portrait` | |
| `options` | see below | Passed to `Dompdf\Options`. |

Only the keys in `DompdfDriver::SETTABLE` are acted on. That is an explicit list rather than
`'set'.ucfirst($key)` for the same reason the registry is not an `Illuminate\Support\Manager`: a config
string must never become a method name.

```php
'options' => [
    'isRemoteEnabled' => env('LARANAIL_PDF_DOMPDF_REMOTE', false),
    'defaultFont' => 'DejaVu Sans',
],
```

Two of these matter:

- **`isRemoteEnabled` defaults to `false`.** Turning it on makes every `<img src>` and `@import` in the
  input HTML a request your server performs — the same exposure as URL rendering, but reachable from
  any HTML string rather than from a guarded URL argument. It is a toggle rather than a hard-coded
  false because a trusted-template pipeline has a legitimate need for it.
- **`isPhpEnabled` is forced off and cannot be set here.** It makes `<script type="text/php">` in the
  input execute, which turns any HTML reaching the driver into arbitrary code execution.

## Security

Governs what `Pdf::url()` may fetch.

```php
'security' => [
    'allowed_schemes' => ['http', 'https'],
    'allowed_hosts' => [],
    'block_private_addresses' => env('LARANAIL_PDF_BLOCK_PRIVATE', true),
],
```

"Render this URL to a PDF" asks a machine inside your network to fetch a URL a caller chose and return
what it found. That is the definition of SSRF, so what it may be given is a security boundary rather
than a preference.

### What is refused by default

| | Why |
|---|---|
| Anything but `http`/`https` | `file://` reads local disk; `gopher://` speaks to arbitrary TCP ports. |
| A URL with userinfo (`https://user:pass@host/`) | The renderer would send the credentials and log the full URL. |
| Loopback, RFC 1918, link-local, CGNAT and reserved addresses | `169.254.169.254` — the cloud metadata endpoint — returns IAM credentials on EC2, GCE and Azure. |

### `allowed_hosts` is the one that actually holds

Empty means any host, subject to the checks above. **Fill it in if any caller can influence the URL.**

```php
'allowed_hosts' => ['reports.internal', '.example.com'],
```

An entry starting with a dot matches subdomains: `.example.com` allows `api.example.com` but not
`example.com` itself. The dot is load-bearing — without it, suffix matching would make
`evil-example.com` a match.

Two behaviours worth knowing:

- **A non-empty allow-list is authoritative**, including over the private-address check. Naming
  `10.0.0.5` explicitly is a deliberate statement about one host; the alternative would be setting
  `block_private_addresses => false` to reach it, turning the check off for every host to permit one.
- **The private-address check is lexical and does not resolve DNS.** A hostname that resolves to
  `127.0.0.1` passes it. Resolving here would not fix that either — the renderer resolves again when it
  connects, and the answer can differ between the two (DNS rebinding). The allow-list is the real
  defence; the address checks are a backstop for the obvious cases.

`laranail::pdf.doctor` reports both settings and asks the guard directly whether the metadata endpoint
is reachable.

## Environment variables

| Variable | Default |
|---|---|
| `LARANAIL_PDF_DRIVER` | `gotenberg` |
| `LARANAIL_PDF_GOTENBERG_URL` | `http://localhost:3000` |
| `LARANAIL_PDF_GOTENBERG_TIMEOUT` | `60` |
| `LARANAIL_PDF_GOTENBERG_VERIFY_SSL` | `true` |
| `LARANAIL_PDF_GOTENBERG_CA_CERT` | — |
| `LARANAIL_PDF_DOMPDF_PAPER` | `a4` |
| `LARANAIL_PDF_DOMPDF_ORIENTATION` | `portrait` |
| `LARANAIL_PDF_DOMPDF_REMOTE` | `false` |
| `LARANAIL_PDF_BLOCK_PRIVATE` | `true` |

---

[← Docs index](../README.md#documentation)
