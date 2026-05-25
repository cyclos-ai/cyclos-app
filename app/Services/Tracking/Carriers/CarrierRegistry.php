<?php

namespace App\Services\Tracking\Carriers;

class CarrierRegistry
{
    private static array $carriers = [
        // ----------------------------------------------------------------
        // Major Ocean Carriers
        // ----------------------------------------------------------------
        'MAEU' => [
            'name'         => 'Maersk',
            'group'        => 'Maersk',
            'api_type'     => 'rest',
            'tracking_url' => 'https://api.maersk.com/track',
            'website'      => 'https://www.maersk.com',
            'aliases'      => ['MAEI', 'MSKU', 'SEAU', 'SEJJ'],
        ],
        'MSCU' => [
            'name'         => 'MSC - Mediterranean Shipping Company',
            'group'        => 'MSC',
            'api_type'     => 'rest',
            'tracking_url' => 'https://www.msc.com/api/track',
            'website'      => 'https://www.msc.com',
            'aliases'      => ['MEDU'],
        ],
        'CMDU' => [
            'name'         => 'CMA CGM',
            'group'        => 'CMA CGM',
            'api_type'     => 'rest',
            'tracking_url' => 'https://api.cma-cgm.com/track',
            'website'      => 'https://www.cma-cgm.com',
            'aliases'      => ['ANNU', 'APLU'],
        ],
        'COSU' => [
            'name'         => 'COSCO Shipping',
            'group'        => 'COSCO',
            'api_type'     => 'rest',
            'tracking_url' => 'https://elines.coscoshipping.com/api/track',
            'website'      => 'https://www.coscoshipping.com',
            'aliases'      => ['CCLU', 'OOLU'],
        ],
        'EGLV' => [
            'name'         => 'Evergreen Marine',
            'group'        => 'Evergreen',
            'api_type'     => 'rest',
            'tracking_url' => 'https://www.evergreen-line.com/api/track',
            'website'      => 'https://www.evergreen-line.com',
            'aliases'      => ['EGHU'],
        ],
        'HLCU' => [
            'name'         => 'Hapag-Lloyd',
            'group'        => 'Hapag-Lloyd',
            'api_type'     => 'rest',
            'tracking_url' => 'https://api.hapag-lloyd.com/track',
            'website'      => 'https://www.hapag-lloyd.com',
            'aliases'      => ['HLXU'],
        ],
        'ONEY' => [
            'name'         => 'ONE - Ocean Network Express',
            'group'        => 'ONE',
            'api_type'     => 'rest',
            'tracking_url' => 'https://ecomm.one-line.com/api/track',
            'website'      => 'https://www.one-line.com',
            'aliases'      => ['ONEU'],
        ],
        'YMLU' => [
            'name'         => 'Yang Ming Marine',
            'group'        => 'Yang Ming',
            'api_type'     => 'rest',
            'tracking_url' => 'https://www.yangming.com/api/track',
            'website'      => 'https://www.yangming.com',
            'aliases'      => ['YMJA'],
        ],
        'ZIMU' => [
            'name'         => 'ZIM Integrated Shipping',
            'group'        => 'ZIM',
            'api_type'     => 'rest',
            'tracking_url' => 'https://www.zim.com/api/track',
            'website'      => 'https://www.zim.com',
            'aliases'      => ['ZLCU'],
        ],
        'HDMU' => [
            'name'         => 'HMM - Hyundai Merchant Marine',
            'group'        => 'HMM',
            'api_type'     => 'rest',
            'tracking_url' => 'https://www.hmm21.com/api/track',
            'website'      => 'https://www.hmm21.com',
            'aliases'      => [],
        ],
        'WHLC' => [
            'name'         => 'Wan Hai Lines',
            'group'        => 'Wan Hai',
            'api_type'     => 'rest',
            'tracking_url' => 'https://www.wanhai.com/api/track',
            'website'      => 'https://www.wanhai.com',
            'aliases'      => ['WHLU'],
        ],
        'PILU' => [
            'name'         => 'PIL - Pacific International Lines',
            'group'        => 'PIL',
            'api_type'     => 'rest',
            'tracking_url' => 'https://www.pilship.com/api/track',
            'website'      => 'https://www.pilship.com',
            'aliases'      => [],
        ],
        'KMTU' => [
            'name'         => 'SM Line',
            'group'        => 'SM Line',
            'api_type'     => 'rest',
            'tracking_url' => 'https://www.smlines.com/api/track',
            'website'      => 'https://www.smlines.com',
            'aliases'      => [],
        ],
        'SUDU' => [
            'name'         => 'Hamburg Süd (Maersk)',
            'group'        => 'Maersk',
            'api_type'     => 'rest',
            'tracking_url' => 'https://api.maersk.com/track',
            'website'      => 'https://www.hamburgsud.com',
            'aliases'      => [],
        ],
        'SAFI' => [
            'name'         => 'Safmarine (Maersk)',
            'group'        => 'Maersk',
            'api_type'     => 'rest',
            'tracking_url' => 'https://api.maersk.com/track',
            'website'      => 'https://www.safmarine.com',
            'aliases'      => [],
        ],
        'TRHU' => [
            'name'         => 'Turkon Line',
            'group'        => 'Turkon',
            'api_type'     => 'scrape',
            'tracking_url' => 'https://service.turkon.com/api/track',
            'website'      => 'https://www.turkon.com',
            'aliases'      => [],
        ],
        'CCNI' => [
            'name'         => 'CCNI (Hamburg Süd)',
            'group'        => 'Maersk',
            'api_type'     => 'rest',
            'tracking_url' => 'https://api.maersk.com/track',
            'website'      => 'https://www.ccni.cl',
            'aliases'      => [],
        ],
        'KKLU' => [
            'name'         => '"K" Line',
            'group'        => 'ONE',
            'api_type'     => 'rest',
            'tracking_url' => 'https://ecomm.one-line.com/api/track',
            'website'      => 'https://www.kline.com',
            'aliases'      => [],
        ],
        'NYKU' => [
            'name'         => 'NYK Line',
            'group'        => 'ONE',
            'api_type'     => 'rest',
            'tracking_url' => 'https://ecomm.one-line.com/api/track',
            'website'      => 'https://www.nyk.com',
            'aliases'      => [],
        ],
        'MOLU' => [
            'name'         => 'MOL - Mitsui O.S.K. Lines',
            'group'        => 'ONE',
            'api_type'     => 'rest',
            'tracking_url' => 'https://ecomm.one-line.com/api/track',
            'website'      => 'https://www.mol.co.jp',
            'aliases'      => [],
        ],
        // ----------------------------------------------------------------
        // Regional / Specialty Carriers
        // ----------------------------------------------------------------
        'ACLU' => [
            'name'         => 'Atlantic Container Line',
            'group'        => 'Grimaldi',
            'api_type'     => 'rest',
            'tracking_url' => null,
            'website'      => 'https://www.aclcargo.com',
            'aliases'      => [],
        ],
        'BAXU' => [
            'name'         => 'BAX Global',
            'group'        => 'BAX',
            'api_type'     => 'rest',
            'tracking_url' => null,
            'website'      => null,
            'aliases'      => [],
        ],
        'CLHU' => [
            'name'         => 'China Shipping',
            'group'        => 'COSCO',
            'api_type'     => 'rest',
            'tracking_url' => 'https://elines.coscoshipping.com/api/track',
            'website'      => null,
            'aliases'      => [],
        ],
        'EISU' => [
            'name'         => 'Emirates Shipping Line',
            'group'        => 'Emirates',
            'api_type'     => 'rest',
            'tracking_url' => null,
            'website'      => 'https://www.emiratesline.com',
            'aliases'      => [],
        ],
        'FESO' => [
            'name'         => 'FESCO Transportation Group',
            'group'        => 'FESCO',
            'api_type'     => 'rest',
            'tracking_url' => null,
            'website'      => 'https://www.fesco.ru',
            'aliases'      => [],
        ],
        'GCEU' => [
            'name'         => 'Gold Container Line',
            'group'        => 'Gold',
            'api_type'     => 'rest',
            'tracking_url' => null,
            'website'      => null,
            'aliases'      => [],
        ],
        'SIKU' => [
            'name'         => 'Sinokor Merchant Marine',
            'group'        => 'Sinokor',
            'api_type'     => 'rest',
            'tracking_url' => null,
            'website'      => 'https://www.sinokor.co.kr',
            'aliases'      => [],
        ],
        'ISCL' => [
            'name'         => 'Interasia Line',
            'group'        => 'Interasia',
            'api_type'     => 'rest',
            'tracking_url' => null,
            'website'      => 'https://www.interasia.cc',
            'aliases'      => [],
        ],
        'TGHU' => [
            'name'         => 'Tropical Shipping',
            'group'        => 'Tropical',
            'api_type'     => 'rest',
            'tracking_url' => null,
            'website'      => 'https://www.tropical.com',
            'aliases'      => [],
        ],
        'REGU' => [
            'name'         => 'Seaboard Marine',
            'group'        => 'Seaboard',
            'api_type'     => 'rest',
            'tracking_url' => null,
            'website'      => 'https://www.seaboardmarine.com',
            'aliases'      => [],
        ],
        'CPRU' => [
            'name'         => 'Crowley Maritime',
            'group'        => 'Crowley',
            'api_type'     => 'rest',
            'tracking_url' => null,
            'website'      => 'https://www.crowley.com',
            'aliases'      => [],
        ],
        'MATS' => [
            'name'         => 'Matson Navigation',
            'group'        => 'Matson',
            'api_type'     => 'rest',
            'tracking_url' => null,
            'website'      => 'https://www.matson.com',
            'aliases'      => [],
        ],
        'PABV' => [
            'name'         => 'Pan Asia Shipping',
            'group'        => 'Pan Asia',
            'api_type'     => 'rest',
            'tracking_url' => null,
            'website'      => null,
            'aliases'      => [],
        ],
        'UESU' => [
            'name'         => 'Unifeeder',
            'group'        => 'DP World',
            'api_type'     => 'rest',
            'tracking_url' => null,
            'website'      => 'https://www.unifeeder.com',
            'aliases'      => [],
        ],
        'XPRU' => [
            'name'         => 'X-Press Feeders',
            'group'        => 'X-Press',
            'api_type'     => 'rest',
            'tracking_url' => null,
            'website'      => 'https://www.x-pressfeeders.com',
            'aliases'      => [],
        ],
    ];

    /**
     * Container-number prefix → primary SCAC mapping.
     * ISO 6346: first 3 letters of container number identify the owner/operator.
     */
    private static array $containerPrefixMap = [
        'MAE' => 'MAEU',
        'MSC' => 'MSCU',
        'CMA' => 'CMDU',
        'CGM' => 'CMDU',
        'COS' => 'COSU',
        'EVG' => 'EGLV',
        'HLC' => 'HLCU',
        'ONE' => 'ONEY',
        'YML' => 'YMLU',
        'ZIM' => 'ZIMU',
        'HMM' => 'HDMU',
        'WAN' => 'WHLC',
        'PIL' => 'PILU',
        'HSD' => 'SUDU',
        'SAF' => 'SAFI',
        'TRK' => 'TRHU',
        'TGL' => 'TGHU',
        'SEA' => 'REGU',
        'CWL' => 'CPRU',
        'MAT' => 'MATS',
    ];

    // ----------------------------------------------------------------
    // Public API
    // ----------------------------------------------------------------

    public static function getCarrier(string $scac): ?array
    {
        $scac = strtoupper(trim($scac));

        return self::$carriers[$scac] ?? null;
    }

    /**
     * Given an alias/secondary SCAC code, return the primary SCAC.
     */
    public static function findByAlias(string $code): ?string
    {
        $code = strtoupper(trim($code));

        foreach (self::$carriers as $scac => $carrier) {
            if (in_array($code, $carrier['aliases'], true)) {
                return $scac;
            }
        }

        return null;
    }

    public static function getAllCarriers(): array
    {
        return self::$carriers;
    }

    /**
     * Only carriers that have a non-null tracking_url.
     */
    public static function getSupportedCarriers(): array
    {
        return array_filter(self::$carriers, static fn(array $c) => $c['tracking_url'] !== null);
    }

    /**
     * Returns unique carrier groups and their member SCACs.
     * e.g. ['Maersk' => ['MAEU', 'SUDU', 'SAFI', 'CCNI'], ...]
     */
    public static function getCarrierGroups(): array
    {
        $groups = [];

        foreach (self::$carriers as $scac => $carrier) {
            $groups[$carrier['group']][] = $scac;
        }

        ksort($groups);

        return $groups;
    }

    /**
     * Resolve an alias SCAC to its primary SCAC; returns the input unchanged
     * if it is already a primary SCAC or if it is unknown.
     */
    public static function resolveScac(string $code): string
    {
        $code = strtoupper(trim($code));

        // Already a primary SCAC
        if (isset(self::$carriers[$code])) {
            return $code;
        }

        // Try alias map
        return self::findByAlias($code) ?? $code;
    }

    /**
     * Attempt to detect carrier SCAC from an MBL/B/L number prefix (first 4 chars).
     */
    public static function detectFromMBL(string $mblNumber): ?string
    {
        $prefix = strtoupper(substr(trim($mblNumber), 0, 4));

        // Check primary SCACs
        if (isset(self::$carriers[$prefix])) {
            return $prefix;
        }

        // Check aliases
        $resolved = self::findByAlias($prefix);
        if ($resolved !== null) {
            return $resolved;
        }

        // Try first 3 chars in case the MBL starts with a 3-char carrier code
        $short = substr($prefix, 0, 3);
        if (isset(self::$carriers[$short])) {
            return $short;
        }

        return null;
    }

    /**
     * Attempt to detect carrier SCAC from an ISO 6346 container number prefix (first 3 letters).
     */
    public static function detectFromContainerPrefix(string $containerNumber): ?string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $containerNumber), 0, 3));

        return self::$containerPrefixMap[$prefix] ?? null;
    }

    /**
     * Full-text search across name, SCAC, group, and aliases.
     */
    public static function searchCarriers(string $query): array
    {
        $q = strtolower(trim($query));

        if ($q === '') {
            return [];
        }

        $results = [];

        foreach (self::$carriers as $scac => $carrier) {
            if (
                str_contains(strtolower($scac), $q)
                || str_contains(strtolower($carrier['name']), $q)
                || str_contains(strtolower($carrier['group']), $q)
                || !empty(array_filter($carrier['aliases'], static fn(string $a) => str_contains(strtolower($a), $q)))
            ) {
                $results[$scac] = $carrier;
            }
        }

        return $results;
    }
}
