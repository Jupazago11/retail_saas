<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\AliasLoader;

/**
 * Livewire::WithFileUploads (`use Facades\Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl;`)
 * triggers Laravel's "real-time facade" autoloader the first time a user
 * picks a file anywhere in the app. That autoloader writes a one-time cache
 * file to storage/framework/cache/facade-<hash>.php via tempnam() — if that
 * directory isn't writable at request time (as it wasn't in production:
 * Railway build vs. runtime filesystem/permission mismatch), tempnam() falls
 * back to the system temp dir and emits a warning that Laravel's error
 * handler escalates into a fatal 500, crashing every file upload in the app.
 *
 * Running this once during the build step (composer post-autoload-dump),
 * when the filesystem is guaranteed writable, bakes that cache file into the
 * image. At runtime AliasLoader::ensureFacadeExists() finds the file already
 * there and returns immediately without ever calling tempnam() again — the
 * crash never has a chance to happen, regardless of runtime permissions.
 */
require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

AliasLoader::getInstance()
    ->load('Facades\Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl');

echo "Real-time facade cache warmed.\n";
