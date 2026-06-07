<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Air\AirShipmentController;
use App\Http\Controllers\Api\V1\Booking\BookingController;
use App\Http\Controllers\Api\V1\Calendar\CalendarController;
use App\Http\Controllers\Api\V1\CarrierContract\CarrierContractController;
use App\Http\Controllers\Api\V1\Container\ContainerController;
use App\Http\Controllers\Api\V1\Container\ContainerCustomerFieldController;
use App\Http\Controllers\Api\V1\CustomColumn\CustomColumnController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\DataUpload\CSVUploadController;
use App\Http\Controllers\Api\V1\DataUpload\DataUploadController;
use App\Http\Controllers\Api\V1\Demurrage\DemurrageController;
use App\Http\Controllers\Api\V1\Demurrage\DetentionController;
use App\Http\Controllers\Api\V1\DistributionCenter\DistributionCenterController;
use App\Http\Controllers\Api\V1\Drayage\DrayageStepController;
use App\Http\Controllers\Api\V1\Drayage\ImportDrayageController;
use App\Http\Controllers\Api\V1\Drayage\ScheduledDropController;
use App\Http\Controllers\Api\V1\Factory\FactoryController;
use App\Http\Controllers\Api\V1\Integration\QuickBooksIntegrationController;
use App\Http\Controllers\Api\V1\Integration\TruckHubIntegrationController;
use App\Http\Controllers\Api\V1\Invoice\DrayageInvoiceController;
use App\Http\Controllers\Api\V1\Invoice\OceanInvoiceController;
use App\Http\Controllers\Api\V1\Map\MapController;
use App\Http\Controllers\Api\V1\MBL\MBLController;
use App\Http\Controllers\Api\V1\Organization\OrganizationController;
use App\Http\Controllers\Api\V1\PurchaseOrder\PurchaseOrderController;
use App\Http\Controllers\Api\V1\Rail\RailController;
use App\Http\Controllers\Api\V1\Report\ReportController;
use App\Http\Controllers\Api\V1\SKU\SKUController;
use App\Http\Controllers\Api\V1\Terminal\TerminalController;
use App\Http\Controllers\Api\V1\Carrier\CarrierController;
use App\Http\Controllers\Api\V1\Tracking\MultiSourceTrackingController;
use App\Http\Controllers\Api\V1\Tracking\TrackingRequestController;
use App\Http\Controllers\Api\V1\TransitTime\TransitTimeController;
use App\Http\Controllers\Api\V1\Vendor\VendorController;
use App\Http\Controllers\Api\V1\Vessel\AisController;
use App\Http\Controllers\Api\V1\Vessel\VesselController;
use App\Http\Controllers\Api\V1\Volume\VolumeController;
use App\Http\Controllers\Api\V1\Document\DocumentExtractionController;
use App\Http\Controllers\Api\V1\Edi\EdiWebhookController;
use App\Http\Controllers\Api\V1\Webhook\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
| All routes here are loaded within a tenant context by stancl/tenancy.
| The tenancy middleware initialises the tenant DB connection before any
| controller logic runs.
*/

Route::middleware(['auth:api', 'throttle:api', 'count.api'])
    ->name('api.v1.')
    ->group(function () {

        // ----------------------------------------------------------------
        // Containers
        // ----------------------------------------------------------------
        Route::prefix('containers')->name('containers.')->group(function () {
            // Static sub-routes must come before {uuid} to avoid conflicts
            Route::get('active',                [ContainerController::class, 'active'])->name('active');
            Route::get('not-tracking',          [ContainerController::class, 'notTracking'])->name('not-tracking');
            Route::get('dropped-mbl',           [ContainerController::class, 'droppedMbl'])->name('dropped-mbl');
            Route::get('mbl/{mbl_number}',      [ContainerController::class, 'byMbl'])->name('by-mbl');
            Route::get('number/{container_number}', [ContainerController::class, 'byContainerNumber'])->name('by-number');
            Route::post('filter',               [ContainerController::class, 'filter'])->name('filter');

            Route::get('/',                     [ContainerController::class, 'index'])->name('index');
            Route::post('/',                    [ContainerController::class, 'store'])->name('store');
            Route::get('{uuid}',                [ContainerController::class, 'show'])->name('show');
            Route::put('{uuid}',                [ContainerController::class, 'update'])->name('update');
            Route::delete('{uuid}',             [ContainerController::class, 'destroy'])->name('destroy');
            Route::patch('{uuid}/priority',     [ContainerController::class, 'updatePriority'])->name('priority');
            Route::patch('{uuid}/outgate',      [ContainerController::class, 'outgate'])->name('outgate');
            Route::patch('{uuid}/empty-return', [ContainerController::class, 'emptyReturn'])->name('empty-return');
            Route::get('{uuid}/location-history', [ContainerController::class, 'locationHistory'])->name('location-history');
            Route::post('{uuid}/link-vessel',    [ContainerController::class, 'linkVessel'])->name('link-vessel');

            // Container customer fields (nested)
            Route::get('{uuid}/customer-fields',                         [ContainerCustomerFieldController::class, 'index'])->name('customer-fields.index');
            Route::post('{uuid}/customer-fields',                        [ContainerCustomerFieldController::class, 'store'])->name('customer-fields.store');
            Route::get('{uuid}/customer-fields/{field_uuid}',            [ContainerCustomerFieldController::class, 'show'])->name('customer-fields.show');
            Route::put('{uuid}/customer-fields/{field_uuid}',            [ContainerCustomerFieldController::class, 'update'])->name('customer-fields.update');
            Route::delete('{uuid}/customer-fields/{field_uuid}',         [ContainerCustomerFieldController::class, 'destroy'])->name('customer-fields.destroy');
        });

        // ================================================================
        // SHIPPER-ONLY ROUTES
        // ================================================================
        Route::middleware(['ensure.role:shipper'])->group(function () {

        // ----------------------------------------------------------------
        // Vessels
        // ----------------------------------------------------------------
        Route::prefix('vessels')->name('vessels.')->group(function () {
            Route::get('export',        [VesselController::class, 'exportVessels'])->name('export');
            Route::get('/',             [VesselController::class, 'index'])->name('index');
            Route::get('{uuid}',        [VesselController::class, 'show'])->name('show');
            Route::put('{uuid}',        [VesselController::class, 'update'])->name('update');
            Route::get('{uuid}/schedule',   [VesselController::class, 'schedule'])->name('schedule');
            Route::get('{uuid}/containers', [VesselController::class, 'containers'])->name('containers');
        });

        // ----------------------------------------------------------------
        // MBLs
        // ----------------------------------------------------------------
        Route::prefix('mbls')->name('mbls.')->group(function () {
            Route::get('number/{mbl_number}', [MBLController::class, 'byNumber'])->name('by-number');
            Route::get('/',             [MBLController::class, 'index'])->name('index');
            Route::get('{uuid}',        [MBLController::class, 'show'])->name('show');
            Route::patch('{uuid}/not-tracking', [MBLController::class, 'updateNotTracking'])->name('not-tracking');
            Route::get('{uuid}/containers',     [MBLController::class, 'containers'])->name('containers');
        });

        // ----------------------------------------------------------------
        // Bookings
        // ----------------------------------------------------------------
        Route::prefix('bookings')->name('bookings.')->group(function () {
            Route::get('number/{booking_number}', [BookingController::class, 'byNumber'])->name('by-number');
            Route::get('/',             [BookingController::class, 'index'])->name('index');
            Route::post('/',            [BookingController::class, 'store'])->name('store');
            Route::get('{uuid}',        [BookingController::class, 'show'])->name('show');
            Route::put('{uuid}',        [BookingController::class, 'update'])->name('update');
        });

        // ----------------------------------------------------------------
        // Air Shipments
        // ----------------------------------------------------------------
        Route::prefix('air-shipments')->name('air-shipments.')->group(function () {
            Route::get('supported-carriers',    [AirShipmentController::class, 'supportedCarriers'])->name('supported-carriers');
            Route::get('awb/{awb_number}',      [AirShipmentController::class, 'showByAwb'])->name('by-awb');
            Route::get('/',                     [AirShipmentController::class, 'index'])->name('index');
            Route::post('/',                    [AirShipmentController::class, 'store'])->name('store');
        });

        }); // end ensure.role:shipper

        // ----------------------------------------------------------------
        // Steamship Line Carriers (shared)
        // ----------------------------------------------------------------
        Route::prefix('carriers')->name('carriers.')->group(function () {
            Route::get('supported',         [CarrierController::class, 'supported'])->name('supported');
            Route::get('groups',            [CarrierController::class, 'groups'])->name('groups');
            Route::get('search',            [CarrierController::class, 'search'])->name('search');
            Route::get('/',                 [CarrierController::class, 'index'])->name('index');
            Route::get('{scac}',            [CarrierController::class, 'show'])->name('show');
            Route::post('{scac}/track',     [CarrierController::class, 'track'])->name('track');
        });

        // ----------------------------------------------------------------
        // Tracking Requests (shared)
        // ----------------------------------------------------------------
        Route::prefix('tracking-requests')->name('tracking-requests.')->group(function () {
            Route::get('supported-carriers',          [TrackingRequestController::class, 'supportedCarriers'])->name('supported-carriers');
            Route::get('mbl/{mbl_number}',            [TrackingRequestController::class, 'byMbl'])->name('by-mbl');
            Route::get('container/{container_number}',[TrackingRequestController::class, 'byContainer'])->name('by-container');
            Route::post('filter',                     [TrackingRequestController::class, 'filter'])->name('filter');
            Route::post('with-carrier',               [TrackingRequestController::class, 'storeWithCarrier'])->name('with-carrier');
            Route::post('booking',                    [TrackingRequestController::class, 'storeBooking'])->name('booking');
            Route::post('non-party',                  [TrackingRequestController::class, 'storeNonParty'])->name('non-party');
            Route::post('/',                          [TrackingRequestController::class, 'store'])->name('store');
            Route::get('{uuid}',                      [TrackingRequestController::class, 'show'])->name('show');
            Route::delete('{uuid}',                   [TrackingRequestController::class, 'destroy'])->name('destroy');
            // Customer fields on tracking requests
            Route::get('{uuid}/customer-fields',      [ContainerCustomerFieldController::class, 'byTrackingRequest'])->name('customer-fields');
        });

        // ----------------------------------------------------------------
        // Rail
        // ----------------------------------------------------------------
        Route::prefix('rail')->name('rail.')->group(function () {
            Route::get('carriers',                                  [RailController::class, 'carrierLookup'])->name('carriers');
            Route::get('milestones/container/{container_number}',   [RailController::class, 'milestonesByContainer'])->name('milestones.by-container');
            Route::get('milestones/{uuid}',                         [RailController::class, 'milestonesByUuid'])->name('milestones.show');

            // Ramps
            Route::get('ramps',             [RailController::class, 'ramps'])->name('ramps.index');
            Route::get('ramps/{code}',      [RailController::class, 'rampDetail'])->name('ramps.show');

            // Shipments
            Route::get('shipments',                         [RailController::class, 'shipments'])->name('shipments.index');
            Route::post('shipments',                        [RailController::class, 'storeShipment'])->name('shipments.store');
            Route::get('shipments/{uuid}',                  [RailController::class, 'shipmentDetail'])->name('shipments.show');
            Route::put('shipments/{uuid}',                  [RailController::class, 'updateShipment'])->name('shipments.update');
            Route::patch('shipments/{uuid}/status',         [RailController::class, 'updateShipmentStatus'])->name('shipments.status');
        });

        // ----------------------------------------------------------------
        // Demurrage
        // ----------------------------------------------------------------
        Route::prefix('demurrage')->name('demurrage.')->group(function () {
            Route::get('alarms',                    [DemurrageController::class, 'alarms'])->name('alarms');
            Route::post('calculate',                [DemurrageController::class, 'calculate'])->name('calculate');
            Route::post('filter',                   [DemurrageController::class, 'filter'])->name('filter');
            Route::get('container/{uuid}',          [DemurrageController::class, 'byContainer'])->name('by-container');
            Route::get('/',                         [DemurrageController::class, 'index'])->name('index');
            Route::get('{uuid}',                    [DemurrageController::class, 'show'])->name('show');
        });

        // ----------------------------------------------------------------
        // Detention
        // ----------------------------------------------------------------
        Route::prefix('detention')->name('detention.')->group(function () {
            Route::get('alarms',            [DetentionController::class, 'alarms'])->name('alarms');
            Route::post('calculate',        [DetentionController::class, 'calculate'])->name('calculate');
            Route::get('container/{uuid}',  [DetentionController::class, 'byContainer'])->name('by-container');
            Route::get('/',                 [DetentionController::class, 'index'])->name('index');
            Route::get('{uuid}',            [DetentionController::class, 'show'])->name('show');
        });

        // ----------------------------------------------------------------
        // Drayage Invoices (shared)
        // ----------------------------------------------------------------
        Route::prefix('drayage-invoices')->name('drayage-invoices.')->group(function () {
            Route::post('filter',           [DrayageInvoiceController::class, 'filter'])->name('filter');
            Route::get('/',                 [DrayageInvoiceController::class, 'index'])->name('index');
            Route::post('/',                [DrayageInvoiceController::class, 'store'])->name('store');
            Route::get('{uuid}',            [DrayageInvoiceController::class, 'show'])->name('show');
            Route::put('{uuid}',            [DrayageInvoiceController::class, 'update'])->name('update');
            Route::delete('{uuid}',         [DrayageInvoiceController::class, 'destroy'])->name('destroy');
            Route::post('{uuid}/payments',  [DrayageInvoiceController::class, 'addPayment'])->name('payments');
        });

        // ----------------------------------------------------------------
        // Import Drayage (shared)
        // ----------------------------------------------------------------
        Route::prefix('import-drayage')->name('import-drayage.')->group(function () {
            Route::post('filter',           [ImportDrayageController::class, 'filter'])->name('filter');
            Route::get('/',                 [ImportDrayageController::class, 'index'])->name('index');
            Route::post('/',                [ImportDrayageController::class, 'store'])->name('store');
            Route::get('{uuid}',            [ImportDrayageController::class, 'show'])->name('show');
            Route::put('{uuid}',            [ImportDrayageController::class, 'update'])->name('update');
            Route::delete('{uuid}',         [ImportDrayageController::class, 'destroy'])->name('destroy');
            Route::get('{uuid}/events',     [ImportDrayageController::class, 'events'])->name('events');
        });

        // ----------------------------------------------------------------
        // Scheduled Drops (shared)
        // ----------------------------------------------------------------
        Route::prefix('scheduled-drops')->name('scheduled-drops.')->group(function () {
            Route::get('export',             [ScheduledDropController::class, 'export'])->name('export');
            Route::post('from-containers',   [ScheduledDropController::class, 'fromContainers'])->name('from-containers');
            Route::post('send',              [ScheduledDropController::class, 'send'])->name('send');
            Route::get('/',                  [ScheduledDropController::class, 'index'])->name('index');
            Route::post('/',                 [ScheduledDropController::class, 'store'])->name('store');
            Route::put('{uuid}',             [ScheduledDropController::class, 'update'])->name('update');
            Route::delete('{uuid}',          [ScheduledDropController::class, 'destroy'])->name('destroy');
        });

        // ----------------------------------------------------------------
        // Organization (shared)
        // ----------------------------------------------------------------
        Route::prefix('organizations')->name('organizations.')->group(function () {
            Route::get('current',           [OrganizationController::class, 'show'])->name('show');
            Route::put('current',           [OrganizationController::class, 'update'])->name('update');
            Route::get('members',           [OrganizationController::class, 'members'])->name('members');
            Route::post('members',          [OrganizationController::class, 'inviteMember'])->name('members.invite');
            Route::delete('members/{uuid}', [OrganizationController::class, 'removeMember'])->name('members.remove');
            Route::put('sso',               [OrganizationController::class, 'updateSso'])->name('sso');
        });

        // ----------------------------------------------------------------
        // Dashboards (shared)
        // ----------------------------------------------------------------
        Route::prefix('dashboards')->name('dashboards.')->group(function () {
            Route::get('/',         [DashboardController::class, 'index'])->name('index');
            Route::post('/',        [DashboardController::class, 'store'])->name('store');
            Route::get('{uuid}',    [DashboardController::class, 'show'])->name('show');
            Route::put('{uuid}',    [DashboardController::class, 'update'])->name('update');
            Route::delete('{uuid}', [DashboardController::class, 'destroy'])->name('destroy');
            Route::post('{uuid}/widgets',                        [DashboardController::class, 'addWidget'])->name('widgets.store');
            Route::put('{uuid}/widgets/{widget_uuid}',           [DashboardController::class, 'updateWidget'])->name('widgets.update');
            Route::delete('{uuid}/widgets/{widget_uuid}',        [DashboardController::class, 'removeWidget'])->name('widgets.destroy');
        });

        // ----------------------------------------------------------------
        // Webhooks (shared)
        // ----------------------------------------------------------------
        Route::prefix('webhooks')->name('webhooks.')->group(function () {
            Route::get('/',             [WebhookController::class, 'index'])->name('index');
            Route::post('/',            [WebhookController::class, 'store'])->name('store');
            Route::get('{uuid}',        [WebhookController::class, 'show'])->name('show');
            Route::put('{uuid}',        [WebhookController::class, 'update'])->name('update');
            Route::delete('{uuid}',     [WebhookController::class, 'destroy'])->name('destroy');
            Route::post('{uuid}/test',  [WebhookController::class, 'test'])->name('test');
            Route::get('{uuid}/logs',   [WebhookController::class, 'logs'])->name('logs');
        });

        // ----------------------------------------------------------------
        // Terminals (shared)
        // ----------------------------------------------------------------
        Route::prefix('terminals')->name('terminals.')->group(function () {
            Route::get('locodes',           [TerminalController::class, 'locodes'])->name('locodes');
            Route::get('firms/{firms_code}',[TerminalController::class, 'byFirmsCode'])->name('by-firms');
        });

        // ----------------------------------------------------------------
        // Carrier Contracts (shared)
        // ----------------------------------------------------------------
        Route::prefix('carrier-contracts')->name('carrier-contracts.')->group(function () {
            Route::get('spot',              [CarrierContractController::class, 'spotContracts'])->name('spot');
            Route::get('custom',            [CarrierContractController::class, 'customContracts'])->name('custom');
            Route::get('carrier/{scac}',    [CarrierContractController::class, 'byCarrier'])->name('by-carrier');
            Route::post('/',                [CarrierContractController::class, 'store'])->name('store');
            Route::put('{uuid}',            [CarrierContractController::class, 'update'])->name('update');
            Route::delete('{uuid}',         [CarrierContractController::class, 'destroy'])->name('destroy');
        });

        // ----------------------------------------------------------------
        // Map (shared)
        // ----------------------------------------------------------------
        Route::prefix('map')->name('map.')->group(function () {
            Route::get('vessels',   [MapController::class, 'vessels'])->name('vessels');
            Route::get('containers',[MapController::class, 'containers'])->name('containers');
            Route::get('ports',     [MapController::class, 'ports'])->name('ports');
        });

        // ----------------------------------------------------------------
        // Calendar (shared)
        // ----------------------------------------------------------------
        Route::prefix('calendar')->name('calendar.')->group(function () {
            Route::get('events',    [CalendarController::class, 'events'])->name('events');
            Route::get('export',    [CalendarController::class, 'export'])->name('export');
        });

        // ----------------------------------------------------------------
        // Data Upload (shared)
        // ----------------------------------------------------------------
        Route::prefix('data-upload')->name('data-upload.')->group(function () {
            Route::post('/',                    [DataUploadController::class, 'upload'])->name('upload');
            Route::get('template/{type}',       [DataUploadController::class, 'template'])->name('template');
            Route::get('status/{uuid}',         [DataUploadController::class, 'status'])->name('status');
        });

        // ================================================================
        // SHIPPER-ONLY ROUTES (continued)
        // ================================================================
        Route::middleware(['ensure.role:shipper'])->group(function () {

        // ----------------------------------------------------------------
        // Ocean Invoices
        // ----------------------------------------------------------------
        Route::prefix('ocean-invoices')->name('ocean-invoices.')->group(function () {
            Route::get('ok-to-pay',         [OceanInvoiceController::class, 'okToPay'])->name('ok-to-pay');
            Route::get('paid',              [OceanInvoiceController::class, 'paid'])->name('paid');
            Route::post('filter',           [OceanInvoiceController::class, 'filter'])->name('filter');
            Route::get('/',                 [OceanInvoiceController::class, 'index'])->name('index');
            Route::post('/',                [OceanInvoiceController::class, 'store'])->name('store');
            Route::get('{uuid}',            [OceanInvoiceController::class, 'show'])->name('show');
            Route::put('{uuid}',            [OceanInvoiceController::class, 'update'])->name('update');
            Route::delete('{uuid}',         [OceanInvoiceController::class, 'destroy'])->name('destroy');
            Route::post('{uuid}/payments',  [OceanInvoiceController::class, 'addPayment'])->name('payments');
        });

        // ----------------------------------------------------------------
        // Purchase Orders
        // ----------------------------------------------------------------
        Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
            Route::post('filter',       [PurchaseOrderController::class, 'filter'])->name('filter');
            Route::get('/',             [PurchaseOrderController::class, 'index'])->name('index');
            Route::post('/',            [PurchaseOrderController::class, 'store'])->name('store');
            Route::get('{uuid}',        [PurchaseOrderController::class, 'show'])->name('show');
            Route::put('{uuid}',        [PurchaseOrderController::class, 'update'])->name('update');
            Route::delete('{uuid}',     [PurchaseOrderController::class, 'destroy'])->name('destroy');
            Route::get('{uuid}/items',  [PurchaseOrderController::class, 'items'])->name('items');
        });

        // ----------------------------------------------------------------
        // SKUs
        // ----------------------------------------------------------------
        Route::prefix('skus')->name('skus.')->group(function () {
            Route::post('filter',   [SKUController::class, 'filter'])->name('filter');
            Route::get('/',         [SKUController::class, 'index'])->name('index');
            Route::post('/',        [SKUController::class, 'store'])->name('store');
            Route::get('{uuid}',    [SKUController::class, 'show'])->name('show');
            Route::put('{uuid}',    [SKUController::class, 'update'])->name('update');
        });

        // ----------------------------------------------------------------
        // Factories
        // ----------------------------------------------------------------
        Route::prefix('factories')->name('factories.')->group(function () {
            Route::post('filter',   [FactoryController::class, 'filter'])->name('filter');
            Route::get('/',         [FactoryController::class, 'index'])->name('index');
            Route::post('/',        [FactoryController::class, 'store'])->name('store');
            Route::get('{uuid}',    [FactoryController::class, 'show'])->name('show');
            Route::put('{uuid}',    [FactoryController::class, 'update'])->name('update');
            Route::delete('{uuid}', [FactoryController::class, 'destroy'])->name('destroy');
        });

        // ----------------------------------------------------------------
        // Vendors
        // ----------------------------------------------------------------
        Route::prefix('vendors')->name('vendors.')->group(function () {
            Route::post('filter',   [VendorController::class, 'filter'])->name('filter');
            Route::get('/',         [VendorController::class, 'index'])->name('index');
            Route::post('/',        [VendorController::class, 'store'])->name('store');
            Route::get('{uuid}',    [VendorController::class, 'show'])->name('show');
            Route::put('{uuid}',    [VendorController::class, 'update'])->name('update');
            Route::delete('{uuid}', [VendorController::class, 'destroy'])->name('destroy');
        });

        // ----------------------------------------------------------------
        // Distribution Centers
        // ----------------------------------------------------------------
        Route::prefix('distribution-centers')->name('distribution-centers.')->group(function () {
            Route::post('filter',               [DistributionCenterController::class, 'filter'])->name('filter');
            Route::get('/',                     [DistributionCenterController::class, 'index'])->name('index');
            Route::post('/',                    [DistributionCenterController::class, 'store'])->name('store');
            Route::get('{uuid}',                [DistributionCenterController::class, 'show'])->name('show');
            Route::post('{uuid}/containers',    [DistributionCenterController::class, 'associateContainer'])->name('containers');
        });

        // ----------------------------------------------------------------
        // Custom Columns
        // ----------------------------------------------------------------
        Route::prefix('custom-columns')->name('custom-columns.')->group(function () {
            Route::get('/',         [CustomColumnController::class, 'index'])->name('index');
            Route::post('/',        [CustomColumnController::class, 'store'])->name('store');
            Route::get('{uuid}',    [CustomColumnController::class, 'show'])->name('show');
            Route::put('{uuid}',    [CustomColumnController::class, 'update'])->name('update');
            Route::delete('{uuid}', [CustomColumnController::class, 'destroy'])->name('destroy');
            Route::get('{entity_type}/{entity_id}/values',  [CustomColumnController::class, 'values'])->name('values');
            Route::post('{entity_type}/{entity_id}/values', [CustomColumnController::class, 'storeValue'])->name('values.store');
        });

        // ----------------------------------------------------------------
        // Reports
        // ----------------------------------------------------------------
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/',         [ReportController::class, 'index'])->name('index');
            Route::post('/',        [ReportController::class, 'store'])->name('store');
            Route::get('{uuid}',    [ReportController::class, 'show'])->name('show');
            Route::put('{uuid}',    [ReportController::class, 'update'])->name('update');
            Route::delete('{uuid}', [ReportController::class, 'destroy'])->name('destroy');
            Route::post('{uuid}/generate',                              [ReportController::class, 'generate'])->name('generate');
            Route::post('{uuid}/schedule',                              [ReportController::class, 'schedule'])->name('schedule.store');
            Route::put('{uuid}/schedule/{schedule_uuid}',               [ReportController::class, 'updateSchedule'])->name('schedule.update');
            Route::delete('{uuid}/schedule/{schedule_uuid}',            [ReportController::class, 'deleteSchedule'])->name('schedule.destroy');
        });

        // ----------------------------------------------------------------
        // Volume
        // ----------------------------------------------------------------
        Route::prefix('volume')->name('volume.')->group(function () {
            Route::get('customer',          [VolumeController::class, 'customerVolume'])->name('customer');
            Route::get('billed',            [VolumeController::class, 'billedContainers'])->name('billed');
            Route::post('savings',          [VolumeController::class, 'savingsCalculator'])->name('savings');
            Route::get('summary',           [VolumeController::class, 'summary'])->name('summary');
        });

        // ----------------------------------------------------------------
        // Transit Times
        // ----------------------------------------------------------------
        Route::prefix('transit-times')->name('transit-times.')->group(function () {
            Route::get('trends',    [TransitTimeController::class, 'trends'])->name('trends');
            Route::post('filter',   [TransitTimeController::class, 'filter'])->name('filter');
            Route::get('/',         [TransitTimeController::class, 'index'])->name('index');
        });

        // ----------------------------------------------------------------
        // CSV Uploads (Shipper)
        // ----------------------------------------------------------------
        Route::prefix('uploads')->name('uploads.')->group(function () {
            Route::post('containers',                   [CSVUploadController::class, 'uploadContainers'])->name('containers.upload');
            Route::get('containers/template',           [CSVUploadController::class, 'downloadTemplate'])->name('containers.template');
            Route::get('containers/mapping-fields',     [CSVUploadController::class, 'mappingFields'])->name('containers.mapping-fields');
            Route::get('{upload_id}/status',            [CSVUploadController::class, 'uploadStatus'])->name('status');
            Route::get('history',                       [CSVUploadController::class, 'uploadHistory'])->name('history');
        });

        }); // end ensure.role:shipper (continued)

        // ================================================================
        // DRAYAGE-ONLY ROUTES
        // ================================================================
        Route::middleware(['ensure.role:drayage'])->group(function () {

        // ----------------------------------------------------------------
        // Drayage Step Management (Drayage Carriers)
        // ----------------------------------------------------------------
        Route::prefix('drayage')->name('drayage.steps.')->group(function () {
            Route::get('{uuid}/steps',          [DrayageStepController::class, 'getSteps'])->name('steps');
            Route::post('{uuid}/advance-step',  [DrayageStepController::class, 'advanceStep'])->name('advance');
            Route::put('{uuid}/step',           [DrayageStepController::class, 'setStep'])->name('set');
            Route::post('{uuid}/pickup',        [DrayageStepController::class, 'markPickedUp'])->name('pickup');
            Route::post('{uuid}/delivered',     [DrayageStepController::class, 'markDelivered'])->name('delivered');
            Route::post('{uuid}/empty-return',  [DrayageStepController::class, 'markEmptyReturned'])->name('empty-return');
        });

        }); // end ensure.role:drayage

        // ----------------------------------------------------------------
        // Carrier Integrations Management
        // ----------------------------------------------------------------
        Route::prefix('carrier-integrations')->name('carrier-integrations.')->group(function () {
            Route::get('/',              [\App\Http\Controllers\Api\V1\Integration\CarrierIntegrationController::class, 'index'])->name('index');
            Route::post('/',             [\App\Http\Controllers\Api\V1\Integration\CarrierIntegrationController::class, 'store'])->name('store');
            Route::get('{scac}',         [\App\Http\Controllers\Api\V1\Integration\CarrierIntegrationController::class, 'show'])->name('show');
            Route::delete('{scac}',      [\App\Http\Controllers\Api\V1\Integration\CarrierIntegrationController::class, 'destroy'])->name('destroy');
            Route::post('{scac}/test',   [\App\Http\Controllers\Api\V1\Integration\CarrierIntegrationController::class, 'test'])->name('test');
            Route::post('{scac}/toggle', [\App\Http\Controllers\Api\V1\Integration\CarrierIntegrationController::class, 'toggle'])->name('toggle');
        });

        // ----------------------------------------------------------------
        // n8n Workflow Automation
        // ----------------------------------------------------------------
        Route::prefix('n8n')->group(function () {
            Route::get('/',                         [\App\Http\Controllers\Api\V1\Integration\N8nIntegrationController::class, 'status']);
            Route::post('/connect',                 [\App\Http\Controllers\Api\V1\Integration\N8nIntegrationController::class, 'connect']);
            Route::post('/disconnect',              [\App\Http\Controllers\Api\V1\Integration\N8nIntegrationController::class, 'disconnect']);
            Route::post('/health',                  [\App\Http\Controllers\Api\V1\Integration\N8nIntegrationController::class, 'health']);
            Route::get('/workflows',                [\App\Http\Controllers\Api\V1\Integration\N8nIntegrationController::class, 'workflows']);
            Route::post('/workflows/sync',          [\App\Http\Controllers\Api\V1\Integration\N8nIntegrationController::class, 'syncWorkflows']);
            Route::get('/templates',                [\App\Http\Controllers\Api\V1\Integration\N8nIntegrationController::class, 'templates']);
            Route::post('/templates/{key}/deploy',  [\App\Http\Controllers\Api\V1\Integration\N8nIntegrationController::class, 'deployTemplate']);
            Route::put('/workflow-mappings/{id}',   [\App\Http\Controllers\Api\V1\Integration\N8nIntegrationController::class, 'updateMapping']);
            Route::delete('/workflow-mappings/{id}',[\App\Http\Controllers\Api\V1\Integration\N8nIntegrationController::class, 'deleteMapping']);
            Route::get('/executions',               [\App\Http\Controllers\Api\V1\Integration\N8nIntegrationController::class, 'executions']);
        });

        // ----------------------------------------------------------------
        // QuickBooks Online Integration
        // ----------------------------------------------------------------
        Route::prefix('integrations/quickbooks')->name('integrations.quickbooks.')->group(function () {
            Route::get('/',          [QuickBooksIntegrationController::class, 'status'])->name('status');
            Route::get('connect',    [QuickBooksIntegrationController::class, 'connect'])->name('connect');
            Route::post('disconnect',[QuickBooksIntegrationController::class, 'disconnect'])->name('disconnect');
            // invoice sync endpoints (controller built by a sibling agent — declare the routes now):
            Route::post('invoices/ocean/{uuid}/push',          [\App\Http\Controllers\Api\V1\Integration\QuickBooksInvoiceController::class, 'pushOcean'])->name('invoices.ocean.push');
            Route::post('invoices/ocean/{uuid}/sync-status',   [\App\Http\Controllers\Api\V1\Integration\QuickBooksInvoiceController::class, 'syncOcean'])->name('invoices.ocean.sync');
            Route::post('invoices/drayage/{uuid}/push',        [\App\Http\Controllers\Api\V1\Integration\QuickBooksInvoiceController::class, 'pushDrayage'])->name('invoices.drayage.push');
            Route::post('invoices/drayage/{uuid}/sync-status', [\App\Http\Controllers\Api\V1\Integration\QuickBooksInvoiceController::class, 'syncDrayage'])->name('invoices.drayage.sync');
        });

        // ----------------------------------------------------------------
        // TruckHub Integration
        // ----------------------------------------------------------------
        Route::prefix('integrations/truckhub')->name('integrations.truckhub.')->group(function () {
            Route::get('containers',                                [TruckHubIntegrationController::class, 'listContainers'])->name('containers');
            Route::post('containers/{uuid}/status',                 [TruckHubIntegrationController::class, 'updateContainerStatus'])->name('containers.status');
            Route::get('drayage-orders',                            [TruckHubIntegrationController::class, 'listDrayageOrders'])->name('drayage-orders');
            Route::post('drayage-orders/{uuid}/accept',             [TruckHubIntegrationController::class, 'acceptDrayageOrder'])->name('drayage-orders.accept');
            Route::post('drayage-orders/{uuid}/update',             [TruckHubIntegrationController::class, 'updateDrayageStep'])->name('drayage-orders.update');
            Route::get('webhook-config',                            [TruckHubIntegrationController::class, 'webhookConfig'])->name('webhook-config');
        });

        // ----------------------------------------------------------------
        // Datalastic AIS Vessel Tracking (global vessel map)
        // ----------------------------------------------------------------
        Route::prefix('ais')->name('ais.')->group(function () {
            Route::get('status',  [AisController::class, 'status'])->name('status');
            Route::get('vessels', [AisController::class, 'vessels'])->name('vessels');
            Route::get('vessel',  [AisController::class, 'vessel'])->name('vessel');
        });

        // ----------------------------------------------------------------
        // Multi-Source Container Tracking (JSONCargo → DCSA fallback chain)
        // ----------------------------------------------------------------
        Route::prefix('tracking')->name('tracking.')->group(function () {
            Route::get('container/{number}', [MultiSourceTrackingController::class, 'track'])->name('container');
            Route::get('sources',            [MultiSourceTrackingController::class, 'sources'])->name('sources');
        });

        // ----------------------------------------------------------------
        // JSONCargo Container & Vessel Tracking API (shared)
        // ----------------------------------------------------------------
        Route::prefix('jsoncargo')->name('jsoncargo.')->group(function () {
            // Meta
            Route::get('status',                [\App\Http\Controllers\Api\V1\JsonCargo\JsonCargoController::class, 'status'])->name('status');
            Route::get('stats',                 [\App\Http\Controllers\Api\V1\JsonCargo\JsonCargoController::class, 'apiKeyStats'])->name('stats');

            // Container tracking
            Route::post('containers/batch',     [\App\Http\Controllers\Api\V1\JsonCargo\JsonCargoController::class, 'containerBatch'])->name('containers.batch');
            Route::get('containers/bol/{bol}',  [\App\Http\Controllers\Api\V1\JsonCargo\JsonCargoController::class, 'containersByBol'])->name('containers.bol');
            Route::get('containers/{tracking}', [\App\Http\Controllers\Api\V1\JsonCargo\JsonCargoController::class, 'containerDetails'])->name('containers.show');
            Route::post('containers/{tracking}/refresh', [\App\Http\Controllers\Api\V1\JsonCargo\JsonCargoController::class, 'refreshContainer'])->name('containers.refresh');

            // Vessel tracking
            Route::get('vessels/basic',         [\App\Http\Controllers\Api\V1\JsonCargo\JsonCargoController::class, 'vesselBasic'])->name('vessels.basic');
            Route::get('vessels/pro',           [\App\Http\Controllers\Api\V1\JsonCargo\JsonCargoController::class, 'vesselPro'])->name('vessels.pro');
            Route::get('vessels/bulk',          [\App\Http\Controllers\Api\V1\JsonCargo\JsonCargoController::class, 'vesselBulk'])->name('vessels.bulk');
            Route::get('vessels/find',          [\App\Http\Controllers\Api\V1\JsonCargo\JsonCargoController::class, 'vesselFinder'])->name('vessels.find');
            Route::get('vessels/specs',         [\App\Http\Controllers\Api\V1\JsonCargo\JsonCargoController::class, 'vesselSpecs'])->name('vessels.specs');

            // Port & Terminal
            Route::get('ports/find',            [\App\Http\Controllers\Api\V1\JsonCargo\JsonCargoController::class, 'portFinder'])->name('ports.find');
            Route::get('terminals/find',        [\App\Http\Controllers\Api\V1\JsonCargo\JsonCargoController::class, 'terminalFinder'])->name('terminals.find');
        });

        // ----------------------------------------------------------------
        // Document Extraction (OCR via Anthropic Claude)
        // ----------------------------------------------------------------
        Route::prefix('documents')->name('documents.')->group(function () {
            Route::get('extract/status', [DocumentExtractionController::class, 'status'])->name('extract.status');
            Route::post('extract',       [DocumentExtractionController::class, 'extract'])->name('extract');
        });

        // ----------------------------------------------------------------
        // Carrier Onboarding (Shipper manages invites & carrier connections)
        // ----------------------------------------------------------------
        Route::prefix('carrier-onboarding')->name('carrier-onboarding.')->group(function () {
            Route::get('carriers',          [\App\Http\Controllers\Api\V1\Carrier\CarrierOnboardingController::class, 'carriers'])->name('carriers.index');
            Route::get('carriers/{uuid}',   [\App\Http\Controllers\Api\V1\Carrier\CarrierOnboardingController::class, 'showCarrier'])->name('carriers.show');
            Route::get('invites',           [\App\Http\Controllers\Api\V1\Carrier\CarrierOnboardingController::class, 'invites'])->name('invites.index');
            Route::post('invites',          [\App\Http\Controllers\Api\V1\Carrier\CarrierOnboardingController::class, 'createInvite'])->name('invites.store');
            Route::delete('invites/{uuid}', [\App\Http\Controllers\Api\V1\Carrier\CarrierOnboardingController::class, 'revokeInvite'])->name('invites.revoke');
        });
    });

// ----------------------------------------------------------------
// EDI Webhooks (outside auth:api — authenticated via X-EDI-Key header)
// The test and sample endpoints sit inside the auth:api group above,
// but the inbound webhook from EDI providers uses a shared secret key.
// ----------------------------------------------------------------
Route::middleware(['throttle:api'])
    ->prefix('edi')
    ->name('api.v1.edi.')
    ->group(function () {
        // Inbound EDI 315 from carriers/EDI VAN — no auth:api, uses X-EDI-Key
        Route::post('315', [EdiWebhookController::class, 'receive315'])
            ->name('315.receive');

        // Test and sample endpoints — require auth:api
        Route::middleware(['auth:api'])->group(function () {
            Route::post('315/test',   [EdiWebhookController::class, 'test315'])->name('315.test');
            Route::get('315/sample',  [EdiWebhookController::class, 'sample315'])->name('315.sample');
        });
    });
