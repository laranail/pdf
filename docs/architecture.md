# Architecture

Why the pieces are shaped the way they are.

## The problem this package generalises

A `GotenbergService` in a client application: five constructor arguments, a hard-coded Guzzle client,
four rendering methods, and a `string` return that was a path under `storage_path()` — into a directory
the constructor created with `mkdir()`.

It worked. But the shape had consequences that only show up later, and each one is a decision here.

## Why capabilities are types

The obvious design gives `PdfDriver` all four rendering methods and lets drivers throw for the ones
they cannot do. Then every caller has to know which driver is configured to know which calls are safe,
and the failure arrives at render time.

The next-obvious design adds `supports(string $capability): bool` returning a hand-written list. That
puts the claim and the implementation in two places, and they drift the first time someone adds a
method.

So a capability is an **interface**: `RendersHtml`, `RendersUrl`, `ConvertsDocuments`, `MergesPdfs`,
each extending `PdfDriver`. `Capability::isImplementedBy()` is an `instanceof`, and `capabilities()`
and `supports()` are `final` on the `Driver` base so a subclass cannot override the answer. A driver
therefore cannot claim what it has not implemented, or implement without claiming.

The registry's typed accessors are the payoff: `forMerge()` returns `MergesPdfs`, and it can promise
that because the `instanceof` just proved it.

### Why two drivers ship

A capability seam with one driver behind it is not a seam — it is an abstraction drawn around whatever
that one driver happens to do, and it fits the second one badly. `DompdfDriver` implements
`RendersHtml` and nothing else, so `supports(Capability::Merge)` has a real negative answer and
`UnsupportedCapability` has a real path to being thrown. It is also genuinely useful: an invoice does
not need a Chromium container, and a package whose only driver requires Docker is one most
applications cannot adopt.

## Why the registry is not a `Manager`

`Illuminate\Support\Manager` resolves a driver by turning the configured string into a method name —
`createFooDriver()` for `'foo'`. **A config value must never reach a method name.**
`laranail/captcha` settled this the same way in its `AdapterFactory`.

Here the map is data, resolution is an array lookup, and an unknown name produces `DriverNotFound`
listing what is registered — rather than a `BadMethodCallException` that leaks the shape of the class.

An enum of drivers was the other candidate and fails the extension requirement: someone will want
wkhtmltopdf or a hosted API. `extend()` is the seam, and factories resolve lazily so an unused driver
never has to be constructible.

## Why rendering returns a lazy document

`PdfDocument` wraps a closure producing a PSR-7 stream. Three problems with returning a path, all of
them real in the code this replaces:

1. **Every render wrote to local disk**, wanted or not. A controller streaming to the browser still
   left a file behind, and nothing cleaned up.
2. **The destination was fixed at construction**, so sending output to S3 meant writing locally and
   copying — the whole document through memory twice.
3. **`mkdir()` in a constructor** means the class cannot be instantiated where that path is not
   writable: a test, a read-only container, a queue worker with a different `HOME`.

So: nothing renders until something reads it, `store()` pipes the stream into `Filesystem::put()`, and
`PdfDocument` implements `Responsable` so a controller gets streaming by default rather than opting in.

`contents()` exists and materialises everything, which is right for a 200 KB invoice and wrong for a
40 MB merge. The difference is now the caller's to make, and visible at the call site.

### Why `store()` consumes the document

Laravel's `put()` calls `detach()` on a PSR-7 stream and Flysystem closes the handle, so the memoized
copy is spent. The memo is therefore cleared and the next read re-renders.

That cost is deliberate and asserted in the suite. The alternative — leaving the spent stream in place
— made a second `store()` silently write zero bytes and report success, which is strictly worse than a
visible second render.

## Why no vendor exception escapes

The rendering engines are optional, so their exception classes may not be loaded — and **you cannot
write `catch (GotenbergApiErrored $e)` for a class that might not exist**. An optional dependency whose
exceptions leak is not optional in any useful sense.

Every driver rethrows as `RenderFailed`, keeping the original as `getPrevious()`. `PdfException` is the
one type a caller needs, and a test walks `src/Exceptions/` asserting every class extends it.

The catch is `Throwable`, not the vendor's base: a connection failure surfaces as a Guzzle exception,
and a caller catching `PdfException` should not have to know the difference.

## Why URL rendering has a guard

`Pdf::url()` is the one capability that turns a renderer into an SSRF vector. `UrlGuard` runs before
anything is built, so a refused URL costs nothing and the exception names the URL rather than a
Gotenberg trace.

Its limits are documented rather than implied — it does not resolve DNS, and the docs say so, because
a guard trusted beyond what it does is worse than no guard. See
[Security](configuration.md#security).

---

[← Docs index](../README.md#documentation)
