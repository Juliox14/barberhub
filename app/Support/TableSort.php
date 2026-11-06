<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class TableSort
{
    /**
     * @param  array<string, string|callable(Builder, string): void>  $columns
     * @return array{sort: string, direction: string, requested: bool}
     */
    public static function state(
        Request $request,
        array $columns,
        string $defaultSort,
        string $defaultDirection = 'asc',
        string $sortParameter = 'sort',
        string $directionParameter = 'direction',
    ): array {
        $requestedSort = $request->query($sortParameter);
        $requestedDirection = $request->query($directionParameter);
        $hasValidSort = is_string($requestedSort) && array_key_exists($requestedSort, $columns);
        $sort = $hasValidSort ? $requestedSort : $defaultSort;
        $direction = $hasValidSort
            && is_string($requestedDirection)
            && in_array($requestedDirection, ['asc', 'desc'], true)
                ? $requestedDirection
                : $defaultDirection;

        return [
            'sort' => $sort,
            'direction' => $direction,
            'requested' => is_string($requestedSort) && array_key_exists($requestedSort, $columns),
        ];
    }

    /**
     * @param  array<string, string|callable(Builder, string): void>  $columns
     */
    public static function apply(Builder $query, array $columns, string $sort, string $direction): void
    {
        if (! array_key_exists($sort, $columns)) {
            return;
        }

        $query->reorder();
        $column = $columns[$sort];

        if (is_string($column)) {
            $query->orderBy($column, $direction);
        } else {
            $column($query, $direction);
        }

        $query->orderBy($query->getModel()->qualifyColumn($query->getModel()->getKeyName()), $direction);
    }
}
