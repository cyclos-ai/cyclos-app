<?php

namespace App\Http\Controllers\Api\V1\Report;

use App\Http\Controllers\Controller;
use App\Http\Resources\Report\ReportResource;
use App\Models\Tenant\Report;
use App\Models\Tenant\ReportSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * GET /api/v1/reports
     */
    public function index(Request $request): JsonResponse
    {
        $query = Report::query();

        $this->applySorting(
            $query,
            $request->input('order_by', 'created_at'),
            (int) $request->input('direction', -1)
        );

        return $this->paginateResource($query, $request, ReportResource::class);
    }

    /**
     * GET /api/v1/reports/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $report = Report::where('uuid', $uuid)->with('schedules')->first();

        if (! $report) {
            return $this->notFound('Report not found');
        }

        return $this->success(new ReportResource($report));
    }

    /**
     * POST /api/v1/reports
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'report_type' => 'required|string|max:100',
            'config'      => 'nullable|array',
            'filters'     => 'nullable|array',
            'columns'     => 'nullable|array',
        ]);

        $report = Report::create([
            'name'        => $request->input('name'),
            'report_type' => $request->input('report_type'),
            'config'      => $request->input('config'),
            'filters'     => $request->input('filters'),
            'columns'     => $request->input('columns'),
            'user_id'     => $request->user()->id,
        ]);

        return $this->created(new ReportResource($report), 'Report created');
    }

    /**
     * PUT /api/v1/reports/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $report = Report::where('uuid', $uuid)->first();

        if (! $report) {
            return $this->notFound('Report not found');
        }

        $request->validate([
            'name'    => 'sometimes|string|max:255',
            'config'  => 'nullable|array',
            'filters' => 'nullable|array',
            'columns' => 'nullable|array',
        ]);

        $report->update($request->only(['name', 'config', 'filters', 'columns']));

        return $this->success(new ReportResource($report->fresh()), 'Report updated');
    }

    /**
     * DELETE /api/v1/reports/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $report = Report::where('uuid', $uuid)->first();

        if (! $report) {
            return $this->notFound('Report not found');
        }

        $report->delete();

        return $this->noContent();
    }

    /**
     * POST /api/v1/reports/{uuid}/generate
     */
    public function generate(string $uuid, Request $request): JsonResponse
    {
        $report = Report::where('uuid', $uuid)->first();

        if (! $report) {
            return $this->notFound('Report not found');
        }

        // Dispatch report generation job
        // In production: dispatch(new GenerateReportJob($report))->onQueue('reports');

        $report->update([
            'last_generated_at' => now(),
            'status'            => 'generating',
        ]);

        return $this->success([
            'report_uuid' => $uuid,
            'status'      => 'generating',
            'message'     => 'Report generation started',
        ]);
    }

    /**
     * POST /api/v1/reports/{uuid}/schedule
     */
    public function schedule(Request $request, string $uuid): JsonResponse
    {
        $report = Report::where('uuid', $uuid)->first();

        if (! $report) {
            return $this->notFound('Report not found');
        }

        $request->validate([
            'frequency'     => 'required|in:daily,weekly,monthly',
            'day_of_week'   => 'required_if:frequency,weekly|nullable|integer|min:0|max:6',
            'day_of_month'  => 'required_if:frequency,monthly|nullable|integer|min:1|max:31',
            'time'          => 'required|date_format:H:i',
            'recipients'    => 'required|array|min:1',
            'recipients.*'  => 'email',
            'format'        => 'nullable|in:pdf,excel,csv',
        ]);

        $schedule = $report->schedules()->create($request->validated());

        return $this->created($schedule, 'Report schedule created');
    }

    /**
     * PUT /api/v1/reports/{uuid}/schedule/{schedule_uuid}
     */
    public function updateSchedule(Request $request, string $uuid, string $scheduleUuid): JsonResponse
    {
        $report = Report::where('uuid', $uuid)->first();

        if (! $report) {
            return $this->notFound('Report not found');
        }

        $schedule = $report->schedules()->where('uuid', $scheduleUuid)->first();

        if (! $schedule) {
            return $this->notFound('Schedule not found');
        }

        $request->validate([
            'frequency'    => 'sometimes|in:daily,weekly,monthly',
            'day_of_week'  => 'nullable|integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'time'         => 'sometimes|date_format:H:i',
            'recipients'   => 'sometimes|array|min:1',
            'recipients.*' => 'email',
            'format'       => 'nullable|in:pdf,excel,csv',
            'is_active'    => 'nullable|boolean',
        ]);

        $schedule->update($request->only(['frequency', 'day_of_week', 'day_of_month', 'time', 'recipients', 'format', 'is_active']));

        return $this->success($schedule->fresh(), 'Schedule updated');
    }

    /**
     * DELETE /api/v1/reports/{uuid}/schedule/{schedule_uuid}
     */
    public function deleteSchedule(string $uuid, string $scheduleUuid): JsonResponse
    {
        $report = Report::where('uuid', $uuid)->first();

        if (! $report) {
            return $this->notFound('Report not found');
        }

        $schedule = $report->schedules()->where('uuid', $scheduleUuid)->first();

        if (! $schedule) {
            return $this->notFound('Schedule not found');
        }

        $schedule->delete();

        return $this->noContent();
    }
}
