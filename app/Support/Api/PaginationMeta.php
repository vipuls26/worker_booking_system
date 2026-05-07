<?php

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginationMeta
{
    /**
     * @return array<string, int|null>
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
