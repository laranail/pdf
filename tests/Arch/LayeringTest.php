<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Assert;

/**
 * The dependency rule, enforced rather than documented.
 *
 * `static-analysis.yml` has run an `Architecture` job against `tests/Arch` for as long as the
 * workflow has existed, and the directory was never created — so the job failed on
 * `Test file "tests/Arch" not found` rather than on anything about the code. A check that has
 * never once passed teaches people to ignore the whole workflow.
 *
 * The boundary it should have been guarding: the domain layers take what they need through their
 * constructors, and the framework layers (`Providers`, `Commands`, `Facades`) are where the
 * container is allowed to be touched, because that is what they exist for. The rule currently
 * holds — these tests lock it in rather than fix a violation.
 */
$globals = ['config', 'env', 'request', 'app', 'resolve', 'session', 'cache', 'logger', 'report'];

$facades = [
    Cache::class,
    Config::class,
    File::class,
    Http::class,
    Log::class,
    Storage::class,
];

arch('drivers take their dependencies by injection')
    ->expect('Simtabi\Laranail\Pdf\Drivers')
    ->not->toUse([...$globals, ...$facades]);

arch('support classes take their dependencies by injection')
    ->expect('Simtabi\Laranail\Pdf\Support')
    ->not->toUse([...$globals, ...$facades]);

/**
 * `PdfDocument::store()` calls `Storage::disk()` directly, which this rule would otherwise reject.
 *
 * It is a deliberate exception, not an oversight, and it is carved out by name rather than by
 * dropping `Storage` from the list — so the same facade in any *other* value object still fails.
 * Removing it means giving `PdfDocument` a filesystem collaborator, which changes the public API of
 * a value object consumers already call as `$document->store($disk, $path)`. That is a design call
 * for the package owner, not something to slip into an unrelated change.
 */
arch('value objects stay free of framework services')
    ->expect('Simtabi\Laranail\Pdf\ValueObjects')
    ->not->toUse([...$globals, ...array_diff($facades, [Storage::class])]);

arch('only PdfDocument may reach the filesystem facade')
    ->expect('Simtabi\Laranail\Pdf\ValueObjects')
    ->not->toUse(Storage::class)
    ->ignoring('Simtabi\Laranail\Pdf\ValueObjects\PdfDocument');

/**
 * `env()` outside a config file returns null once `config:cache` has run, so a driver reading a
 * binary path or an API key that way works in development and returns empty in production.
 */
arch('env is never read outside the config file')
    ->expect('Simtabi\Laranail\Pdf')
    ->not->toUse('env');

arch('contracts stay dependency-free')
    ->expect('Simtabi\Laranail\Pdf\Contracts')
    ->toBeInterfaces();

arch('enums are backed so they can round-trip through config and the database')
    ->expect('Simtabi\Laranail\Pdf\Enums')
    ->toBeStringBackedEnums();

arch('nothing is left debugging')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('strict types everywhere')
    ->expect('Simtabi\Laranail\Pdf')
    ->toUseStrictTypes();

/**
 * PHPUnit is a dev dependency. A production class importing `Assert` makes the package unusable
 * without it — an error a consumer only meets at runtime, in the one path they relied on.
 */
arch('phpunit stays out of production code')
    ->expect('Simtabi\Laranail\Pdf')
    ->not->toUse(Assert::class);
