# Security policy

## Reporting a vulnerability

Email **opensource@simtabi.com**. Please do not open a public issue.

Include the version, a description of the impact, and the smallest reproduction
you can manage. You will get an acknowledgement within three working days.

## What this package's threat model covers

`laranail/pdf` executes local processes and can expose an unauthenticated
HTTP endpoint, so the following are in scope and treated as vulnerabilities:

- A path that reaches a shell, or any way to execute a script outside the
  configured root.
- Any way to run an interpreter the configuration did not name.
- A secret this package injected appearing in a log, an exception message, or
  a process argument list.
- A URL the guard should have refused reaching a renderer — in particular any
  loopback, RFC 1918, link-local or metadata address, or a scheme other than
  http/https.
- A host outside a non-empty `allowed_hosts` being fetched.
- HTML input reaching Dompdf with `isPhpEnabled` on, or with remote fetching on
  when config did not ask for it.
- A rendering engine's exception escaping as itself rather than as a
  `PdfException`.

## Not vulnerabilities

- Rendering a URL you allow-listed that then does something you did not expect.
  The allow-list is the boundary; what is on it is your decision.
- Setting `verify_ssl => false`. It is documented as insecure and reported as
  such by `laranail::pdf.doctor`.
- Turning on `isRemoteEnabled` for Dompdf and then passing caller-controlled
  HTML. That opt-in moves the boundary deliberately, and the config comments say
  what it costs.
- The URL guard not catching a hostname that resolves to a private address. It
  does not resolve DNS, and says so — the renderer resolves again when it
  connects and can get a different answer, so resolving in the guard would be
  reassurance rather than protection. Use `allowed_hosts`.
