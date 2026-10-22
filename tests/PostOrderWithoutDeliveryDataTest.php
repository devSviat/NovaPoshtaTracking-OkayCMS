<?php

namespace Modules\Sviat\NovaPoshtaTracking;

use Okay\Core\EntityFactory;
use Okay\Core\Request;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCostDeliveryDataEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Extenders\BackendExtender;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionProperty;

/**
 * Хук на BackendOrdersRequest::postOrder спрацьовує ще до запису замовлення.
 * У нового замовлення немає ні id, ні рядка даних накладної: findOne() дає
 * false, читання ->id з нього — warning, а з показом помилок він виводиться до
 * заголовків і ламає редирект після збереження. Те саме — у наявного
 * замовлення без даних накладної (доставка не Новою Поштою).
 */
class PostOrderWithoutDeliveryDataTest extends TestCase
{
    /** @dataProvider orderProvider */
    #[DataProvider('orderProvider')]
    public function testNothingIsWrittenWhenThereIsNoDeliveryDataRow($orderId): void
    {
        $entity = $this->createMock(NPCostDeliveryDataEntity::class);
        $entity->method('findOne')->willReturn(false);
        $entity->expects($this->never())->method('update');

        $order = (object) ['id' => $orderId, 'paid' => 0];
        $warnings = [];
        set_error_handler(function ($no, $message) use (&$warnings) {
            $warnings[] = $message;
            return true;
        });
        try {
            $result = $this->extender($entity)->postOrder($order);
        } finally {
            restore_error_handler();
        }

        $this->assertSame($order, $result);
        $this->assertSame([], $warnings);
    }

    public static function orderProvider(): array
    {
        return [
            'нове замовлення'                   => [null],
            'наявне без даних накладної'        => [42],
        ];
    }

    /** Контроль: рядок є — дані накладної, як і раніше, записуються саме в нього. */
    public function testExistingDeliveryDataRowIsUpdated(): void
    {
        $entity = $this->createMock(NPCostDeliveryDataEntity::class);
        $entity->method('findOne')->willReturn((object) ['id' => 7]);
        $entity->expects($this->exactly(2))->method('update')->with(7, $this->anything());

        $this->extender($entity)->postOrder((object) ['id' => 42, 'paid' => 0]);
    }

    private function extender(NPCostDeliveryDataEntity $entity): BackendExtender
    {
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturn($entity);

        $extender = (new ReflectionClass(BackendExtender::class))->newInstanceWithoutConstructor();
        $this->inject($extender, 'entityFactory', $factory);
        // Request не мокається: у нього є метод method(). post() читає лише $_POST.
        $this->inject($extender, 'request', (new ReflectionClass(Request::class))->newInstanceWithoutConstructor());

        return $extender;
    }

    private function inject($object, string $property, $value): void
    {
        $p = new ReflectionProperty($object, $property);
        if (PHP_VERSION_ID < 80100) {
            $p->setAccessible(true);
        }
        $p->setValue($object, $value);
    }
}
