# Contributing

Thanks for helping. This package runs local processes and can open an
unauthenticated HTTP endpoint, so a few things are stricter here than usual.

## Getting set up

```bash
composer install
composer test
composer lint
```

`composer lint` runs Pint, PHPStan (level max) and Rector. All three must be
clean before a PR.

## The rules that are not negotiable

- **A config value must never become a method name.** No `'set'.ucfirst($key)`,
  no `Illuminate\Support\Manager`. Driver resolution is an array lookup and
  Dompdf's options come from an explicit allow-list.
- **A capability is a type.** If a driver can do something, it implements the
  interface; `capabilities()` and `supports()` derive from `instanceof` and are
  `final`. Do not add a rendering method to `PdfDriver`.
- **No vendor exception escapes.** Wrap whatever an engine throws in
  `RenderFailed::from()`. A caller cannot write a `catch` for a class that may
  not be loaded, and a test asserts every exception extends `PdfException`.
- **`env()` is called in `config/pdf.php` and nowhere else.** Anywhere else it
  returns null the moment the host runs `config:cache`.
- **Rendering returns a lazy `PdfDocument`, never a path and never bytes.** The
  caller decides when to materialise.

## Tests

Tests needing a real Gotenberg container are in the `gotenberg` group, excluded
by default and run as their own CI job:

```bash
docker run --rm -p 3000:3000 gotenberg/gotenberg:8
vendor/bin/pest --group=gotenberg
```

Before touching a driver, confirm the optional dependencies are still optional —
CI runs this, and it catches more than you would expect:

```bash
composer remove --dev gotenberg/gotenberg-php dompdf/dompdf
vendor/bin/pest
```

## Commits

Subject in the imperative, under 72 characters. The body explains why, not what.
No AI attribution.
