<?php

namespace App\Http\Controllers\Api\V1\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Requests\Booking\UpdateBookingRequest;
use App\Http\Resources\Booking\BookingResource;
use App\Models\Tenant\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /**
     * GET /api/v1/bookings
     */
    public function index(Request $request): JsonResponse
    {
        $query = Booking::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'created_at'),
            (int) $request->input('direction', -1)
        );

        return $this->paginateResource($query, $request, BookingResource::class);
    }

    /**
     * GET /api/v1/bookings/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $booking = Booking::where('uuid', $uuid)->first();

        if (! $booking) {
            return $this->notFound('Booking not found');
        }

        return $this->success(new BookingResource($booking));
    }

    /**
     * GET /api/v1/bookings/number/{booking_number}
     */
    public function byNumber(string $bookingNumber): JsonResponse
    {
        $booking = Booking::where('booking_number', $bookingNumber)->first();

        if (! $booking) {
            return $this->notFound('Booking not found');
        }

        return $this->success(new BookingResource($booking));
    }

    /**
     * POST /api/v1/bookings
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = Booking::create($request->validated());

        return $this->created(new BookingResource($booking), 'Booking created successfully');
    }

    /**
     * PUT /api/v1/bookings/{uuid}
     */
    public function update(UpdateBookingRequest $request, string $uuid): JsonResponse
    {
        $booking = Booking::where('uuid', $uuid)->first();

        if (! $booking) {
            return $this->notFound('Booking not found');
        }

        $booking->update($request->validated());

        return $this->success(new BookingResource($booking->fresh()), 'Booking updated successfully');
    }
}
