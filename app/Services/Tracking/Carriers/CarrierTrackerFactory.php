<?php

namespace App\Services\Tracking\Carriers;

use App\Services\Tracking\Carriers\Implementations\CMACGMTracker;
use App\Services\Tracking\Carriers\Implementations\COSCOTracker;
use App\Services\Tracking\Carriers\Implementations\EvergreenTracker;
use App\Services\Tracking\Carriers\Implementations\GenericTracker;
use App\Services\Tracking\Carriers\Implementations\HapagLloydTracker;
use App\Services\Tracking\Carriers\Implementations\HMMTracker;
use App\Services\Tracking\Carriers\Implementations\MaerskTracker;
use App\Services\Tracking\Carriers\Implementations\MSCTracker;
use App\Services\Tracking\Carriers\Implementations\ONETracker;
use App\Services\Tracking\Carriers\Implementations\YangMingTracker;
use App\Services\Tracking\Carriers\Implementations\ZIMTracker;

class CarrierTrackerFactory
{
    /**
     * Primary SCAC → tracker class map.
     * Aliases are resolved via CarrierRegistry::resolveScac() before lookup.
     */
    private const TRACKER_MAP = [
        // Maersk group
        'MAEU' => MaerskTracker::class,
        'SUDU' => MaerskTracker::class,
        'SAFI' => MaerskTracker::class,
        'CCNI' => MaerskTracker::class,

        // MSC group
        'MSCU' => MSCTracker::class,

        // CMA CGM group
        'CMDU' => CMACGMTracker::class,

        // COSCO group
        'COSU' => COSCOTracker::class,
        'CLHU' => COSCOTracker::class,

        // Evergreen group
        'EGLV' => EvergreenTracker::class,

        // Hapag-Lloyd group
        'HLCU' => HapagLloydTracker::class,

        // ONE group
        'ONEY' => ONETracker::class,
        'KKLU' => ONETracker::class,
        'NYKU' => ONETracker::class,
        'MOLU' => ONETracker::class,

        // Yang Ming
        'YMLU' => YangMingTracker::class,

        // ZIM
        'ZIMU' => ZIMTracker::class,

        // HMM
        'HDMU' => HMMTracker::class,
    ];

    /**
     * Resolve SCAC to the correct tracker implementation.
     * Aliases are transparently resolved to their primary SCAC before lookup.
     */
    public function make(string $scac): CarrierTrackingInterface
    {
        $resolvedScac = CarrierRegistry::resolveScac($scac);

        $trackerClass = self::TRACKER_MAP[$resolvedScac] ?? null;

        if ($trackerClass !== null) {
            return new $trackerClass();
        }

        // Fall back to generic tracker for any unknown or unsupported SCAC
        return new GenericTracker($resolvedScac);
    }

    /**
     * Returns true if a dedicated (non-generic) tracker exists for this SCAC.
     */
    public function supports(string $scac): bool
    {
        $resolvedScac = CarrierRegistry::resolveScac($scac);

        return isset(self::TRACKER_MAP[$resolvedScac]);
    }

    /**
     * Return an array of all SCACs that have a dedicated tracker implementation.
     */
    public function supportedScacs(): array
    {
        return array_keys(self::TRACKER_MAP);
    }
}
