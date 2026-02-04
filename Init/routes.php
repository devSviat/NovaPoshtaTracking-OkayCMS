<?php

namespace Okay\Modules\Sviat\NovaPoshtaTracking;

return [
    'Sviat_NovaPoshtaTracking_generateDocument' => [
        'slug' => 'backend/nova-poshta/ajax/generateDocument',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\TrackingDocumentController',
            'method' => 'generateDocument',
        ],
    ],
    'Sviat_NovaPoshtaTracking_updateTrackingDocument' => [
        'slug' => 'backend/nova-poshta/ajax/updateTrackingDocument',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\TrackingDocumentController',
            'method' => 'updateTrackingDocument',
        ],
    ],
    'Sviat_NovaPoshtaTracking_removeDocument' => [
        'slug' => 'backend/nova-poshta/ajax/removeDocument',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\TrackingDocumentController',
            'method' => 'removeDocument',
        ],
    ],
];

