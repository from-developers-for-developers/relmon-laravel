<?php

namespace FromDevelopersForDevelopers\RelMonLaravel\Contracts;

use FromDevelopersForDevelopers\RelMon\ValueObject\RelMonObject;

interface RelMonServiceContract
{
    public function build(mixed $input, ?string $format = null, ?array $defaults = null): RelMonObject;
}
