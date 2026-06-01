<?php

namespace FromDevelopersForDevelopers\RelMonLaravel\Tests\Feature;

use FromDevelopersForDevelopers\RelMon\Enum\Format;
use FromDevelopersForDevelopers\RelMon\RelMonFacade;
use FromDevelopersForDevelopers\RelMon\ValueObject\RelMonObject;
use FromDevelopersForDevelopers\RelMonLaravel\Casts\RelMonColumnsCast;
use FromDevelopersForDevelopers\RelMonLaravel\Contracts\RelMonServiceContract;
use FromDevelopersForDevelopers\RelMonLaravel\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

final class RelMonColumnsCastTest extends TestCase
{
    public function testItReturnsNullWhenARequiredColumnIsMissingOrNull(): void
    {
        $cast = new RelMonColumnsCast('net=net', 'gross=gross', 'tax=tax');

        $this->assertNull($cast->get($this->model(), 'amount', null, [
            'net' => 10000,
            'gross' => 12100,
        ]));

        $this->assertNull($cast->get($this->model(), 'amount', null, [
            'net' => 10000,
            'gross' => 12100,
            'tax' => null,
        ]));
    }

    public function testItReadsMinorColumnsDirectlyIntoARelmonObject(): void
    {
        $cast = new RelMonColumnsCast(
            'net=net_amount',
            'gross=gross_amount',
            'tax=tax_amount',
            'taxRate=tax_rate',
            'unit=currency',
            'precision=precision',
            'taxRatePrecision=tax_rate_precision',
            'scope=scope',
            'roundingMode=rounding_mode',
            'roundingApplication=rounding_application',
            'mode=minors',
        );

        $relmon = $cast->get($this->model(), 'amount', null, [
            'net_amount' => '10000',
            'gross_amount' => 12100,
            'tax_amount' => 2100,
            'tax_rate' => '2100',
            'currency' => 'EUR',
            'precision' => '2',
            'tax_rate_precision' => 2,
            'scope' => 'r',
            'rounding_mode' => 'up',
            'rounding_application' => 'total',
        ]);

        $this->assertInstanceOf(RelMonObject::class, $relmon);
        $this->assertSame(10000, $relmon->getNet());
        $this->assertSame(12100, $relmon->getGross());
        $this->assertSame(2100, $relmon->getTax());
        $this->assertSame(2100, $relmon->getTaxRate());
        $this->assertSame('EUR', $relmon->getUnit());
        $this->assertSame(2, $relmon->getPrecision());
        $this->assertSame(2, $relmon->getTaxRatePrecision());
        $this->assertSame('100.00', $relmon->getNetFormatted());
    }

    public function testItReadsDecimalColumnsThroughTheLaravelRelmonService(): void
    {
        config()->set('relmon.protocol_identifier', 'relmon@1.0.0/3');

        $calls = new \stdClass();
        $calls->input = null;
        $calls->format = null;
        $calls->defaults = null;

        app()->instance(RelMonServiceContract::class, new class ($calls) implements RelMonServiceContract {
            public function __construct(private \stdClass $calls)
            {
            }

            public function build(mixed $input, ?string $format = null, ?array $defaults = null): RelMonObject
            {
                $this->calls->input = $input;
                $this->calls->format = $format;
                $this->calls->defaults = $defaults;

                return RelMonFacade::build($input, $format, defaults: $defaults ?? []);
            }
        });

        $cast = new RelMonColumnsCast(
            'net=net_amount',
            'gross=gross_amount',
            'tax=tax_amount',
            'taxRate=tax_rate',
            'precision=precision',
            'unit=currency',
            'mode=decimal',
        );

        $relmon = $cast->get($this->model(), 'amount', null, [
            'net_amount' => '100.00',
            'gross_amount' => '121.00',
            'tax_amount' => '21.00',
            'tax_rate' => '21.00',
            'precision' => 2,
            'currency' => 'EUR',
        ]);

        $this->assertSame(10000, $relmon?->getNet());
        $this->assertSame(12100, $relmon?->getGross());
        $this->assertSame(2100, $relmon?->getTax());
        $this->assertSame(2100, $relmon?->getTaxRate());
        $this->assertSame('EUR', $relmon?->getUnit());
        $this->assertSame(2, $relmon?->getPrecision());
        $this->assertSame(2, $relmon?->getTaxRatePrecision());
        $this->assertSame(Format::JSON_ARRAY, $calls->format);
        $this->assertSame([
            'protocol' => 'relmon@1.0.0/3',
            'net' => '100.00',
            'gross' => '121.00',
            'tax' => '21.00',
            'taxRate' => '21.00',
            'unit' => 'EUR',
            'precision' => 2,
        ], $calls->input);
    }

    public function testItRejectsMixedAutoReadColumnTypes(): void
    {
        $cast = new RelMonColumnsCast('net=net', 'gross=gross', 'tax=tax', 'mode=auto');

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('cannot auto-detect mixed monetary column types');

        $cast->get($this->model(), 'amount', null, [
            'net' => 10000,
            'gross' => '121.00',
            'tax' => 2100,
        ]);
    }

    public function testItRejectsIntegerLikeStringsInAutoMode(): void
    {
        $cast = new RelMonColumnsCast('net=net', 'gross=gross', 'tax=tax', 'mode=auto');

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('cannot auto-detect integer-like string value [10000] in column [net]');

        $cast->get($this->model(), 'amount', null, [
            'net' => '10000',
            'gross' => '12100',
            'tax' => '2100',
        ]);
    }

    public function testItWritesDecimalValuesFromSdkPayloadsAndClearsNulls(): void
    {
        $cast = new RelMonColumnsCast(
            'net=net_amount',
            'gross=gross_amount',
            'tax=tax_amount',
            'taxRate=tax_rate',
            'unit=currency',
            'precision=precision',
            'taxRatePrecision=tax_rate_precision',
            'mode=decimal',
        );

        $written = $cast->set($this->model(), 'amount', [
            'protocol' => 'relmon@1.0.0/3',
            'net' => '100.00',
            'gross' => '121.00',
            'tax' => '21.00',
            'taxRate' => '21.00',
            'unit' => 'EUR',
            'precision' => 2,
        ], []);

        $this->assertSame([
            'net_amount' => '100.00',
            'gross_amount' => '121.00',
            'tax_amount' => '21.00',
            'tax_rate' => '21.00',
            'currency' => 'EUR',
            'precision' => 2,
            'tax_rate_precision' => 2,
        ], $written);

        $this->assertSame([
            'net_amount' => null,
            'gross_amount' => null,
            'tax_amount' => null,
            'tax_rate' => null,
            'currency' => null,
            'precision' => null,
            'tax_rate_precision' => null,
        ], $cast->set($this->model(), 'amount', null, []));
    }

    public function testAutoWriteDefaultsToMinorValues(): void
    {
        $cast = new RelMonColumnsCast(
            'net=net_amount',
            'gross=gross_amount',
            'tax=tax_amount',
            'taxRate=tax_rate',
            'precision=precision',
            'mode=auto',
        );

        $relmon = app('relmon')->build([
            'protocol' => 'relmon@1.0.0/3',
            'net' => '100.00',
            'gross' => '121.00',
            'tax' => '21.00',
            'taxRate' => '21.00',
            'precision' => 2,
        ]);

        $this->assertSame([
            'net_amount' => 10000,
            'gross_amount' => 12100,
            'tax_amount' => 2100,
            'tax_rate' => 2100,
            'precision' => 2,
        ], $cast->set($this->model(), 'amount', $relmon, []));
    }

    private function model(): Model
    {
        return new class extends Model {
            protected $guarded = [];

            public $timestamps = false;
        };
    }
}
