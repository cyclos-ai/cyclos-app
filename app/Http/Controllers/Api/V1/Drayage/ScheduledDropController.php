<?php

namespace App\Http\Controllers\Api\V1\Drayage;

use App\Exports\ScheduledDropsExport;
use App\Http\Controllers\Controller;
use App\Mail\ScheduledDropsMail;
use App\Models\Tenant\Container;
use App\Models\Tenant\DrayageCarrier;
use App\Models\Tenant\ScheduledDrop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScheduledDropController extends Controller
{
    // ----------------------------------------------------------------
    // GET /api/v1/scheduled-drops
    // ----------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        $query = ScheduledDrop::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->input('batch_id'));
        }

        if ($request->filled('drayage_carrier_id')) {
            $query->where('drayage_carrier_id', $request->input('drayage_carrier_id'));
        }

        $query->orderBy('created_at', 'desc');

        return $this->paginate($query, $request);
    }

    // ----------------------------------------------------------------
    // POST /api/v1/scheduled-drops
    // ----------------------------------------------------------------
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'drops'                         => 'nullable|array',
            'drops.*.container_number'      => 'required_with:drops|string|max:255',
            'drops.*.drayage_carrier_name'  => 'nullable|string|max:255',
            'drops.*.batch_id'              => 'nullable|uuid',
            // single-row fields
            'container_number'              => 'required_without:drops|string|max:255',
            'drayage_carrier_name'          => 'nullable|string|max:255',
            'batch_id'                      => 'nullable|uuid',
        ]);

        $rows = $request->has('drops') ? $request->input('drops') : [$request->all()];
        $created = [];

        foreach ($rows as $row) {
            $drop = $this->buildDrop($row);
            $drop['status'] = 'draft';
            $created[] = ScheduledDrop::create($drop);
        }

        return $this->created(
            count($created) === 1 ? $created[0] : $created,
            'Scheduled drop(s) created'
        );
    }

    // ----------------------------------------------------------------
    // POST /api/v1/scheduled-drops/from-containers
    // ----------------------------------------------------------------
    public function fromContainers(Request $request): JsonResponse
    {
        $request->validate([
            'container_ids'        => 'required|array|min:1',
            'container_ids.*'      => 'required|uuid',
            'drayage_carrier_id'   => 'nullable|uuid',
            'drayage_carrier_name' => 'nullable|string|max:255',
            'dc_code'              => 'nullable|string|max:50',
            'dc_name'              => 'nullable|string|max:255',
            'estimated_drop_date'  => 'nullable|date',
            'terminal_pickup'      => 'nullable|string|max:255',
            'requested_stack'      => 'nullable|string|max:255',
            'dray_notes'           => 'nullable|string',
        ]);

        $batchId     = (string) Str::uuid();
        $sharedData  = $request->only([
            'drayage_carrier_id',
            'drayage_carrier_name',
            'dc_code',
            'dc_name',
            'estimated_drop_date',
            'terminal_pickup',
            'requested_stack',
            'dray_notes',
        ]);

        // Resolve carrier name from record if not provided
        if (empty($sharedData['drayage_carrier_name']) && ! empty($sharedData['drayage_carrier_id'])) {
            $carrier = DrayageCarrier::find($sharedData['drayage_carrier_id']);
            $sharedData['drayage_carrier_name'] = $carrier?->company_name ?? '';
        }

        $containers = Container::with('vessel')
            ->whereIn('id', $request->input('container_ids'))
            ->get()
            ->keyBy('id');

        $created = [];

        foreach ($request->input('container_ids') as $containerId) {
            $container = $containers->get($containerId);

            if (! $container) {
                continue;
            }

            $drop = array_merge($sharedData, [
                'batch_id'         => $batchId,
                'container_id'     => $container->id,
                'container_number' => $container->container_number,
                'vessel_eta'       => $container->vessel?->eta ?? $container->eta,
                'mother_vessel'    => $container->vessel?->name,
                'ocean_scac'       => $container->carrier_scac,
                'dem_lfd'          => $container->last_free_day_demurrage?->toDateString(),
                'container_type'   => $this->resolveContainerType($container),
                'status'           => 'draft',
            ]);

            $created[] = ScheduledDrop::create($drop);
        }

        return $this->created([
            'batch_id' => $batchId,
            'count'    => count($created),
            'drops'    => $created,
        ], 'Scheduled drops created from containers');
    }

    // ----------------------------------------------------------------
    // PUT /api/v1/scheduled-drops/{uuid}
    // ----------------------------------------------------------------
    public function update(Request $request, string $uuid): JsonResponse
    {
        $drop = ScheduledDrop::find($uuid);

        if (! $drop) {
            return $this->notFound('Scheduled drop not found');
        }

        if ($drop->status !== 'draft') {
            return $this->error('Only draft drops can be edited', 422);
        }

        $request->validate([
            'drayage_carrier_name' => 'nullable|string|max:255',
            'dc_code'              => 'nullable|string|max:50',
            'dc_name'              => 'nullable|string|max:255',
            'vessel_eta'           => 'nullable|date',
            'mother_vessel'        => 'nullable|string|max:255',
            'estimated_drop_date'  => 'nullable|date',
            'container_number'     => 'nullable|string|max:255',
            'terminal_pickup'      => 'nullable|string|max:255',
            'ocean_scac'           => 'nullable|string|max:10',
            'dem_lfd'              => 'nullable|date',
            'container_type'       => 'nullable|string|max:20',
            'requested_stack'      => 'nullable|string|max:255',
            'dray_notes'           => 'nullable|string',
        ]);

        $drop->update($request->validated());

        return $this->success($drop->fresh(), 'Scheduled drop updated');
    }

    // ----------------------------------------------------------------
    // DELETE /api/v1/scheduled-drops/{uuid}
    // ----------------------------------------------------------------
    public function destroy(string $uuid): JsonResponse
    {
        $drop = ScheduledDrop::find($uuid);

        if (! $drop) {
            return $this->notFound('Scheduled drop not found');
        }

        $drop->delete();

        return $this->noContent();
    }

    // ----------------------------------------------------------------
    // GET /api/v1/scheduled-drops/export?batch={id}
    // ----------------------------------------------------------------
    public function export(Request $request): BinaryFileResponse|JsonResponse
    {
        $request->validate([
            'batch_id' => 'nullable|uuid',
        ]);

        $query = ScheduledDrop::query();

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->input('batch_id'));
        }

        $drops = $query->orderBy('created_at')->get();

        if ($drops->isEmpty()) {
            return $this->error('No drops found for export', 404);
        }

        $filename = 'Scheduled_Drops_' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new ScheduledDropsExport($drops), $filename);
    }

    // ----------------------------------------------------------------
    // POST /api/v1/scheduled-drops/send
    // ----------------------------------------------------------------
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'batch_id'      => 'nullable|uuid|required_without:drop_ids',
            'drop_ids'      => 'nullable|array|required_without:batch_id',
            'drop_ids.*'    => 'uuid',
            'carrier_email' => 'nullable|email',
        ]);

        // Resolve drops
        if ($request->filled('batch_id')) {
            $drops = ScheduledDrop::where('batch_id', $request->input('batch_id'))->get();
        } else {
            $drops = ScheduledDrop::whereIn('id', $request->input('drop_ids', []))->get();
        }

        if ($drops->isEmpty()) {
            return $this->error('No scheduled drops found', 404);
        }

        // Resolve email
        $toEmail = $request->input('carrier_email');

        if (! $toEmail) {
            $carrierId = $drops->first()->drayage_carrier_id;
            if ($carrierId) {
                $carrier = DrayageCarrier::find($carrierId);
                $toEmail = $carrier?->contact_email;
            }
        }

        if (! $toEmail) {
            return $this->error('No carrier email provided or found on the drayage carrier record', 422);
        }

        $exportDate = now()->format('Y-m-d');
        $sentAt     = now();
        $mailSent   = false;
        $mailMessage = 'Mail not sent — SMTP may not be configured';

        try {
            Mail::to($toEmail)->send(new ScheduledDropsMail($drops, $exportDate));
            $mailSent    = true;
            $mailMessage = "Email sent to {$toEmail}";
        } catch (\Throwable $e) {
            Log::warning('ScheduledDrops mail send failed', [
                'to'    => $toEmail,
                'error' => $e->getMessage(),
            ]);
            $mailMessage = 'Mail delivery failed: ' . $e->getMessage();
        }

        // Mark drops as sent regardless of mail outcome
        $ids = $drops->pluck('id')->all();
        ScheduledDrop::whereIn('id', $ids)->update([
            'status'         => 'sent',
            'sent_at'        => $sentAt,
            'sent_to_email'  => $toEmail,
        ]);

        return $this->success([
            'sent_count'  => $drops->count(),
            'sent_to'     => $toEmail,
            'mail_sent'   => $mailSent,
            'message'     => $mailMessage,
        ], $mailSent ? 'Scheduled drops sent' : 'Drops marked sent but email delivery failed');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function buildDrop(array $row): array
    {
        $drop = [
            'batch_id'             => $row['batch_id']             ?? (string) Str::uuid(),
            'drayage_carrier_id'   => $row['drayage_carrier_id']   ?? null,
            'drayage_carrier_name' => $row['drayage_carrier_name']  ?? '',
            'dc_code'              => $row['dc_code']               ?? null,
            'dc_name'              => $row['dc_name']               ?? null,
            'vessel_eta'           => $row['vessel_eta']            ?? null,
            'mother_vessel'        => $row['mother_vessel']         ?? null,
            'estimated_drop_date'  => $row['estimated_drop_date']   ?? null,
            'container_id'         => $row['container_id']          ?? null,
            'container_number'     => $row['container_number'],
            'terminal_pickup'      => $row['terminal_pickup']       ?? null,
            'ocean_scac'           => $row['ocean_scac']            ?? null,
            'dem_lfd'              => $row['dem_lfd']               ?? null,
            'container_type'       => $row['container_type']        ?? null,
            'requested_stack'      => $row['requested_stack']       ?? null,
            'dray_notes'           => $row['dray_notes']            ?? null,
        ];

        // Auto-fill from Container record if container_id or container_number is given
        $container = null;

        if (! empty($drop['container_id'])) {
            $container = Container::with('vessel')->find($drop['container_id']);
        }

        if (! $container && ! empty($drop['container_number'])) {
            $container = Container::with('vessel')
                ->where('container_number', $drop['container_number'])
                ->first();
            if ($container) {
                $drop['container_id'] = $container->id;
            }
        }

        if ($container) {
            $drop['vessel_eta']    ??= $container->vessel?->eta ?? $container->eta;
            $drop['mother_vessel'] ??= $container->vessel?->name;
            $drop['ocean_scac']    ??= $container->carrier_scac;
            $drop['dem_lfd']       ??= $container->last_free_day_demurrage?->toDateString();
            $drop['container_type'] ??= $this->resolveContainerType($container);
        }

        // Resolve carrier name from FK if not provided
        if (empty($drop['drayage_carrier_name']) && ! empty($drop['drayage_carrier_id'])) {
            $carrier = DrayageCarrier::find($drop['drayage_carrier_id']);
            $drop['drayage_carrier_name'] = $carrier?->company_name ?? '';
        }

        return $drop;
    }

    private function resolveContainerType(Container $container): ?string
    {
        // The Container model casts 'type' to ContainerType enum; get the raw value for the spreadsheet
        if ($container->type === null) {
            return null;
        }

        return is_object($container->type) ? $container->type->value : (string) $container->type;
    }
}
