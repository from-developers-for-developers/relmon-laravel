<?php

namespace FromDevelopersForDevelopers\RelMonLaravel\Casts;

use FromDevelopersForDevelopers\RelMon\Enum\Format;
use FromDevelopersForDevelopers\RelMon\Enum\RoundingApplication;
use FromDevelopersForDevelopers\RelMon\Enum\RoundingMode;
use FromDevelopersForDevelopers\RelMon\Enum\Scope;
use FromDevelopersForDevelopers\RelMon\ValueObject\RelMonObject;
use FromDevelopersForDevelopers\RelMonLaravel\Contracts\RelMonServiceContract;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

final class RelMonColumnsCast implements CastsAttributes
{
    private const DEFAULT_PROTOCOL_IDENTIFIER = 'relmon@1.0.0/3';

    private const AMBIGUOUS_AUTO_MODE_MESSAGE =
        'The [%s] RelMon cast cannot auto-detect integer-like string value '
        . '[%s] in column [%s]. Use mode=minors or mode=decimal.';

    private const MONETARY_COLUMN_TYPE_MESSAGE =
        'The [%s] RelMon cast expected monetary column [%s] to contain '
        . 'an integer minor value or a decimal string.';

    private RelMonColumnsCastOptions $options;

    public function __construct(string ...$arguments)
    {
        $this->options = RelMonColumnsCastOptions::fromArguments($arguments);
    }

    public function get($model, string $key, $value, array $attributes): ?RelMonObject
    {
        if ($this->requiredValueIsMissing($attributes)) {
            return null;
        }

        return match ($this->options->mode) {
            RelMonColumnsCastOptions::MODE_MINORS => $this->buildFromMinorColumns($key, $attributes),
            RelMonColumnsCastOptions::MODE_DECIMAL => $this->buildFromDecimalColumns($key, $attributes),
            default => $this->buildFromAutoDetectedColumns($key, $attributes),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function set($model, string $key, $value, array $attributes): array
    {
        if ($value === null) {
            return array_fill_keys($this->options->configuredColumns(), null);
        }

        $relmon = $value instanceof RelMonObject ? $value : $this->relmon()->build($value);
        $writeMode = $this->options->mode === RelMonColumnsCastOptions::MODE_DECIMAL
            ? RelMonColumnsCastOptions::MODE_DECIMAL
            : RelMonColumnsCastOptions::MODE_MINORS;

        return $this->writeColumns($relmon, $writeMode);
    }

    private function requiredValueIsMissing(array $attributes): bool
    {
        foreach ($this->options->requiredColumns() as $column) {
            if (!array_key_exists($column, $attributes) || $attributes[$column] === null) {
                return true;
            }
        }

        return false;
    }

    private function buildFromAutoDetectedColumns(string $key, array $attributes): RelMonObject
    {
        $detectedModes = [];

        foreach ($this->options->requiredColumns() as $column) {
            $detectedModes[] = $this->detectMonetaryColumnMode($attributes[$column], $column, $key);
        }

        $detectedModes = array_values(array_unique($detectedModes));

        if (count($detectedModes) !== 1) {
            throw new \UnexpectedValueException(
                sprintf(
                    'The [%s] RelMon cast cannot auto-detect mixed monetary column types. '
                    . 'Use mode=minors or mode=decimal.',
                    $key
                )
            );
        }

        return $detectedModes[0] === RelMonColumnsCastOptions::MODE_MINORS
            ? $this->buildFromMinorColumns($key, $attributes)
            : $this->buildFromDecimalColumns($key, $attributes);
    }

    private function detectMonetaryColumnMode(mixed $value, string $column, string $key): string
    {
        if (is_int($value)) {
            return RelMonColumnsCastOptions::MODE_MINORS;
        }

        if (is_float($value)) {
            return RelMonColumnsCastOptions::MODE_DECIMAL;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                throw new \UnexpectedValueException(sprintf(self::MONETARY_COLUMN_TYPE_MESSAGE, $key, $column));
            }

            if ($this->isIntegerLikeString($value)) {
                throw new \UnexpectedValueException(sprintf(self::AMBIGUOUS_AUTO_MODE_MESSAGE, $key, $value, $column));
            }

            return RelMonColumnsCastOptions::MODE_DECIMAL;
        }

        throw new \UnexpectedValueException(sprintf(self::MONETARY_COLUMN_TYPE_MESSAGE, $key, $column));
    }

    private function buildFromMinorColumns(string $key, array $attributes): RelMonObject
    {
        return new RelMonObject(
            net: $this->normalizeIntegerValue($attributes[$this->options->net], $this->options->net, $key),
            gross: $this->normalizeIntegerValue($attributes[$this->options->gross], $this->options->gross, $key),
            tax: $this->normalizeIntegerValue($attributes[$this->options->tax], $this->options->tax, $key),
            taxRate: $this->readOptionalInteger($attributes, $this->options->taxRate, $key),
            unit: $this->readOptionalString($attributes, $this->options->unit, $key),
            precision: $this->readOptionalInteger($attributes, $this->options->precision, $key),
            taxRatePrecision: $this->readOptionalInteger($attributes, $this->options->taxRatePrecision, $key),
            scope: $this->readOptionalString($attributes, $this->options->scope, $key) ?? Scope::ROOT,
            roundingMode: $this->readOptionalString($attributes, $this->options->roundingMode, $key)
            ?? RoundingMode::HALF_EVEN,
            roundingApplication: $this->readOptionalString($attributes, $this->options->roundingApplication, $key)
            ?? RoundingApplication::TAX,
        );
    }

    private function buildFromDecimalColumns(string $key, array $attributes): RelMonObject
    {
        return $this->relmon()->build($this->decimalPayload($key, $attributes), Format::JSON_ARRAY);
    }

    private function decimalPayload(string $key, array $attributes): array
    {
        $payload = [
            'protocol' => $this->protocolIdentifier(),
            'net' => $this->normalizeDecimalValue($attributes[$this->options->net], $this->options->net, $key),
            'gross' => $this->normalizeDecimalValue($attributes[$this->options->gross], $this->options->gross, $key),
            'tax' => $this->normalizeDecimalValue($attributes[$this->options->tax], $this->options->tax, $key),
        ];

        if (
            $this->options->taxRate !== null
            && $this->optionalValueIsPresent($attributes, $this->options->taxRate)
        ) {
            $payload['taxRate'] = $this->normalizeDecimalValue(
                $attributes[$this->options->taxRate],
                $this->options->taxRate,
                $key
            );
        }

        if ($this->options->unit !== null && $this->optionalValueIsPresent($attributes, $this->options->unit)) {
            $payload['unit'] = $this->normalizeStringValue(
                $attributes[$this->options->unit],
                $this->options->unit,
                $key
            );
        }

        if (
            $this->options->precision !== null
            && $this->optionalValueIsPresent($attributes, $this->options->precision)
        ) {
            $payload['precision'] = $this->normalizeIntegerValue(
                $attributes[$this->options->precision],
                $this->options->precision,
                $key
            );
        }

        if (
            $this->options->taxRatePrecision !== null
            && $this->optionalValueIsPresent($attributes, $this->options->taxRatePrecision)
        ) {
            $payload['taxRatePrecision'] = $this->normalizeIntegerValue(
                $attributes[$this->options->taxRatePrecision],
                $this->options->taxRatePrecision,
                $key
            );
        }

        if ($this->options->scope !== null && $this->optionalValueIsPresent($attributes, $this->options->scope)) {
            $payload['scope'] = $this->normalizeStringValue(
                $attributes[$this->options->scope],
                $this->options->scope,
                $key
            );
        }

        if (
            $this->options->roundingMode !== null
            && $this->optionalValueIsPresent($attributes, $this->options->roundingMode)
        ) {
            $payload['roundingMode'] = $this->normalizeStringValue(
                $attributes[$this->options->roundingMode],
                $this->options->roundingMode,
                $key
            );
        }

        if (
            $this->options->roundingApplication !== null
            && $this->optionalValueIsPresent($attributes, $this->options->roundingApplication)
        ) {
            $payload['roundingApplication'] = $this->normalizeStringValue(
                $attributes[$this->options->roundingApplication],
                $this->options->roundingApplication,
                $key
            );
        }

        return $payload;
    }

    private function protocolIdentifier(): string
    {
        $protocolIdentifier = config('relmon.protocol_identifier', self::DEFAULT_PROTOCOL_IDENTIFIER);

        if (!is_string($protocolIdentifier) || trim($protocolIdentifier) === '') {
            throw new \UnexpectedValueException(
                'The [relmon.protocol_identifier] configuration value must be a non-empty string.'
            );
        }

        return trim($protocolIdentifier);
    }

    private function writeColumns(RelMonObject $relmon, string $mode): array
    {
        $values = [
            $this->options->net => $mode === RelMonColumnsCastOptions::MODE_DECIMAL
                ? $relmon->getNetFormatted()
                : $relmon->getNet(),

            $this->options->gross => $mode === RelMonColumnsCastOptions::MODE_DECIMAL
                ? $relmon->getGrossFormatted()
                : $relmon->getGross(),

            $this->options->tax => $mode === RelMonColumnsCastOptions::MODE_DECIMAL
                ? $relmon->getTaxFormatted()
                : $relmon->getTax(),
        ];

        if ($this->options->taxRate !== null) {
            $values[$this->options->taxRate] = $mode === RelMonColumnsCastOptions::MODE_DECIMAL
                ? $relmon->getTaxRateFormatted()
                : $relmon->getTaxRate();
        }

        if ($this->options->unit !== null) {
            $values[$this->options->unit] = $relmon->getUnit();
        }

        if ($this->options->precision !== null) {
            $values[$this->options->precision] = $relmon->getPrecision();
        }

        if ($this->options->taxRatePrecision !== null) {
            $values[$this->options->taxRatePrecision] = $relmon->getTaxRatePrecision();
        }

        if ($this->options->scope !== null) {
            $values[$this->options->scope] = $relmon->getScope();
        }

        if ($this->options->roundingMode !== null) {
            $values[$this->options->roundingMode] = $relmon->getRoundingMode();
        }

        if ($this->options->roundingApplication !== null) {
            $values[$this->options->roundingApplication] = $relmon->getRoundingApplication();
        }

        return $values;
    }

    private function optionalValueIsPresent(array $attributes, string $column): bool
    {
        return array_key_exists($column, $attributes) && $attributes[$column] !== null;
    }

    private function readOptionalInteger(array $attributes, ?string $column, string $key): ?int
    {
        if ($column === null || !$this->optionalValueIsPresent($attributes, $column)) {
            return null;
        }

        return $this->normalizeIntegerValue($attributes[$column], $column, $key);
    }

    private function readOptionalString(array $attributes, ?string $column, string $key): ?string
    {
        if ($column === null || !$this->optionalValueIsPresent($attributes, $column)) {
            return null;
        }

        return $this->normalizeStringValue($attributes[$column], $column, $key);
    }

    private function normalizeIntegerValue(mixed $value, string $column, string $key): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $this->isIntegerLikeString(trim($value))) {
            return (int)trim($value);
        }

        throw new \UnexpectedValueException(
            sprintf('The [%s] RelMon cast expected integer minor data in column [%s].', $key, $column)
        );
    }

    private function normalizeDecimalValue(mixed $value, string $column, string $key): string
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value !== '') {
                return $value;
            }
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        throw new \UnexpectedValueException(
            sprintf('The [%s] RelMon cast expected decimal-compatible data in column [%s].', $key, $column)
        );
    }

    private function normalizeStringValue(mixed $value, string $column, string $key): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        throw new \UnexpectedValueException(
            sprintf('The [%s] RelMon cast expected string-compatible data in column [%s].', $key, $column)
        );
    }

    private function relmon(): RelMonServiceContract
    {
        return app(RelMonServiceContract::class);
    }

    private function isIntegerLikeString(string $value): bool
    {
        return preg_match('/^-?\d+$/', $value) === 1;
    }
}
