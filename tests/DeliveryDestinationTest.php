<?php

namespace Modules\Sviat\NovaPoshtaTracking;

use Okay\Admin\Helpers\BackendOrdersHelper;
use Okay\Core\EntityFactory;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCostDeliveryDataEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\DeliveryDestinationResolver;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\DocumentRequestGuard;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\NovaPoshtaApiHelper;
use Okay\Modules\Sviat\NovaPoshtaTracking\Services\NovaPoshtaDocumentService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Куди їде ТТН: адреса, відділення чи поштомат.
 *
 * Тип рахувався з прапорця форми, а запит будувався з іншого прапорця. Кур'єрська
 * доставка (service_type = WarehouseDoors) без прапорця форми йшла в НП як
 * WarehouseDoors без жодного поля адреси.
 */
class DeliveryDestinationTest extends TestCase
{
    private const string CITY = 'db5c88f5-391c-11dd-90d9-001a92567626';
    private const string WAREHOUSE = '1ec09d2e-e1c2-11e3-8c4a-0050568002cf';

    #[DataProvider('resolvedProvider')]
    public function testResolvesDestination(array $data, array $settings, ?string $requested, string $mode, string $service, int $locker): void
    {
        $this->assertSame(
            ['mode' => $mode, 'service_type' => $service, 'pickup_locker' => $locker],
            DeliveryDestinationResolver::resolve((object) $data, $settings, $requested)
        );
    }

    public static function resolvedProvider(): array
    {
        $address = ['city_id' => self::CITY, 'city_name' => 'Львів', 'street' => 'Городоцька', 'house' => '10'];
        $warehouse = ['city_id' => self::CITY, 'warehouse_id' => self::WAREHOUSE];
        $doors = ['service_type' => 'WarehouseDoors'];
        $branch = ['service_type' => 'WarehouseWarehouse'];

        return [
            'курʼєрська доставка без вибору у формі' => [$address, $doors, null, 'address', 'WarehouseDoors', 0],
            'відділення без вибору у формі'          => [$warehouse, $branch, null, 'warehouse', 'WarehouseWarehouse', 0],
            'поштомат за прапорцем у даних'          => [$warehouse + ['pickup_locker' => 1], $branch, null, 'locker', 'WarehouseWarehouse', 1],
            'поштомат, обраний у формі'              => [$warehouse, $branch, 'locker', 'locker', 'WarehouseWarehouse', 1],
            'без налаштувань — за даними адреси'     => [$address, [], null, 'address', 'WarehouseDoors', 0],
            'без налаштувань — за відділенням'       => [$warehouse, [], null, 'warehouse', 'WarehouseWarehouse', 0],
            'порожній вибір у формі = не обрано'     => [$address, $doors, '', 'address', 'WarehouseDoors', 0],
        ];
    }

    #[DataProvider('rejectedProvider')]
    public function testRejectsContradictoryDestination(array $data, array $settings, ?string $requested, string $messagePart): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($messagePart);

        DeliveryDestinationResolver::resolve((object) $data, $settings, $requested);
    }

    public static function rejectedProvider(): array
    {
        $address = ['city_id' => self::CITY, 'city_name' => 'Львів', 'street' => 'Городоцька', 'house' => '10'];
        $warehouse = ['city_id' => self::CITY, 'warehouse_id' => self::WAREHOUSE];
        $doors = ['service_type' => 'WarehouseDoors'];
        $branch = ['service_type' => 'WarehouseWarehouse'];

        return [
            'курʼєрська, але адреси немає'              => [$warehouse, $doors, null, 'населений пункт'],
            'курʼєрська без номера будинку'             => [['house' => ''] + $address, $doors, null, 'будинку'],
            'курʼєрська, а у формі обрано відділення'   => [$address, $doors, 'warehouse', 'адресна доставка'],
            'відділення, а у формі обрано адресу'       => [$warehouse, $branch, 'address', 'відділення'],
            'відділення, а в даних лишилась вулиця'     => [$address + $warehouse, $branch, null, 'Конфлікт'],
            'без налаштувань і з адресою, і з відділенням' => [$address + $warehouse, [], null, 'і адреса, і відділення'],
            'відділення без Ref відділення'             => [['city_id' => self::CITY], $branch, null, 'Ref відділення'],
            'невідомий тип з форми'                     => [$address, $doors, 'courier', 'Невідомий тип'],
            'непідтримуваний ServiceType'               => [$address, ['service_type' => 'DoorsDoors'], null, 'DoorsDoors'],
            'даних НП немає'                            => [[], $doors, null, 'населений пункт'],
        ];
    }

    public function testMissingDeliveryRowIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DeliveryDestinationResolver::resolve(null, ['service_type' => 'WarehouseDoors']);
    }

    /** Головна регресія: запит будується з ServiceType, а не з прапорця форми. */
    public function testAddressRequestCarriesAddressEvenWithoutFormFlag(): void
    {
        $props = $this->buildRequest('WarehouseDoors', ['delivery_type_address' => false]);

        $this->assertSame('WarehouseDoors', $props['ServiceType']);
        $this->assertSame('Львів', $props['RecipientCityName']);
        $this->assertSame('Городоцька', $props['RecipientAddressName']);
        $this->assertSame('10', $props['RecipientHouse']);
        $this->assertArrayNotHasKey('RecipientAddress', $props);
        $this->assertArrayNotHasKey('CityRecipient', $props);
        $this->assertNull(DocumentRequestGuard::error(['calledMethod' => 'save', 'methodProperties' => $props]));
    }

    public function testWarehouseRequestCarriesOnlyWarehouse(): void
    {
        $props = $this->buildRequest('WarehouseWarehouse', ['delivery_type_address' => true]);

        $this->assertSame(self::CITY, $props['CityRecipient']);
        $this->assertSame(self::WAREHOUSE, $props['RecipientAddress']);
        $this->assertArrayNotHasKey('RecipientAddressName', $props);
        $this->assertNull(DocumentRequestGuard::error(['calledMethod' => 'save', 'methodProperties' => $props]));
    }

    #[DataProvider('guardProvider')]
    public function testGuardRejectsMixedOrIncompleteRequests(array $props, string $messagePart): void
    {
        $error = DocumentRequestGuard::error(['calledMethod' => 'save', 'methodProperties' => $props]);

        $this->assertNotNull($error);
        $this->assertStringContainsString($messagePart, $error);
    }

    public static function guardProvider(): array
    {
        $address = ['ServiceType' => 'WarehouseDoors', 'RecipientCityName' => 'Львів', 'RecipientAddressName' => 'Городоцька', 'RecipientHouse' => '10'];
        $warehouse = ['ServiceType' => 'WarehouseWarehouse', 'CityRecipient' => self::CITY, 'RecipientAddress' => self::WAREHOUSE];

        return [
            'адреса без вулиці'         => [['RecipientAddressName' => ''] + $address, 'RecipientAddressName'],
            'адреса з Ref відділення'   => [$address + ['RecipientAddress' => self::WAREHOUSE], 'ServiceType'],
            'відділення з вулицею'      => [$warehouse + ['RecipientAddressName' => 'Городоцька'], 'ServiceType'],
            'відділення без відділення' => [['RecipientAddress' => ''] + $warehouse, 'RecipientAddress'],
            'інший ServiceType'         => [['ServiceType' => 'DoorsDoors'], 'unsupported'],
        ];
    }

    /**
     * Відмова резолвера мусить дійти до оператора як є. Загальний catch
     * перетворював її на «Internal Server Error… перевірте, чи ТТН не створено»,
     * хоча запиту в НП не було.
     */
    public function testResolverRejectionReachesOperatorWithoutApiCallOrSave(): void
    {
        $api = $this->createMock(NovaPoshtaApiHelper::class);
        $api->expects($this->never())->method('sendApiRequest');

        $deliveryData = $this->createMock(NPCostDeliveryDataEntity::class);
        $deliveryData->method('findOne')->willReturn((object) ['id' => 5, 'order_id' => 77, 'city_id' => self::CITY, 'warehouse_id' => self::WAREHOUSE]);
        $deliveryData->expects($this->never())->method('update');

        $result = $this->generate($api, $deliveryData, ['service_type' => 'WarehouseDoors']);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('населений пункт', $result['error']);
        $this->assertStringNotContainsString('Internal Server Error', $result['error']);
    }

    private function generate(NovaPoshtaApiHelper $api, NPCostDeliveryDataEntity $deliveryData, array $deliverySettings): array
    {
        $tracking = $this->createStub(NovaPoshtaTrackingEntity::class);
        $tracking->method('findOne')->willReturn(null);
        $orders = $this->createStub(OrdersEntity::class);
        $orders->method('findOne')->willReturn((object) ['id' => 77, 'delivery_id' => 1, 'paid' => 0, 'phone' => '380000000001', 'name' => 'Іван', 'last_name' => 'Петренко']);
        $deliveries = $this->createStub(DeliveriesEntity::class);
        $deliveries->method('get')->willReturn((object) ['id' => 1, 'settings' => serialize($deliverySettings)]);

        $map = [
            NovaPoshtaTrackingEntity::class => $tracking,
            NPCostDeliveryDataEntity::class => $deliveryData,
            OrdersEntity::class => $orders,
            DeliveriesEntity::class => $deliveries,
        ];
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(fn (string $class) => $map[$class] ?? $this->createStub($class));

        $service = new ReflectionClass(NovaPoshtaDocumentService::class)->newInstanceWithoutConstructor();
        $this->inject($service, 'entityFactory', $factory);
        $this->inject($service, 'settings', $this->createStub(Settings::class));
        $this->inject($service, 'request', new ReflectionClass(Request::class)->newInstanceWithoutConstructor());
        $this->inject($service, 'apiHelper', $api);
        $this->inject($service, 'backendOrdersHelper', $this->createStub(BackendOrdersHelper::class));

        [$get, $post] = [$_GET, $_POST];
        $_GET = $_POST = [];
        try {
            return $service->generateDocument(77);
        } finally {
            [$_GET, $_POST] = [$get, $post];
        }
    }

    private function buildRequest(string $serviceType, array $formData): array
    {
        $service = new ReflectionClass(NovaPoshtaDocumentService::class)->newInstanceWithoutConstructor();
        $this->inject($service, 'settings', $this->createStub(Settings::class));
        // SettlementType шукається через API; без city_id запиту не буде.
        $deliveryData = (object) [
            'city_id' => $serviceType === 'WarehouseDoors' ? '' : self::CITY,
            'warehouse_id' => self::WAREHOUSE,
            'city_name' => 'Львів',
            'street' => 'Городоцька',
            'house' => '10',
            'apartment' => '',
        ];
        $formData += [
            'payer_type' => 'Recipient',
            'payment_method' => 'Cash',
            'cargo_type' => 'Cargo',
            'door_delivery' => 0,
            'pickup_locker' => 0,
            'additional_information' => '',
            'recipient_address_note' => '',
            'control_payment' => 0,
            'amount_due' => 0.0,
        ];
        $sender = ['contact_person' => (object) ['Ref' => 'contact'], 'sender' => (object) ['Ref' => 'sender']];

        $method = new ReflectionMethod($service, 'buildDocumentRequest');
        $request = $method->invoke(
            $service,
            $serviceType,
            $formData,
            $deliveryData,
            (object) ['id' => 77, 'name' => 'Іван', 'last_name' => 'Петренко'],
            $sender,
            [],
            '380000000001',
            '06.11.2026',
            1799,
            [],
            ['volume' => '0.0004', 'weight' => '0.5'],
            0.5,
            0.0004
        );

        return $request['methodProperties'];
    }

    private function inject(object $object, string $property, $value): void
    {
        new ReflectionProperty($object, $property)->setValue($object, $value);
    }
}
