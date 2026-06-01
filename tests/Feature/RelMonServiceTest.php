<?php

namespace FromDevelopersForDevelopers\RelMonLaravel\Tests\Feature;

use FromDevelopersForDevelopers\RelMon\ValueObject\RelMonObject;
use FromDevelopersForDevelopers\RelMonLaravel\Exceptions\InvalidRelMonConfigurationException;
use FromDevelopersForDevelopers\RelMonLaravel\Services\RelMonService;
use FromDevelopersForDevelopers\RelMonLaravel\Tests\Support\ContainerAwareRelMonParser;
use FromDevelopersForDevelopers\RelMonLaravel\Tests\Support\NotAParser;
use FromDevelopersForDevelopers\RelMonLaravel\Tests\Support\ParserConstructionCounter;
use FromDevelopersForDevelopers\RelMonLaravel\Tests\TestCase;

final class RelMonServiceTest extends TestCase
{
    public function testItUsesConfigDefaultsWhenBuilding(): void
    {
        config()->set('relmon.defaults', ['taxRate' => '21.00']);

        $relmon = $this->service()->build([
            'protocol' => 'relmon@1.0.0/1',
            'net' => '100.00',
        ]);

        $this->assertInstanceOf(RelMonObject::class, $relmon);
        $this->assertSame(10000, $relmon->getNet());
        $this->assertSame(12100, $relmon->getGross());
        $this->assertSame(2100, $relmon->getTax());
    }

    public function testExplicitDefaultsOverrideConfigDefaultsForACall(): void
    {
        config()->set('relmon.defaults', ['taxRate' => '10.00']);

        $relmon = $this->service()->build(
            [
                'protocol' => 'relmon@1.0.0/1',
                'net' => '100.00',
            ],
            defaults: ['taxRate' => '21.00'],
        );

        $this->assertSame(12100, $relmon->getGross());
        $this->assertSame(2100, $relmon->getTax());
    }

    public function testItResolvesCustomParsersThroughTheContainerPerBuildAndDeduplicatesEntries(): void
    {
        $counter = new ParserConstructionCounter();
        $this->app->instance(ParserConstructionCounter::class, $counter);

        config()->set('relmon.default_format', ContainerAwareRelMonParser::class);
        config()->set('relmon.custom_parsers', [
            ContainerAwareRelMonParser::class,
            ContainerAwareRelMonParser::class,
        ]);

        $first = $this->service()->build('first payload');
        $second = $this->service()->build('second payload');

        $this->assertSame(2, $counter->constructed);
        $this->assertSame(10000, $first->getNet());
        $this->assertSame(12100, $first->getGross());
        $this->assertSame(2100, $first->getTax());
        $this->assertSame(10000, $second->getNet());
    }

    public function testItRejectsCustomParserClassesThatDoNotImplementTheSdkContract(): void
    {
        config()->set('relmon.custom_parsers', [NotAParser::class]);

        $this->expectException(InvalidRelMonConfigurationException::class);
        $this->expectExceptionMessage('must implement');

        $this->service()->build([]);
    }

    private function service(): RelMonService
    {
        return $this->app->make(RelMonService::class);
    }
}
