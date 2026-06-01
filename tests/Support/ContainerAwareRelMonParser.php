<?php

namespace FromDevelopersForDevelopers\RelMonLaravel\Tests\Support;

use FromDevelopersForDevelopers\RelMon\Dto\RelMonDto;
use FromDevelopersForDevelopers\RelMon\FormatParser\FormatParserInterface;

final class ContainerAwareRelMonParser implements FormatParserInterface
{
    public function __construct(ParserConstructionCounter $counter)
    {
        $counter->constructed++;
    }

    public function parse(mixed $input): RelMonDto
    {
        return new RelMonDto(
            protocolIdentifier: 'relmon@1.0.0/3',
            net: '100.00',
            gross: '121.00',
            tax: '21.00',
        );
    }
}
