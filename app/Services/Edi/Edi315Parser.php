<?php

namespace App\Services\Edi;

class Edi315Parser
{
    /**
     * Human-readable descriptions for every B407 / SG01 / V901 status code.
     */
    public const STATUS_CODES = [
        'AL' => 'Loaded on vessel',
        'AE' => 'Available empty',
        'AF' => 'Available full',
        'AV' => 'Available for pickup',
        'CD' => 'Customs released',
        'CT' => 'Customs hold',
        'DL' => 'Delivered',
        'DS' => 'Discharged from vessel',
        'GI' => 'Gate in (empty return)',
        'GO' => 'Gate out',
        'I'  => 'In-bond',
        'OA' => 'On-hand at terminal',
        'RL' => 'Rail departure',
        'RR' => 'Rail arrival at ramp',
        'TR' => 'In transit',
        'UV' => 'Unloaded from vessel',
        'VD' => 'Vessel departure',
        'VA' => 'Vessel arrival',
    ];

    /**
     * Status codes that represent a rail movement event.
     */
    public function isRailEvent(string $statusCode): bool
    {
        return in_array(strtoupper($statusCode), ['RL', 'RR', 'I'], true);
    }

    /**
     * Parse raw EDI 315 X12 text into a structured array.
     *
     * @param  string  $rawEdi
     * @return array
     */
    public function parse(string $rawEdi): array
    {
        $rawEdi = trim($rawEdi);

        // Detect delimiters from the ISA envelope (ISA is always 106 chars)
        $elementSeparator  = '*';
        $segmentTerminator = '~';

        if (strlen($rawEdi) >= 106) {
            $elementSeparator  = $rawEdi[3];
            $segmentTerminator = $rawEdi[105];
        }

        // Split into segments, stripping whitespace around the terminator
        $segments = array_filter(
            array_map(
                'trim',
                explode($segmentTerminator, $rawEdi)
            )
        );
        $segments = array_values($segments);

        $result = [
            'transaction_id'      => null,
            'container_number'    => null,
            'equipment_type'      => null,
            'equipment_status'    => null,
            'status_code'         => null,
            'status_description'  => null,
            'status_date'         => null,
            'status_time'         => null,
            'bill_of_lading'      => null,
            'vessel_name'         => null,
            'vessel_imo'          => null,
            'departure_date'      => null,
            'arrival_date'        => null,
            'carrier_scac'        => null,
            'ports'               => [],
            'events'              => [],
            'references'          => [],
            'sender'              => null,
            'receiver'            => null,
            'raw_segments'        => $segments,
        ];

        // Track the last R4 port entry so we can attach the following DTM to it
        $lastPortIndex = null;

        foreach ($segments as $segment) {
            $elements = explode($elementSeparator, $segment);
            $tag      = strtoupper(trim($elements[0] ?? ''));

            switch ($tag) {
                case 'ISA':
                    $result['sender']   = isset($elements[6]) ? trim($elements[6]) : null;
                    $result['receiver'] = isset($elements[8]) ? trim($elements[8]) : null;
                    break;

                case 'ST':
                    // ST*315*transaction_id
                    $result['transaction_id'] = $elements[2] ?? null;
                    break;

                case 'B4':
                    // B4*special_handling*inquiry*container_number*equipment_status*date*time*status_code**equipment_type
                    $result['container_number'] = isset($elements[3]) ? trim($elements[3]) : null;
                    $result['equipment_status'] = isset($elements[4]) ? trim($elements[4]) : null;
                    $result['status_date']       = $this->parseDate($elements[5] ?? null);
                    $result['status_time']       = $this->parseTime($elements[6] ?? null);
                    $statusCode                  = isset($elements[7]) ? trim($elements[7]) : null;
                    $result['status_code']       = $statusCode ?: null;
                    $result['status_description'] = $statusCode
                        ? (self::STATUS_CODES[$statusCode] ?? $statusCode)
                        : null;
                    $result['equipment_type']    = isset($elements[9]) ? trim($elements[9]) : null;
                    break;

                case 'N9':
                    // N9*qualifier*reference_value
                    $qualifier = isset($elements[1]) ? trim($elements[1]) : null;
                    $value     = isset($elements[2]) ? trim($elements[2]) : null;

                    if ($qualifier && $value) {
                        $result['references'][$qualifier] = $value;

                        if ($qualifier === 'BM') {
                            $result['bill_of_lading'] = $value;
                        } elseif ($qualifier === 'VN') {
                            $result['vessel_name'] = $value;
                        }
                    }
                    break;

                case 'Q2':
                    // Q2*vessel_id**vessel_name*****departure_date*arrival_date
                    $result['vessel_imo']      = isset($elements[1]) && trim($elements[1]) !== '' ? trim($elements[1]) : null;
                    $result['vessel_name']     = $result['vessel_name'] ?? (isset($elements[3]) ? trim($elements[3]) : null);
                    $result['departure_date']  = $this->parseDate($elements[8] ?? null);
                    $result['arrival_date']    = $this->parseDate($elements[9] ?? null);
                    break;

                case 'SG':
                    // SG*status_code*date*time — overrides B4 primary status when present
                    $sgCode = isset($elements[1]) ? trim($elements[1]) : null;
                    if ($sgCode) {
                        $result['status_code']        = $sgCode;
                        $result['status_description'] = self::STATUS_CODES[$sgCode] ?? $sgCode;
                        $result['status_date']        = $this->parseDate($elements[2] ?? null) ?? $result['status_date'];
                        $result['status_time']        = $this->parseTime($elements[3] ?? null) ?? $result['status_time'];
                    }
                    break;

                case 'R4':
                    // R4*qualifier*location_qualifier*location_code*location_name
                    $port = [
                        'qualifier' => isset($elements[1]) ? trim($elements[1]) : null,
                        'code'      => isset($elements[3]) ? trim($elements[3]) : null,
                        'name'      => isset($elements[4]) ? trim($elements[4]) : null,
                        'date'      => null,
                        'time'      => null,
                    ];
                    $result['ports'][] = $port;
                    $lastPortIndex = array_key_last($result['ports']);
                    break;

                case 'DTM':
                    // DTM*qualifier*date*time
                    $date = $this->parseDate($elements[2] ?? null);
                    $time = $this->parseTime($elements[3] ?? null);

                    // Attach date/time to the most recently parsed R4 port
                    if ($lastPortIndex !== null && isset($result['ports'][$lastPortIndex])) {
                        $result['ports'][$lastPortIndex]['date'] = $date;
                        $result['ports'][$lastPortIndex]['time'] = $time;
                    }
                    break;

                case 'V9':
                    // V9*event_code*date*time**carrier_scac*description
                    $eventCode = isset($elements[1]) ? trim($elements[1]) : null;
                    if ($eventCode) {
                        $carrierScac = isset($elements[5]) ? trim($elements[5]) : null;
                        $event = [
                            'code'        => $eventCode,
                            'date'        => $this->parseDate($elements[2] ?? null),
                            'time'        => $this->parseTime($elements[3] ?? null),
                            'carrier'     => $carrierScac ?: null,
                            'description' => isset($elements[6]) ? trim($elements[6]) : (self::STATUS_CODES[$eventCode] ?? $eventCode),
                        ];
                        $result['events'][] = $event;

                        // First V9 carrier wins for the top-level carrier_scac
                        if ($carrierScac && $result['carrier_scac'] === null) {
                            $result['carrier_scac'] = $carrierScac;
                        }
                    }
                    break;
            }
        }

        return $result;
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Convert YYYYMMDD to Y-m-d, returning null for blank/invalid values.
     */
    private function parseDate(?string $raw): ?string
    {
        if (! $raw || trim($raw) === '') {
            return null;
        }
        $raw = trim($raw);
        if (strlen($raw) === 8 && ctype_digit($raw)) {
            return substr($raw, 0, 4) . '-' . substr($raw, 4, 2) . '-' . substr($raw, 6, 2);
        }
        return null;
    }

    /**
     * Convert HHMM to H:i, returning null for blank/invalid values.
     */
    private function parseTime(?string $raw): ?string
    {
        if (! $raw || trim($raw) === '') {
            return null;
        }
        $raw = trim($raw);
        if (strlen($raw) === 4 && ctype_digit($raw)) {
            return substr($raw, 0, 2) . ':' . substr($raw, 2, 2);
        }
        // Handle HHMMSS (6 digits)
        if (strlen($raw) === 6 && ctype_digit($raw)) {
            return substr($raw, 0, 2) . ':' . substr($raw, 2, 2);
        }
        return null;
    }
}
