<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Landmark extends Model
{
    /** marker_type => [label, colour] for the quick-add points of interest on /map. */
    public const POI_TYPES = [
        'ert_pickup' => ['ERT pickup point', '#2563eb'],
        'aed' => ['AED', '#dc2626'],
        'first_aid' => ['First aid kit', '#16a34a'],
        'lv_rescue' => ['LV rescue kit', '#eab308'],
        'muster_point' => ['Muster point', '#22c55e'],
        'obstruction' => ['Obstruction', '#f97316'],
        'hydration' => ['Hydration station', '#0284c7'],
        'gas_detector' => ['Gas detector', '#14b8a6'],
        'works' => ['Works', '#a855f7'],
        'risk' => ['Risk / hazard', '#ef4444'],
    ];

    /** Every marker_type the settings form and map renderer understand. */
    public const MARKER_TYPES = [
        'landmark' => 'Landmark',
        'road_label' => 'Road label',
        'emergency' => 'Emergency marker',
        'custom' => 'Custom marker',
        'ert_pickup' => 'ERT pickup point',
        'aed' => 'AED',
        'first_aid' => 'First aid kit',
        'lv_rescue' => 'LV rescue kit',
        'muster_point' => 'Muster point',
        'obstruction' => 'Obstruction',
        'hydration' => 'Hydration station',
        'gas_detector' => 'Gas detector',
        'works' => 'Works',
        'risk' => 'Risk / hazard',
    ];

    protected $guarded = [];

    protected $casts = [
        'easting' => 'float',
        'northing' => 'float',
        'active' => 'bool',
        'label_visible' => 'bool',
        'rotation_deg' => 'int',
    ];
}
