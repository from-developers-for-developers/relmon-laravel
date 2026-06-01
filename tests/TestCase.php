<?php

namespace FromDevelopersForDevelopers\RelMonLaravel\Tests;

use FromDevelopersForDevelopers\RelMonLaravel\RelMonServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            RelMonServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'RelMon' => \FromDevelopersForDevelopers\RelMonLaravel\Facades\RelMon::class,
        ];
    }
}
