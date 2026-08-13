# Commands

Three: check it, use it, set it up.

## `laranail::pdf.doctor`

```bash
php artisan laranail::pdf.doctor [--driver=] [--probe] [--json]
```

| Flag | Meaning |
|---|---|
| `--driver=NAME` | Check only this driver. |
| `--probe` | Actually render a one-line document with each available driver. |
| `--json` | Machine-readable output. |

Answers what is wrong, not what the config file looks like. PDF rendering fails in ways that leave an
application looking fine: an optional package never installed, a Gotenberg container that moved, an
SSRF allow-list nobody filled in. Each reads as healthy from `config:show`.

```
+----------------------+-----------+--------------------------+-----------------------+---------+
| Driver               | Available | Capabilities             | Probe                 | Why not |
+----------------------+-----------+--------------------------+-----------------------+---------+
| gotenberg (default)  | yes       | html, url, office, merge | ok (4821 bytes, 91ms) |         |
| dompdf               | yes       | html                     | ok (1204 bytes, 12ms) |         |
+----------------------+-----------+--------------------------+-----------------------+---------+

URL rendering
  allowed hosts:   any
  private blocked: yes
  Note: the private-address check is lexical, so a hostname resolving to an
  internal address still passes it. Fill in allowed_hosts if callers can influence the URL.
```

`--probe` is opt-in because it is the only check that leaves the process — against Gotenberg that means
a real request to a real container.

The security block is not a config dump: `metadata_endpoint_refused` is an actual question put to the
guard, so a rule that stops covering `169.254.169.254` stops claiming to.

**Exits non-zero when no driver is usable**, which makes it a CI gate.

## `laranail::pdf.render`

```bash
php artisan laranail::pdf.render <source> <output> [--driver=] [--paper=] [--orientation=] [--merge=*]
```

For proving a driver works end to end without writing a route.

```bash
php artisan laranail::pdf.render https://example.com/report report.pdf
php artisan laranail::pdf.render invoice.html invoice.pdf --driver=dompdf --paper=letter
php artisan laranail::pdf.render proposal.docx proposal.pdf
php artisan laranail::pdf.render cover.pdf book.pdf --merge=body.pdf --merge=index.pdf
echo '<h1>Hi</h1>' | php artisan laranail::pdf.render - out.pdf --driver=dompdf
```

The source is interpreted by shape: `-` reads HTML from stdin, an `http(s)` URL renders as a URL, an
`.html`/`.htm` file renders as HTML, anything else converts as an Office document. `--merge` overrides
all of that.

## `laranail::pdf.install`

```bash
php artisan laranail::pdf.install [--force]
```

Publishes `config/pdf.php`, then says what is still needed:

```
Published config/pdf.php.

  ✓ dompdf
  ✗ gotenberg — gotenberg/gotenberg-php is not installed. Run `composer require gotenberg/gotenberg-php`.

Next steps
  composer require gotenberg/gotenberg-php guzzlehttp/guzzle
  docker run --rm -p 3000:3000 gotenberg/gotenberg:8

Then: php artisan laranail::pdf.doctor
```

The second half is the useful half — this package renders nothing until an optional package is
installed, and for Gotenberg until a container is running. Publishing a config and stopping would leave
a working-looking install that renders nothing.

**Exits zero even when nothing is ready.** A fresh install with nothing else installed yet is the
expected state, and failing would break a scripted setup that runs `install` before `composer require`.

---

[← Docs index](../../README.md#documentation)
