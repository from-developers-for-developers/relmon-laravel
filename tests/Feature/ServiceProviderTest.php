<?php

namespace FromDevelopersForDevelopers\RelMonLaravel\Tests\Feature;

use FromDevelopersForDevelopers\RelMon\Enum\Format;
use FromDevelopersForDevelopers\RelMonLaravel\Contracts\RelMonServiceContract;
use FromDevelopersForDevelopers\RelMonLaravel\RelMonServiceProvider;
use FromDevelopersForDevelopers\RelMonLaravel\Services\RelMonService;
use FromDevelopersForDevelopers\RelMonLaravel\Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function testItRegistersTheRelmonSingletonAndAlias(): void
    {
        $service = $this->app->make(RelMonService::class);

        $this->assertSame($service, $this->app->make('relmon'));
        $this->assertSame($service, $this->app->make(RelMonServiceContract::class));
    }

    public function testItExposesDefaultConfigAndPublishRegistration(): void
    {
        $this->assertSame(Format::AUTO, config('relmon.default_format'));
        $this->assertSame('relmon@1.0.0/3', config('relmon.protocol_identifier'));
        $this->assertSame([
            'unit' => null,
            'scope' => null,
            'roundingMode' => null,
            'roundingApplication' => null,
            'taxRate' => null,
        ], config('relmon.defaults'));
        $this->assertSame([], config('relmon.custom_parsers'));

        $publishPaths = RelMonServiceProvider::pathsToPublish(
            RelMonServiceProvider::class,
            'relmon-config'
        );

        $this->assertCount(1, $publishPaths);
        $this->assertSame(
            realpath(__DIR__ . '/../../config/relmon.php'),
            realpath((string) array_key_first($publishPaths))
        );
        $this->assertSame($this->app->configPath('relmon.php'), array_values($publishPaths)[0]);
    }
}
