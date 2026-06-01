<?php

namespace FromDevelopersForDevelopers\RelMonLaravel\Tests\Feature;

use FromDevelopersForDevelopers\RelMon\ValueObject\RelMonObject;
use FromDevelopersForDevelopers\RelMonLaravel\Facades\RelMon;
use FromDevelopersForDevelopers\RelMonLaravel\Tests\TestCase;

final class RelMonFacadeTest extends TestCase
{
    public function testItBuildsARelmonObjectViaTheLaravelFacade(): void
    {
        $relmon = RelMon::build([
            'protocol' => 'relmon@1.0.0/3',
            'net' => '100.00',
            'gross' => '121.00',
            'tax' => '21.00',
        ]);

        $this->assertInstanceOf(RelMonObject::class, $relmon);
        $this->assertSame(10000, $relmon->getNet());
        $this->assertSame(12100, $relmon->getGross());
        $this->assertSame(2100, $relmon->getTax());
    }
}
