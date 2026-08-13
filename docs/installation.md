# Installation

What to install, in what order, and how to tell whether it worked.

## Requirements

| | |
|---|---|
| PHP | `^8.4.1 \|\| ^8.5` |
| Laravel | `^13.0` |

The `^8.4.1` floor comes from `laranail/console`, which supplies the command base that makes the
`laranail::pdf.*` command names possible.

## The package

```bash
composer require laranail/pdf
```

`laranail/*` packages resolve through git rather than Packagist, so add the VCS repositories to your
root `composer.json` if they are not already there:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/laranail/pdf" },
    { "type": "vcs", "url": "https://github.com/laranail/console" },
    { "type": "vcs", "url": "https://github.com/laranail/package-tools" }
]
```

Composer ignores a dependency's own `repositories`, so the full transitive `laranail/*` closure has to
be listed — not just `pdf`.

## A rendering engine

**The package renders nothing on its own.** Both engines are optional dependencies, declared in
`suggest` rather than `require`, so you install the one you actually use.

```bash
# Everything: HTML, URL, Office documents, merging.
composer require gotenberg/gotenberg-php guzzlehttp/guzzle

# HTML only, in-process, no external service.
composer require dompdf/dompdf
```

Both drivers are registered whether or not their package is installed. That is deliberate: a missing
package then reports `dompdf/dompdf is not installed`, which is the real problem, rather than
`no driver named [dompdf]`, which reads like a typo.

### Gotenberg also needs a running instance

```bash
docker run --rm -p 3000:3000 gotenberg/gotenberg:8
```

```env
LARANAIL_PDF_GOTENBERG_URL=http://localhost:3000
```

## Publish and check

```bash
php artisan laranail::pdf.install
```

Publishes `config/pdf.php` and then says what is still missing. Follow it with:

```bash
php artisan laranail::pdf.doctor --probe
```

`--probe` actually renders a one-line document with each available driver, which is the difference
between "the config looks right" and "this works".

```
+----------------------+-----------+-------------------------+---------------------+
| Driver               | Available | Capabilities            | Probe               |
+----------------------+-----------+-------------------------+---------------------+
| gotenberg (default)  | yes       | html, url, office, merge| ok (4821 bytes, 91ms)|
| dompdf               | yes       | html                    | ok (1204 bytes, 12ms)|
+----------------------+-----------+-------------------------+---------------------+
```

## If you expose URL rendering

`Pdf::url()` makes your server fetch a URL. If any of that URL comes from user input, set an
allow-list before shipping — see [Security](configuration.md#security).

---

[← Docs index](../README.md#documentation)
