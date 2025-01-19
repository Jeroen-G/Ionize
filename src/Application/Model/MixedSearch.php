<?php

declare(strict_types=1);

namespace JeroenG\Explorer\Application\Model;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Searchable;

class MixedSearch
{
    /**
     * Perform a search against the model's indexed data.
     *
     * @param string $query
     * @param \Closure $callback
     * @return \Laravel\Scout\Builder
     */
    public static function search($query = '', $callback = null)
    {
        return app(Builder::class, [
            'model' => new class extends Model {
                use Searchable;
            },
            'query' => $query,
            'callback' => $callback
        ]);
    }
}
