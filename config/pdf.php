<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default driver
    |--------------------------------------------------------------------------
    |
    | Which driver renders when a call does not name one. Both bundled drivers
    | are always registered; whether they can actually run depends on their
    | optional package being installed — `laranail::pdf.doctor` says which.
    |
    */

    'default' => env('LARANAIL_PDF_DRIVER', 'gotenberg'),

    'default_filename' => 'document.pdf',

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    */

    'drivers' => [

        'gotenberg' => [
            // The Gotenberg instance to render through. Config only — there is
            // no per-call override and no baseUrl() setter, because a renderer
            // whose target host is caller-supplied is an SSRF primitive.
            'base_url' => env('LARANAIL_PDF_GOTENBERG_URL', 'http://localhost:3000'),

            'timeout' => env('LARANAIL_PDF_GOTENBERG_TIMEOUT', 60),

            'verify_ssl' => env('LARANAIL_PDF_GOTENBERG_VERIFY_SSL', true),

            // A CA bundle, e.g. mkcert's. Set-but-missing falls back to the
            // system store rather than to no verification at all.
            'ca_cert' => env('LARANAIL_PDF_GOTENBERG_CA_CERT'),
        ],

        'dompdf' => [
            'paper'       => env('LARANAIL_PDF_DOMPDF_PAPER', 'a4'),
            'orientation' => env('LARANAIL_PDF_DOMPDF_ORIENTATION', 'portrait'),

            /*
            | Passed to Dompdf\Options. Only the keys listed in
            | DompdfDriver::SETTABLE are acted on — a config value must not be
            | free to name a method.
            |
            | `isRemoteEnabled` defaults to false and is the one worth
            | understanding: turning it on makes every <img src> and @import in
            | the input HTML a request this server performs, which is the same
            | exposure as URL rendering but reachable from any HTML string.
            |
            | `isPhpEnabled` is forced off and cannot be overridden here: it
            | makes <script type="text/php"> in the input execute.
            */
            'options' => [
                'isRemoteEnabled' => env('LARANAIL_PDF_DOMPDF_REMOTE', false),
                'defaultFont'     => 'DejaVu Sans',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Governs what the URL capability may fetch. "Render this URL" asks a
    | machine inside your network to fetch a URL a caller chose, so what it may
    | be given is a security boundary rather than a preference.
    |
    */

    'security' => [

        'allowed_schemes' => ['http', 'https'],

        /*
        | Empty means any host, subject to the private-address check below.
        |
        | This is the only setting that holds against a determined caller. The
        | private-address check is lexical, so a hostname that resolves to
        | 127.0.0.1 passes it — and resolving here would not help, because the
        | renderer resolves again when it connects and can get a different
        | answer (DNS rebinding). If the URL capability is reachable from user
        | input, fill this in.
        |
        | An entry starting with a dot matches subdomains: `.example.com`
        | allows `api.example.com` but not `example.com` itself.
        */
        'allowed_hosts' => [],

        /*
        | Refuse loopback, RFC 1918, link-local, CGNAT and reserved addresses.
        | 169.254.169.254 — the cloud metadata endpoint — is the one that
        | matters most.
        */
        'block_private_addresses' => env('LARANAIL_PDF_BLOCK_PRIVATE', true),

    ],

];
