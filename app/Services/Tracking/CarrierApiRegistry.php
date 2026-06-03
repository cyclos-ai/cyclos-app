<?php

declare(strict_types=1);

namespace App\Services\Tracking;

use App\Services\JsonCargo\JsonCargoService;

/**
 * Maps SCAC codes to DCSA carrier configurations loaded from config('services.carriers').
 *
 * Knows about SCAC aliases (e.g. MAEI/MRKU are also Maersk) and can infer
 * the likely carrier from a container prefix or a BOL prefix.
 */
class CarrierApiRegistry
{
    /**
     * SCAC → carriers config-key mapping.
     * Primary SCACs match the array key in config('services.carriers').
     * Aliases point to the same key.
     */
    private const SCAC_TO_CONFIG_KEY = [
        // Maersk
        'MAEU' => 'maersk',
        'MAEI' => 'maersk',
        'MRKU' => 'maersk',
        'MSKU' => 'maersk',
        // CMA CGM
        'CMDU' => 'cma_cgm',
        'CMAU' => 'cma_cgm',
        'ANNU' => 'cma_cgm',
        'APLU' => 'cma_cgm',
        // Hapag-Lloyd
        'HLCU' => 'hapag',
        'HLXU' => 'hapag',
        // ONE
        'ONEY' => 'one',
        'KKLU' => 'one',
        'NYKU' => 'one',
        'MOLU' => 'one',
        // ZIM
        'ZIMU' => 'zim',
        'ZCSU' => 'zim',
        'ZLCU' => 'zim',
        'JXLU' => 'zim',
        'TLLU' => 'zim',
        'CAAU' => 'zim',
    ];

    /**
     * Retrieve the DCSA carrier config block for the given SCAC, or null
     * if the SCAC is unknown or the carrier is not configured in services.php.
     *
     * @return array<string,mixed>|null
     */
    public function forScac(string $scac): ?array
    {
        $key = self::SCAC_TO_CONFIG_KEY[strtoupper($scac)] ?? null;

        if ($key === null) {
            return null;
        }

        $config = config("services.carriers.{$key}");

        if (! is_array($config)) {
            return null;
        }

        return $config;
    }

    /**
     * Try to infer the SCAC from the container number prefix (first 4 chars).
     * Returns the primary SCAC if found, null otherwise.
     */
    public function scacFromContainerPrefix(string $containerNumber): ?string
    {
        $prefix = strtoupper(substr($containerNumber, 0, 4));

        // Check our own SCAC map first
        if (isset(self::SCAC_TO_CONFIG_KEY[$prefix])) {
            return $prefix;
        }

        // Fall back to the full JSONCargo SCAC list (broader coverage)
        $line = JsonCargoService::SCAC_TO_SHIPPING_LINE[$prefix] ?? null;

        if ($line === null) {
            return null;
        }

        // Reverse-lookup: find the first SCAC alias that resolves to this line
        foreach (JsonCargoService::SCAC_TO_SHIPPING_LINE as $s => $l) {
            if ($l === $line && isset(self::SCAC_TO_CONFIG_KEY[$s])) {
                return $s;
            }
        }

        return $prefix;
    }

    /**
     * Try to infer the SCAC from a Bill of Lading prefix (first 4 chars).
     * Many carriers embed the SCAC in their BOL numbers.
     */
    public function scacFromBol(string $bol): ?string
    {
        $prefix = strtoupper(substr($bol, 0, 4));

        return isset(self::SCAC_TO_CONFIG_KEY[$prefix]) ? $prefix : null;
    }

    /**
     * Returns a list of all carriers and whether each one is configured.
     *
     * @return array<int, array{key: string, scac: string, name: string, configured: bool}>
     */
    public function allCarriers(): array
    {
        $carriers = config('services.carriers', []);
        $result   = [];

        foreach ($carriers as $key => $cfg) {
            if (! is_array($cfg)) {
                continue;
            }

            $configured = ! empty($cfg['base_url']);

            $auth = $cfg['auth'] ?? 'apikey';
            if ($auth === 'oauth2') {
                $configured = $configured
                    && ! empty($cfg['client_id'])
                    && ! empty($cfg['client_secret'])
                    && ! empty($cfg['token_url']);
            } else {
                $configured = $configured && ! empty($cfg['api_key']);
            }

            $result[] = [
                'key'        => $key,
                'scac'       => $cfg['scac'] ?? $key,
                'name'       => $cfg['name'] ?? $key,
                'configured' => $configured,
            ];
        }

        return $result;
    }
}
