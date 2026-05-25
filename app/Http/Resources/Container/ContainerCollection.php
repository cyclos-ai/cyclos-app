<?php

namespace App\Http\Resources\Container;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ContainerCollection extends ResourceCollection
{
    public $collects = ContainerResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
