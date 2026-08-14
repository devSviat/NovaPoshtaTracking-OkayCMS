<?php


namespace Okay\Modules\Sviat\NovaPoshtaTracking;

use Okay\Admin\Helpers\BackendOrdersHelper;
use Okay\Core\BackendTranslations;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Languages;
use Okay\Core\Money;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Core\Settings;
use Okay\Entities\CurrenciesEntity;
use Okay\Helpers\DiscountsHelper;
use Okay\Modules\Sviat\NovaPoshtaTracking\Extenders\BackendExtender;
use Okay\Modules\Sviat\NovaPoshtaTracking\Extenders\FrontExtender;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\NovaPoshtaApiHelper;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\NovaPoshtaStatusHelper;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\TrackingDocumentCronHelper;
use Okay\Modules\Sviat\NovaPoshtaTracking\Services\AdminIdentity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Services\NovaPoshtaDocumentService;
use Okay\Modules\Sviat\NovaPoshtaTracking\Services\SeparateSessionAdminIdentity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Services\SharedSessionAdminIdentity;

return [
    // Композиційний корінь: рушій визначається один раз, тут. Далі
    // контролери працюють з портом і про різницю не знають. За номером
    // версії рушії не розрізнити — обидва звуть себе 4.5.2.
    AdminIdentity::class => [
        'class' => class_exists('Okay\\Core\\Security\\SessionNames')
            ? SeparateSessionAdminIdentity::class
            : SharedSessionAdminIdentity::class,
        'arguments' => [],
    ],
    BackendExtender::class => [
        'class' => BackendExtender::class,
        'arguments' => [
            new SR(Settings::class),
            new SR(EntityFactory::class),
            new SR(Request::class),
            new SR(Design::class),
            new SR(NovaPoshtaApiHelper::class),
            new SR(DiscountsHelper::class),
            new SR(Response::class),
            new SR(BackendOrdersHelper::class),
            new SR(Languages::class),
            new SR(NovaPoshtaStatusHelper::class),
            new SR(NovaPoshtaDocumentService::class),
            new SR(BackendTranslations::class),
        ]
    ],
    FrontExtender::class => [
        'class' => FrontExtender::class,
        'arguments' => [
            new SR(Design::class),
            new SR(EntityFactory::class),
        ],
    ],
    NovaPoshtaStatusHelper::class => [
        'class' => NovaPoshtaStatusHelper::class,
        'arguments' => [
            // no arguments
        ],
    ],
    NovaPoshtaApiHelper::class => [
        'class' => NovaPoshtaApiHelper::class,
        'arguments' => [
            new SR(Settings::class),
        ],
    ],
    TrackingDocumentCronHelper::class => [
        'class' => TrackingDocumentCronHelper::class,
        'arguments' => [
            new SR(EntityFactory::class),
            new SR(Settings::class),
            new SR(NovaPoshtaApiHelper::class),
            new SR(NovaPoshtaDocumentService::class),
        ],
    ],
    NovaPoshtaDocumentService::class => [
        'class' => NovaPoshtaDocumentService::class,
        'arguments' => [
            new SR(EntityFactory::class),
            new SR(Settings::class),
            new SR(Request::class),
            new SR(NovaPoshtaApiHelper::class),
            new SR(BackendOrdersHelper::class),
            new SR(Money::class),
        ],
    ],
];
