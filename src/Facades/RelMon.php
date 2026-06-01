<?php

namespace FromDevelopersForDevelopers\RelMonLaravel\Facades;

use FromDevelopersForDevelopers\RelMon\ValueObject\RelMonObject;
use Illuminate\Support\Facades\Facade;

/**
 * @method static RelMonObject build(mixed $input, ?string $format = null, ?array $defaults = null)
 */
final class RelMon extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'relmon';
    }
}
