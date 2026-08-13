# Release process

`laranail/pdf` is released **tag-driven**: pushing a `vX.Y.Z` tag runs the release workflow, which
publishes the GitHub Release with the CHANGELOG section as its body.

Note that `laranail/*` packages resolve through **git VCS repositories rather than Packagist** — the
tag is the distribution mechanism, and consumers on `^0.1` pick it up on their next
`composer update`.

## Versioning & stability

[Semantic Versioning](https://semver.org). While pre-1.0 the package keeps a single moving `v0.1.0`
tag per the laranail convention.

**What SemVer covers (the public API):**

- The `Pdf` facade and `PdfManager`'s method surface.
- `Contracts\PdfDriver` and the four capability interfaces under `Contracts\Capabilities\*` — these
  are what a third-party driver implements, so a change here breaks every external driver.
- `ValueObjects\{PdfDocument, RenderOptions}`, `Enums\Capability`, and `DriverRegistry`'s public methods.
- The `Exceptions\*` hierarchy and its codes (6001–6011). A caller catching `PdfException` and reading
  `context()` is using a supported surface.
- `config/pdf.php` key shapes and the `LARANAIL_PDF_*` env var names.
- The `laranail::pdf.{doctor,render,install}` command signatures.

**What is NOT covered:**

- The bundled drivers' constructor signatures — they are resolved through the registry.
- `Support\{PdfConfig, DriverReport}` and `Drivers\PaperSize`'s table contents.
- Whatever an optional rendering engine does with a given input. `RenderOptions::$extra` passes
  through untouched by design.

### One thing to weigh on every release

**A new capability is a breaking change for third-party drivers only if it is added to an existing
interface.** Adding a fifth `Capability` case with its own interface is additive — existing drivers
simply do not implement it and correctly report they cannot. Adding a method to `RendersHtml` is not.
Prefer the former.

## Cutting a release

1. Land everything on `main` with `composer lint` (pint + phpstan + rector) and `composer test` green.
   CI runs the 8.4/8.5 matrix, static analysis, the security audit, a live Gotenberg container job, and
   the optional-dependencies-removed job.
2. Add the `## [X.Y.Z]` block to `CHANGELOG.md` (Keep a Changelog), plus an `UPGRADING.md` section for
   anything breaking.
3. Commit, push, wait for CI green.
4. Tag; the release body is the CHANGELOG block, never a bare stub:

   ```bash
   git tag vX.Y.Z && git push origin vX.Y.Z
   gh release create vX.Y.Z --title "vX.Y.Z" \
     --notes-file <(awk '/^## \[X.Y.Z\]/{f=1;next} /^## \[/{f=0} f' CHANGELOG.md) --generate-notes
   ```

   Pre-1.0, move the existing tag instead:

   ```bash
   git tag -f v0.1.0 && git push origin v0.1.0 --force
   ```

## The optional-dependency check

Before tagging, confirm the optional dependencies are still optional:

```bash
composer remove --dev gotenberg/gotenberg-php dompdf/dompdf
vendor/bin/pest
```

Everything except the render paths must still pass, and both drivers must report themselves
unavailable with a message naming the missing package — not fatal on a missing class. CI runs this as
its own job so the property cannot rot; run it locally if you touched a driver.

---

[← Docs index](../README.md#documentation)
