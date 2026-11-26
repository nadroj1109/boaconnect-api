<?php

namespace App\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    protected function success(
        $data = null,
        string $message = 'Success',
        array $meta = []
    ) {
        return response()->json([

            'success' => true,

            'message' => $message,

            'data' => $data,

            'meta' => (object) $meta,

        ]);
    }

    protected function paginated(
        LengthAwarePaginator $paginator,
        $collection,
        string $message = 'Success'
    ) {
        return $this->success(

            $collection,

            $message,

            [

                'current_page' => $paginator->currentPage(),

                'last_page' => $paginator->lastPage(),

                'per_page' => $paginator->perPage(),

                'total' => $paginator->total(),

                'from' => $paginator->firstItem(),

                'to' => $paginator->lastItem(),

            ]

        );
    }

    protected function error(
        string $message,
        $errors = [],
        int $status = 422
    ) {
        return response()->json([

            'success' => false,

            'message' => $message,

            'errors' => $errors,

        ], $status);
    }
}
