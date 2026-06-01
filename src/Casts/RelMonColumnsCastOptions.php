<?php

namespace FromDevelopersForDevelopers\RelMonLaravel\Casts;

use FromDevelopersForDevelopers\RelMonLaravel\Exceptions\InvalidRelMonCastOptionsException;

final class RelMonColumnsCastOptions
{
    public const MODE_AUTO = 'auto';
    public const MODE_DECIMAL = 'decimal';
    public const MODE_MINORS = 'minors';

    private const REQUIRED_KEYS = ['net', 'gross', 'tax'];
    private const OPTIONAL_KEYS = [
        'taxRate',
        'unit',
        'precision',
        'taxRatePrecision',
        'scope',
        'roundingMode',
        'roundingApplication',
        'mode',
    ];

    private const VALID_MODES = [self::MODE_AUTO, self::MODE_DECIMAL, self::MODE_MINORS];

    public function __construct(
        public string $net,
        public string $gross,
        public string $tax,
        public ?string $taxRate = null,
        public ?string $unit = null,
        public ?string $precision = null,
        public ?string $taxRatePrecision = null,
        public ?string $scope = null,
        public ?string $roundingMode = null,
        public ?string $roundingApplication = null,
        public string $mode = self::MODE_AUTO,
    ) {
    }

    public static function fromArguments(array $arguments): self
    {
        $parsed = [];

        foreach ($arguments as $argument) {
            $token = trim($argument);

            if ($token === '') {
                throw new InvalidRelMonCastOptionsException(
                    'RelMon column cast options must not contain empty tokens.'
                );
            }

            if (substr_count($token, '=') !== 1) {
                throw new InvalidRelMonCastOptionsException(
                    sprintf('Invalid RelMon column cast option [%s]. Expected key=value.', $token)
                );
            }

            [$key, $value] = array_map('trim', explode('=', $token, 2));

            if ($key === '' || $value === '') {
                throw new InvalidRelMonCastOptionsException(
                    sprintf('Invalid RelMon column cast option [%s]. Keys and values must be non-empty.', $token)
                );
            }

            if (isset($parsed[$key])) {
                throw new InvalidRelMonCastOptionsException(
                    sprintf('Duplicate RelMon column cast option [%s] is not allowed.', $key)
                );
            }

            if (!in_array($key, array_merge(self::REQUIRED_KEYS, self::OPTIONAL_KEYS), true)) {
                throw new InvalidRelMonCastOptionsException(
                    sprintf('Unknown RelMon column cast option [%s].', $key)
                );
            }

            $parsed[$key] = $value;
        }

        $missingKeys = array_values(array_diff(self::REQUIRED_KEYS, array_keys($parsed)));

        if ($missingKeys !== []) {
            throw new InvalidRelMonCastOptionsException(
                sprintf(
                    'Missing required RelMon column cast options: %s.',
                    implode(', ', $missingKeys)
                )
            );
        }

        $mode = $parsed['mode'] ?? self::MODE_AUTO;

        if (!in_array($mode, self::VALID_MODES, true)) {
            throw new InvalidRelMonCastOptionsException(
                sprintf(
                    'Invalid RelMon column cast mode [%s]. Supported modes: %s.',
                    $mode,
                    implode(', ', self::VALID_MODES)
                )
            );
        }

        return new self(
            net: $parsed['net'],
            gross: $parsed['gross'],
            tax: $parsed['tax'],
            taxRate: $parsed['taxRate'] ?? null,
            unit: $parsed['unit'] ?? null,
            precision: $parsed['precision'] ?? null,
            taxRatePrecision: $parsed['taxRatePrecision'] ?? null,
            scope: $parsed['scope'] ?? null,
            roundingMode: $parsed['roundingMode'] ?? null,
            roundingApplication: $parsed['roundingApplication'] ?? null,
            mode: $mode,
        );
    }

    public function requiredColumns(): array
    {
        return [
            'net' => $this->net,
            'gross' => $this->gross,
            'tax' => $this->tax,
        ];
    }

    public function optionalColumns(): array
    {
        return array_filter(
            [
                'taxRate' => $this->taxRate,
                'unit' => $this->unit,
                'precision' => $this->precision,
                'taxRatePrecision' => $this->taxRatePrecision,
                'scope' => $this->scope,
                'roundingMode' => $this->roundingMode,
                'roundingApplication' => $this->roundingApplication,
            ],
            static fn(?string $column): bool => $column !== null
        );
    }

    public function configuredColumns(): array
    {
        return array_merge($this->requiredColumns(), $this->optionalColumns());
    }
}
