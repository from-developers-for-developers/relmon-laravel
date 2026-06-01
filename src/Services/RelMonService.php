<?php

namespace FromDevelopersForDevelopers\RelMonLaravel\Services;

use FromDevelopersForDevelopers\RelMon\Enum\Format;
use FromDevelopersForDevelopers\RelMon\FormatParser\FormatParserInterface;
use FromDevelopersForDevelopers\RelMon\RelMonFacade;
use FromDevelopersForDevelopers\RelMon\ValueObject\RelMonObject;
use FromDevelopersForDevelopers\RelMonLaravel\Contracts\RelMonServiceContract;
use FromDevelopersForDevelopers\RelMonLaravel\Exceptions\InvalidRelMonConfigurationException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

final class RelMonService implements RelMonServiceContract
{
    public function __construct(
        private Application $app,
        private Repository $config,
    ) {
    }

    public function build(mixed $input, ?string $format = null, ?array $defaults = null): RelMonObject
    {
        return RelMonFacade::build(
            $input,
            $this->resolveFormat($format),
            $this->resolveCustomParsers(),
            $this->resolveDefaults($defaults),
        );
    }

    private function resolveFormat(?string $format): string
    {
        if ($format !== null) {
            return $format;
        }

        $configuredFormat = $this->config->get('relmon.default_format', Format::AUTO);

        if (!is_string($configuredFormat)) {
            throw new InvalidRelMonConfigurationException(
                'The [relmon.default_format] configuration value must be a string.'
            );
        }

        return $configuredFormat;
    }

    private function resolveDefaults(?array $defaults): array
    {
        if ($defaults !== null) {
            return $defaults;
        }

        $configuredDefaults = $this->config->get('relmon.defaults', []);

        if (!is_array($configuredDefaults)) {
            throw new InvalidRelMonConfigurationException(
                'The [relmon.defaults] configuration value must be an array.'
            );
        }

        return $configuredDefaults;
    }

    /**
     * @return FormatParserInterface[]
     */
    private function resolveCustomParsers(): array
    {
        $configuredParsers = $this->config->get('relmon.custom_parsers', []);

        if (!is_array($configuredParsers)) {
            throw new InvalidRelMonConfigurationException(
                'The [relmon.custom_parsers] configuration value must be an array of parser class names.'
            );
        }

        $resolvedParsers = [];
        $seenParsers = [];

        foreach ($configuredParsers as $index => $parserClass) {
            if (!is_string($parserClass) || trim($parserClass) === '') {
                throw new InvalidRelMonConfigurationException(
                    sprintf(
                        'The [relmon.custom_parsers] entry at index [%s] must be a non-empty parser class name string.',
                        $index
                    )
                );
            }

            $parserClass = trim($parserClass);

            if (isset($seenParsers[$parserClass])) {
                continue;
            }

            $seenParsers[$parserClass] = true;
            $resolvedParser = $this->resolveParser($parserClass);

            if (!$resolvedParser instanceof FormatParserInterface) {
                throw new InvalidRelMonConfigurationException(
                    sprintf(
                        'The custom RelMon parser [%s] must implement [%s].',
                        $parserClass,
                        FormatParserInterface::class
                    )
                );
            }

            $resolvedParsers[] = $resolvedParser;
        }

        return $resolvedParsers;
    }

    private function resolveParser(string $parserClass): mixed
    {
        try {
            return $this->app->make($parserClass);
        } catch (\Throwable $exception) {
            throw new InvalidRelMonConfigurationException(
                sprintf('Unable to resolve custom RelMon parser [%s] from the Laravel container.', $parserClass),
                previous: $exception,
            );
        }
    }
}
