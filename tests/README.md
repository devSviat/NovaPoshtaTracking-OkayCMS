# Тести модуля NovaPoshtaTracking

Тести модульні: сайт не піднімають, у базу не ходять. Вони перевіряють логіку
класів модуля, підставляючи заглушки замість сервісів ядра, тому весь набір іде
за частку секунди.

## 1. Візьміть тести з репозиторію

В архів релізу тека `tests/` **не входить** — вона потрібна лише розробнику.
Взяти її можна з репозиторію:

```bash
git clone https://github.com/devSviat/NovaPoshtaTracking-OkayCMS.git
```

або «Source code (zip)» на сторінці потрібного релізу.

## 2. Покладіть їх у OkayCMS

Тести мають лежати за адресою, що дзеркалить адресу самого модуля:

```
{OkayCMS_root}/
├── Okay/Modules/Sviat/NovaPoshtaTracking/     ← модуль
└── tests/Modules/Sviat/NovaPoshtaTracking/     ← його тести
```

Тека `tests/` у корені OkayCMS уже існує — у ній лежать тести ядра.

```bash
cd {OkayCMS_root}
mkdir -p tests/Modules/Sviat/NovaPoshtaTracking
cp -r ~/NovaPoshtaTracking-OkayCMS/tests/. tests/Modules/Sviat/NovaPoshtaTracking/
```

Лишити тести в теці модуля не вийде: PHPUnit бере тільки те, що вказано в
`phpunit.xml` OkayCMS, а там `<directory>tests</directory>`. Та й класи ядра,
без яких тести не працюють, приходять із автозавантажувача рушія.

## 3. Запустіть

```bash
cd {OkayCMS_root}
vendor/bin/phpunit tests/Modules/Sviat/NovaPoshtaTracking
```

Очікуваний результат — рядок `OK (N tests, M assertions)`.

Весь набір разом із тестами ядра — просто `vendor/bin/phpunit`.

**Немає `vendor/bin/phpunit`?** Отже, залежності ставили без dev-пакетів.
PHPUnit входить у поставку OkayCMS як `require-dev`:

```bash
composer install
```

**Потрібен PHP 8.5** — як і самому модулю. Тести пишуться синтаксисом 8.4+
(`new Foo()->bar()` без дужок, типізовані константи) і на старіших версіях
не розпарсяться.

## Як дописати свій тест

1. Створіть файл `SomeServiceTest.php` — назва файла має збігатися з назвою
   класу, обидві закінчуються на `Test`, назви методів починаються з `test`.
2. Неймспейс — `Modules\Sviat\NovaPoshtaTracking`.
3. Хелпери (фейки, спільні базові класи) кладіть поруч і підключайте
   `require_once` — автозавантаження для теки `tests/` немає.

```php
<?php

namespace Modules\Sviat\NovaPoshtaTracking;

use Okay\Modules\Sviat\NovaPoshtaTracking\Services\SomeService;
use PHPUnit\Framework\TestCase;

class SomeServiceTest extends TestCase
{
    public function testReturnsZeroWhenEmpty(): void
    {
        self::assertSame(0, (new SomeService())->count());
    }
}
```

Класи ядра (`Okay\Core\…`, `Okay\Entities\…`) доступні — тести йдуть під
автозавантажувачем рушія. Замість справжніх сервісів зазвичай беруть
`$this->createMock(...)`.

Новий файл дописуйте і в репозиторій модуля, у теку `tests/`, — інакше він
загубиться при наступному оновленні.

## Якщо модуль має працювати і на форку OkayCMS

Крім стокової OkayCMS, модуль тримає сумісність із
[форком OkayCMS](https://github.com/devSviat/OkayCMS-Fork) — окремою збіркою рушія на
PHP 8.5 зі Smarty 5. Якщо ви працюєте лише зі звичайною OkayCMS, цей розділ
можна пропустити.

Обидва рушії йдуть на PHP 8.5: форк із PHPUnit 13, стокова OkayCMS 4.5.3 —
з PHPUnit 11. Щоб один тест був зелений і там, і там:

**1. Провайдер даних — атрибутом.** Анотацію `@dataProvider` PHPUnit 13 уже не читає.

```php
#[DataProvider('numbers')]
public function testSomething(int $n): void
```

**2. Без `setAccessible()`.** З PHP 8.1 він не потрібен, а на 8.5 застарілий
і робить прогін червоним.

## Що перевіряє CI

Реліз (`Actions` → `Реліз` → `Run workflow`) не збереться, поки тести червоні:

1. `php -l` на всьому коді модуля під PHP 8.5;
2. модуль ставиться в справжній рушій — окремо в стокову OkayCMS, окремо у
   [форк](https://github.com/devSviat/OkayCMS-Fork) — і там запускається `phpunit`;
3. лише після цього піднімається версія, ставиться тег і збирається архів.
