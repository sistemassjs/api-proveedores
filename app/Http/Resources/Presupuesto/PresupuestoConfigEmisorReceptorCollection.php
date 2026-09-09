<?php

namespace App\Http\Resources\Presupuesto;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class PresupuestoConfigEmisorReceptorCollection extends ResourceCollection
{
    public $collects = PresupuestoConfigEmisorReceptorResource::class;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $response = [
            'data' => $this->collection,
        ];

        if ($this->resource instanceof LengthAwarePaginator) {
            $response['meta'] = [
                'total' => $this->resource->total(),
                'count' => $this->resource->count(),
                'per_page' => $this->resource->perPage(),
                'current_page' => $this->resource->currentPage(),
                'total_pages' => $this->resource->lastPage(),
                'from' => $this->resource->firstItem(),
                'to' => $this->resource->lastItem(),
            ];
            $response['links'] = [
                'first' => $this->resource->url(1),
                'last' => $this->resource->url($this->resource->lastPage()),
                'prev' => $this->resource->previousPageUrl(),
                'next' => $this->resource->nextPageUrl(),
            ];
        }

        return $response;
    }
}
