<?php

namespace Platform\Core\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Platform\Core\CoreServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            CoreServiceProvider::class,
        ];
    }
}

