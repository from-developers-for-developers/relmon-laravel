<?php

namespace FromDevelopersForDevelopers\RelMonLaravel\Tests\Unit;

use FromDevelopersForDevelopers\RelMonLaravel\Casts\RelMonColumnsCastOptions;
use FromDevelopersForDevelopers\RelMonLaravel\Exceptions\InvalidRelMonCastOptionsException;
use PHPUnit\Framework\TestCase;

final class RelMonColumnsCastOptionsTest extends TestCase
{
    public function testItParsesRequiredAndOptionalNamedOptionsAndDefaultsModeToAuto(): void
    {
        $options = RelMonColumnsCastOptions::fromArguments([
            ' net = net_amount ',
            'gross = gross_amount',
            'tax=tax_amount',
            ' taxRate = tax_rate ',
            'unit=currency',
        ]);

        $this->assertSame('net_amount', $options->net);
        $this->assertSame('gross_amount', $options->gross);
        $this->assertSame('tax_amount', $options->tax);
        $this->assertSame('tax_rate', $options->taxRate);
        $this->assertSame('currency', $options->unit);
        $this->assertSame(RelMonColumnsCastOptions::MODE_AUTO, $options->mode);
    }

    public function testItRejectsUnknownOptions(): void
    {
        $this->expectException(InvalidRelMonCastOptionsException::class);
        $this->expectExceptionMessage('Unknown RelMon column cast option [foo]');

        RelMonColumnsCastOptions::fromArguments([
            'net=net',
            'gross=gross',
            'tax=tax',
            'foo=bar',
        ]);
    }

    public function testItRejectsDuplicateOptions(): void
    {
        $this->expectException(InvalidRelMonCastOptionsException::class);
        $this->expectExceptionMessage('Duplicate RelMon column cast option [net]');

        RelMonColumnsCastOptions::fromArguments([
            'net=net',
            'gross=gross',
            'tax=tax',
            'net=other_net',
        ]);
    }

    public function testItRejectsInvalidModesAndMissingRequiredKeys(): void
    {
        try {
            RelMonColumnsCastOptions::fromArguments([
                'net=net',
                'gross=gross',
                'mode=money',
            ]);
            $this->fail('InvalidRelMonCastOptionsException was expected.');
        } catch (InvalidRelMonCastOptionsException $exception) {
            $this->assertStringContainsString(
                'Missing required RelMon column cast options: tax.',
                $exception->getMessage()
            );
        }

        $this->expectException(InvalidRelMonCastOptionsException::class);
        $this->expectExceptionMessage('Invalid RelMon column cast mode [money]');

        RelMonColumnsCastOptions::fromArguments([
            'net=net',
            'gross=gross',
            'tax=tax',
            'mode=money',
        ]);
    }
}
