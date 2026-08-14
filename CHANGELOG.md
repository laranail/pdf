# Changelog

All notable changes to `laranail/pdf` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- **`PdfDocument::saveTo()` reported success on a write that never happened.** A
  failed `fopen()` was swallowed and the path returned anyway, so an unwritable
  directory — the ordinary case — looked identical to a successful save and the
  caller went on to reference a file that did not exist. It now throws
  `RenderFailed`, and a short write (a full disk stopping `fwrite()` early) is
  treated the same way: a truncated PDF claiming to be whole is worse than an
  exception. The handle is closed in a `finally` either way.

## [0.1.0] - 2026-08-13

Initial release.

PDF rendering for Laravel behind a capability-checked driver seam, generalised from a client
application's `GotenbergService` — five constructor arguments, a hard-coded Guzzle client, and a
`string` return that was a path under `storage_path()` into a directory the constructor created with
`mkdir()`.

### Added

- **`Contracts\PdfDriver` plus four capability interfaces** — `RendersHtml`, `RendersUrl`,
  `ConvertsDocuments`, `MergesPdfs`, each extending it. `supports()` is derived from `instanceof` and
  is `final` on the `Driver` base, so a driver cannot claim a capability it has not implemented, or
  implement one without claiming it. The registry's typed accessors (`forHtml()`, `forMerge()`, …) can
  therefore promise their return type honestly.

- **`DriverRegistry`** — string-keyed, with an `extend()` seam for third-party drivers and lazy
  factories so an unused driver never has to be constructible. Deliberately not an
  `Illuminate\Support\Manager`, which resolves `'foo'` by calling `createFooDriver()`: a config value
  must never reach a method name. An unknown driver gives `DriverNotFound` listing what is registered.

- **`ValueObjects\PdfDocument`** — the return contract, and never a path. Nothing renders until
  something reads it, `store()` pipes the PSR-7 body into `Filesystem::put()` so a large merge never
  fully enters memory, and it implements `Responsable` so a controller gets streaming by default
  rather than opting into it. `contents()` materialises everything and is right for a page-sized
  document — the difference is now the caller's to make, and visible at the call site.

- **`GotenbergDriver`** — all four capabilities. Its base URL comes from config only; there is no
  per-call override and no `baseUrl()` setter.

- **`DompdfDriver`** — HTML only. Useful on its own (an invoice does not need a Chromium container),
  and structurally necessary: a capability seam with one driver behind it is an abstraction nobody has
  tested. `isPhpEnabled` is forced off and not overridable, since it makes `<script type="text/php">`
  in the input execute. `isRemoteEnabled` defaults to off.

- **`Support\UrlGuard`** — the SSRF guard on URL rendering. Refuses non-http(s) schemes, URLs carrying
  userinfo, and loopback/RFC 1918/link-local/CGNAT/reserved addresses including the cloud metadata
  endpoint. A non-empty `allowed_hosts` is authoritative, including over the private-address check, so
  one internal host can be named without disabling the check for every host. It does **not** resolve
  DNS, and the docs say so rather than implying otherwise.

- **`laranail::pdf.{doctor,render,install}`** — `doctor` reports which drivers are installed,
  configured and (with `--probe`) actually working, exits non-zero when none is usable, and asks the
  guard directly whether the metadata endpoint is reachable rather than dumping config.

- **`config/pdf.php`** under `laranail.pdf.*`, and the `Pdf` facade.

### Security

- **No vendor exception escapes.** The rendering engines are optional, so their exception classes may
  not be loaded — and a caller cannot write `catch (GotenbergApiErrored $e)` for a class that might not
  exist. Everything is rethrown as `RenderFailed` with the original as `getPrevious()`, and a test
  walks `src/Exceptions/` asserting every class is catchable as `PdfException`.

- **A CI job removes both optional dependencies and runs the suite**, so "optional" is a tested
  property rather than an intention.

[0.1.0]: https://github.com/laranail/pdf/releases/tag/v0.1.0
