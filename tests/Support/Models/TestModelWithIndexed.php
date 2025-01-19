<?php

declare(strict_types=1);

namespace JeroenG\Explorer\Tests\Support\Models;

use JeroenG\Explorer\Application\BeIndexed;
use JeroenG\Explorer\Application\Explored;

class TestModelWithIndexed implements Explored, BeIndexed
{
    public function getScoutKey(): string
    {
        return ':scout_key:';
    }

    public function searchableAs(): string
    {
        return ':searchable_as:';
    }

    public function toSearchableArray(): array
    {
        return [ 'data' => true ];
    }

    public function mappableAs(): array
    {
        return [
            'data' => [ 'type' => 'boolean' ]
        ];
    }

    public function prepareIndexAction(array $action): array
    {
        $action['index']['_routing'] = ':routing_key:';

        return $action;
    }
}
