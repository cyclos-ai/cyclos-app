<?php

return [
    'host'    => env('N8N_HOST', 'http://localhost:5678'),
    'api_key' => env('N8N_API_KEY'),
    'webhook_base_url' => env('N8N_WEBHOOK_BASE_URL', 'http://localhost:5678'),
    'enabled' => env('N8N_ENABLED', false),

    // Default workflow templates that can be deployed per tenant
    'templates' => [
        'container_arrival' => [
            'name' => 'Container Arrival Notification',
            'description' => 'Sends notifications when a container arrives at port',
            'trigger_event' => 'container.status.changed',
            'category' => 'tracking',
        ],
        'demurrage_alert' => [
            'name' => 'Demurrage Alert',
            'description' => 'Alerts when demurrage costs exceed threshold',
            'trigger_event' => 'demurrage.threshold.exceeded',
            'category' => 'finance',
        ],
        'vessel_eta_change' => [
            'name' => 'Vessel ETA Change',
            'description' => 'Notifies when a vessel ETA changes significantly',
            'trigger_event' => 'vessel.eta.updated',
            'category' => 'tracking',
        ],
        'invoice_created' => [
            'name' => 'Invoice Created',
            'description' => 'Triggers when a new invoice is created',
            'trigger_event' => 'invoice.created',
            'category' => 'finance',
        ],
        'lfd_approaching' => [
            'name' => 'LFD Approaching',
            'description' => 'Warns when Last Free Day is approaching',
            'trigger_event' => 'container.lfd.approaching',
            'category' => 'tracking',
        ],
    ],
];
