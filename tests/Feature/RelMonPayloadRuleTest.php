<?php

namespace FromDevelopersForDevelopers\RelMonLaravel\Tests\Feature;

use FromDevelopersForDevelopers\RelMon\Enum\Format;
use FromDevelopersForDevelopers\RelMonLaravel\Rules\RelMonPayload;
use FromDevelopersForDevelopers\RelMonLaravel\Tests\TestCase;

final class RelMonPayloadRuleTest extends TestCase
{
    public function testItPassesValidationForAValidPayload(): void
    {
        $validator = validator(
            [
                'relmon' => [
                    'protocol' => 'relmon@1.0.0/3',
                    'net' => '100.00',
                    'gross' => '121.00',
                    'tax' => '21.00',
                ],
            ],
            [
                'relmon' => [new RelMonPayload()],
            ]
        );

        $this->assertFalse($validator->fails());
    }

    public function testItUsesExplicitFormatAndDefaults(): void
    {
        $validator = validator(
            [
                'relmon' => json_encode([
                    'protocol' => 'relmon@1.0.0/1',
                    'net' => '100.00',
                ], JSON_THROW_ON_ERROR),
            ],
            [
                'relmon' => [
                    new RelMonPayload(
                        format: Format::JSON_STRING,
                        defaults: ['taxRate' => '21.00'],
                    ),
                ],
            ]
        );

        $this->assertFalse($validator->fails());
    }

    public function testItPrefersSdkValidationMessagesWhenBuildFails(): void
    {
        $validator = validator(
            [
                'relmon' => [
                    'protocol' => 'relmon@1.0.0/3',
                    'net' => '121.00',
                    'gross' => '100.00',
                    'tax' => '21.00',
                ],
            ],
            [
                'relmon' => [new RelMonPayload()],
            ]
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'The relmon field is not a valid RelMon payload. '
            . 'Gross must be greater than or equal to net for positive amounts.',
            $validator->errors()->first('relmon')
        );
    }
}
