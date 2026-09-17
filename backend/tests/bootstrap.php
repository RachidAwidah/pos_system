<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * Test bootstrap — runs migrate:fresh --seed before the PHPUnit suite.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$status = $kernel->call('migrate:fresh', [
    '--seed' => true,
    '--force' => true,
]);
