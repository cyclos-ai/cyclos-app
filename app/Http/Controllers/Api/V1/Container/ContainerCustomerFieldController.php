<?php

namespace App\Http\Controllers\Api\V1\Container;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Container;
use App\Models\Tenant\ContainerCustomerField;
use App\Models\Tenant\TrackingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContainerCustomerFieldController extends Controller
{
    /**
     * GET /api/v1/containers/{uuid}/customer-fields
     */
    public function index(string $containerUuid): JsonResponse
    {
        $container = Container::where('uuid', $containerUuid)->first();

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $fields = ContainerCustomerField::where('container_uuid', $containerUuid)->get();

        return $this->success($fields);
    }

    /**
     * POST /api/v1/containers/{uuid}/customer-fields
     */
    public function store(Request $request, string $containerUuid): JsonResponse
    {
        $container = Container::where('uuid', $containerUuid)->first();

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $request->validate([
            'field_name'  => 'required|string|max:255',
            'field_value' => 'nullable|string|max:1000',
            'field_type'  => 'nullable|string|in:text,number,date,boolean',
        ]);

        $field = ContainerCustomerField::create([
            'container_uuid' => $containerUuid,
            'field_name'     => $request->input('field_name'),
            'field_value'    => $request->input('field_value'),
            'field_type'     => $request->input('field_type', 'text'),
        ]);

        return $this->created($field, 'Customer field created');
    }

    /**
     * GET /api/v1/containers/{uuid}/customer-fields/{field_uuid}
     */
    public function show(string $containerUuid, string $fieldUuid): JsonResponse
    {
        $field = ContainerCustomerField::where('container_uuid', $containerUuid)
            ->where('uuid', $fieldUuid)
            ->first();

        if (! $field) {
            return $this->notFound('Customer field not found');
        }

        return $this->success($field);
    }

    /**
     * PUT /api/v1/containers/{uuid}/customer-fields/{field_uuid}
     */
    public function update(Request $request, string $containerUuid, string $fieldUuid): JsonResponse
    {
        $field = ContainerCustomerField::where('container_uuid', $containerUuid)
            ->where('uuid', $fieldUuid)
            ->first();

        if (! $field) {
            return $this->notFound('Customer field not found');
        }

        $request->validate([
            'field_name'  => 'sometimes|string|max:255',
            'field_value' => 'nullable|string|max:1000',
            'field_type'  => 'nullable|string|in:text,number,date,boolean',
        ]);

        $field->update($request->only(['field_name', 'field_value', 'field_type']));

        return $this->success($field->fresh(), 'Customer field updated');
    }

    /**
     * DELETE /api/v1/containers/{uuid}/customer-fields/{field_uuid}
     */
    public function destroy(string $containerUuid, string $fieldUuid): JsonResponse
    {
        $field = ContainerCustomerField::where('container_uuid', $containerUuid)
            ->where('uuid', $fieldUuid)
            ->first();

        if (! $field) {
            return $this->notFound('Customer field not found');
        }

        $field->delete();

        return $this->noContent();
    }

    /**
     * GET /api/v1/tracking-requests/{uuid}/customer-fields
     */
    public function byTrackingRequest(string $trackingRequestUuid, Request $request): JsonResponse
    {
        $trackingRequest = TrackingRequest::where('uuid', $trackingRequestUuid)->first();

        if (! $trackingRequest) {
            return $this->notFound('Tracking request not found');
        }

        $fields = ContainerCustomerField::where('tracking_request_uuid', $trackingRequestUuid)->get();

        return $this->success($fields);
    }
}
