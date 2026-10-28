<?php

namespace Modules\Sviat\NovaPoshtaTracking;

use Okay\Core\EntityFactory;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCostDeliveryDataEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Extenders\BackendExtender;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\NovaPoshtaPaymentHelper;
use Okay\Modules\Sviat\NovaPoshtaTracking\Services\NovaPoshtaDocumentService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Скільки накладна бере з отримувача.
 *
 * Оголошена цінність — уся сума замовлення, а контроль оплати чи грошовий переказ —
 * лише те, що ще не внесено (аванс віднімає Checkbox розширенням amountDueOnPickup).
 */
class AmountDueOnPickupTest extends TestCase
{
    /** Накладка з увімкненим контролем оплати раніше не брала грошей узагалі. */
    public function testControlPaymentReplacesRedeliveryForCashOnDelivery(): void
    {
        $request = $this->buildRequest(['control_payment' => 1, 'amount_due' => 1255.0], (object) ['redelivery' => 1]);

        self::assertSame(1255.0, $request['methodProperties']['AfterpaymentOnGoodsCost']);
        self::assertArrayNotHasKey('BackwardDeliveryData', $request['methodProperties']);
    }

    public function testRedeliveryCollectsAmountDueWhenControlIsOff(): void
    {
        $request = $this->buildRequest(['control_payment' => 0, 'amount_due' => 1255.0], (object) ['redelivery' => 1]);

        self::assertSame(1255.0, $request['methodProperties']['BackwardDeliveryData'][0]['RedeliveryString']);
        self::assertArrayNotHasKey('AfterpaymentOnGoodsCost', $request['methodProperties']);
    }

    public function testNothingIsCollectedWhenAdvanceCoversOrder(): void
    {
        foreach ([1, 0] as $controlPayment) {
            $request = $this->buildRequest(['control_payment' => $controlPayment, 'amount_due' => 0.0], (object) ['redelivery' => 1]);

            self::assertSame([], $request['methodProperties'], "control_payment={$controlPayment}");
        }
    }

    public function testWithoutExtensionsWholeOrderIsDue(): void
    {
        $helper = new NovaPoshtaPaymentHelper();

        self::assertSame(2455.0, $helper->amountDueOnPickup((object) ['id' => 1], 2455.0));
        self::assertSame(0.0, $helper->amountDueOnPickup((object) ['id' => 1], -5.0));
    }

    /**
     * Форма оплаченого замовлення показує контроль вимкненим. Записаний нуль лишився б
     * і після зняття позначки «сплачено», і накладка поїхала б без збору грошей.
     */
    public function testSavingPaidOrderKeepsStoredControlPayment(): void
    {
        self::assertArrayNotHasKey('control_payment', $this->savedDeliveryData(paid: 1, postedControl: '0'));
    }

    /** Контроль: для неоплаченого замовлення вибір менеджера, як і раніше, зберігається. */
    public function testSavingUnpaidOrderStoresControlPayment(): void
    {
        self::assertSame(1, $this->savedDeliveryData(paid: 0, postedControl: '1')['control_payment']);
    }

    private function buildRequest(array $formData, object $deliveryData): array
    {
        $service = new ReflectionClass(NovaPoshtaDocumentService::class)->newInstanceWithoutConstructor();
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturn('Recipient');
        $this->inject($service, 'settings', $settings);

        $apiRequest = ['methodProperties' => []];
        $this->call($service, 'addPaymentControlToRequest', [&$apiRequest, $formData]);
        $this->call($service, 'addBackwardDeliveryToRequest', [&$apiRequest, $formData, $deliveryData]);

        return $apiRequest;
    }

    private function savedDeliveryData(int $paid, string $postedControl): array
    {
        $entity = $this->createStub(NPCostDeliveryDataEntity::class);
        $entity->method('findOne')->willReturn((object) ['id' => 7]);
        $saved = [];
        $entity->method('update')->willReturnCallback(function ($id, $data) use (&$saved) {
            $saved[] = (array) $data;
            return true;
        });
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturn($entity);

        $extender = new ReflectionClass(BackendExtender::class)->newInstanceWithoutConstructor();
        $this->inject($extender, 'entityFactory', $factory);
        // Request не мокається: у нього є метод method(). post() читає лише $_POST.
        $this->inject($extender, 'request', new ReflectionClass(Request::class)->newInstanceWithoutConstructor());

        $previousPost = $_POST;
        $_POST = ['control_payment' => $postedControl];
        try {
            $extender->postOrder((object) ['id' => 42, 'paid' => $paid]);
        } finally {
            $_POST = $previousPost;
        }

        return end($saved);
    }

    private function call(object $object, string $method, array $args): void
    {
        $reflection = new ReflectionMethod($object, $method);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }
        $reflection->invokeArgs($object, $args);
    }

    private function inject(object $object, string $property, $value): void
    {
        $reflection = new ReflectionProperty($object, $property);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }
        $reflection->setValue($object, $value);
    }
}
