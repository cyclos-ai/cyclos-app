<?php

namespace App\Http\Requests\Container;

use Illuminate\Foundation\Http\FormRequest;

class FilterContainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filters'              => ['nullable', 'array'],
            'filters.*.field'      => ['required_with:filters', 'string', 'max:100'],
            'filters.*.operator'   => ['required_with:filters', 'string', 'in:eq,neq,gt,gte,lt,lte,contains,not_contains,starts_with,ends_with,is_null,is_not_null,in,not_in'],
            'filters.*.value'      => ['nullable'],
            'order_by'             => ['nullable', 'string', 'max:100'],
            'direction'            => ['nullable', 'in:1,-1'],
            'page_num'             => ['nullable', 'integer', 'min:0'],
            'page_size'            => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
