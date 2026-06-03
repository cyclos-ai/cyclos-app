<?php

declare(strict_types=1);

namespace App\Services\Tracking\Contracts;

interface ContainerTrackingProvider
{
    /**
     * Machine-readable provider identifier.
     * Examples: 'jsoncargo', 'maersk', 'cma_cgm', 'hapag', 'one', 'zim'
     */
    public function name(): string;

    /**
     * Returns true when credentials for this provider are present in config.
     */
    public function isConfigured(): bool;

    /**
     * Returns true when this provider can handle the given carrier SCAC.
     * A null SCAC means "unknown" — generic aggregator providers should return true.
     */
    public function supports(?string $carrierScac): bool;

    /**
     * Fetch tracking data and return a normalized result array, or null on
     * any unrecoverable failure (timeout, 5xx, network error, etc.).
     *
     * Normalized result shape:
     * {
     *   container_id, container_type?, container_status,
     *   shipping_line_name, shipping_line_id?,
     *   shipped_from, shipped_to,
     *   loading_port, discharging_port,
     *   atd_origin, eta_final_destination,
     *   last_location, next_location,
     *   last_vessel_name, current_vessel_name, current_voyage_number,
     *   bill_of_lading,
     *   last_updated,
     *   source,       <- provider name string
     *   events: [     <- raw DCSA events or empty array
     *     { eventType, eventTypeCode, classifier, eventDateTime, location, ... }
     *   ]
     * }
     *
     * @param  string      $containerNumber  ISO 11 char container number (e.g. ZCSU7244544)
     * @param  string|null $carrierScac      SCAC code (e.g. 'ZIMU'), optional
     * @param  string|null $bol              Bill of Lading number, optional
     * @return array|null  Normalized data array or null when no data / error
     */
    public function track(string $containerNumber, ?string $carrierScac = null, ?string $bol = null): ?array;
}
