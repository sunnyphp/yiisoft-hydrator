# Приведение типов

Когда PHP типы определены в классе, приведение типов автоматически
применяется при создании или наполнении объекта:

```php
final class Lock
{
    public function __construct(
        private string $name,
        private bool $isLocked
    ) {}
}

$hydrator = new Hydrator();
$lock = $hydrator->create(Lock::class, ['name' => 'The lock', 'isLocked' => 1]);
```

## Настройка приведения типов

Вы можете регулировать приведение типов, передавая объект приведения типов в
гидратор:

```php
use Yiisoft\Hydrator\Hydrator;
use Yiisoft\Hydrator\TypeCaster\CompositeTypeCaster;
use Yiisoft\Hydrator\TypeCaster\EnumTypeCaster;
use Yiisoft\Hydrator\TypeCaster\PhpNativeTypeCaster;
use Yiisoft\Hydrator\TypeCaster\HydratorTypeCaster;

$typeCaster = new CompositeTypeCaster(
    new PhpNativeTypeCaster(),
    new HydratorTypeCaster(),
    new EnumTypeCaster(),
);
$hydrator = new Hydrator($typeCaster);
```

Приведенный выше набор используется по-умолчанию.

Из коробки доступны следующие классы для приведения типов:

- `CompositeTypeCaster` позволяет комбинировать несколько классов для
  приведения типов
- `PhpNativeTypeCaster` приведение типов, основанное на PHP типах,
  определенных в классе
- `HydratorTypeCaster` приведение массивов к объектам
- `EnumTypeCaster` приведение значений к перечислениям
- `NullTypeCaster` настраиваемый класс для приведения `null`, пустой строки
  и пустого массива к `null`
- `NoTypeCaster` не использовать приведение типов

## Ваше собственное приведение типов

При необходимости, вы можете определить пользовательский класс для
приведения типов:

```php
use Yiisoft\Hydrator\TypeCaster\TypeCastContext;
use Yiisoft\Hydrator\TypeCaster\TypeCasterInterface;
use Yiisoft\Hydrator\Result;
use Yiisoft\Hydrator\Hydrator;
use Yiisoft\Hydrator\TypeCaster\CompositeTypeCaster;

final class User
{
    public function __construct(
        private string $nickname
    )
    {
    }
    
    public function getNickName(): string
    {
        return $this->nickname;
    }
}

final class NickNameTypeCaster implements TypeCasterInterface
{
    public function cast(mixed $value, TypeCastContext $context): Result
    {
        $type = $context->getReflectionType();
    
        if (
            $context->getReflection()->getName() === 'author'
            && $type instanceof ReflectionNamedType
            && $type->isBuiltin()
            && $type->getName() === 'string'
            && preg_match('~^@(.*)$~', $value, $matches)
        ) {            
            $user = new User($matches[1]);
            return Result::success($user);        
        }       

        return Result::fail();
    }
}

final class Post
{
    public function __construct(
        private string $title,
        private User $author
    )
    {    
    }
    
    public function getTitle(): string 
    {
        return $this->title;    
    }
    
    public function getAuthor(): User
    {
        return $this->author;
    }
}

$typeCaster = new CompositeTypeCaster(
    // ...
    new NickNameTypeCaster(),
);
$hydrator = new Hydrator($typeCaster);

$post = $hydrator->create(Post::class, ['title' => 'Example post', 'author' => '@samdark']);
echo $post->getAuthor()->getNickName();
```

## Использование атрибутов

### `ToString`

Для приведения значения к строке явно, вы можете использовать атрибут
`ToString`:

```php
use \Yiisoft\Hydrator\Attribute\Parameter\ToString;

class Money
{
    public function __construct(
        #[ToString]
        private string $value,
        private string $currency,
    ) {}
}

$money = $hydrator->create(Money::class, [
    'value' => 4200,
    'currency' => 'AMD',
]);
```

### `Trim` / `LeftTrim` / `RightTrim`

Для удаления пробелов (или других символов) из начала и/или конца строки, вы
можете использовать атрибуты `Trim`, `LeftTrim` или `RightTrim`:

```php
use DateTimeImmutable;
use Yiisoft\Hydrator\Attribute\Parameter\Trim;

class Person
{
    public function __construct(
        #[Trim] // '  John ' → 'John'
        private ?string $name = null, 
    ) {}
}

$person = $hydrator->create(Person::class, ['name' => '  John ']);
```

### `ToDatetime`

Чтобы явно привести значение к объекту `DateTimeImmutable` или `DateTime`,
можно использовать атрибут `ToDateTime`:

```php
use DateTimeImmutable;
use Yiisoft\Hydrator\Attribute\Parameter\ToDateTime;

class Person
{
    public function __construct(
        #[ToDateTime(locale: 'ru')]
        private ?DateTimeImmutable $birthday = null,
    ) {}
}

$person = $hydrator->create(Person::class, ['birthday' => '27.01.1986']);
```

### `Collection`

Гидратор поддерживает коллекции через атрибут `Collection`. Необходимо указать имя класса связанной коллекции:

```php
final class PostCategory
{
    public function __construct(
        #[Collection(Post::class)]
        private array $posts = [],
    ) {
    }
}

final class Post
{
    public function __construct(
        private string $name,
        private string $description = '',
    ) {
    }
}

$category = $hydrator->create(
    PostCategory::class,
    [
        ['name' => 'Post 1'],
        ['name' => 'Post 2', 'description' => 'Description for post 2'],
    ],
);
```

### `ToArrayOfStrings`

Используйте атрибут `ToArrayOfStrings` для приведения значения к массиву
строк:

```php
use Yiisoft\Hydrator\Attribute\Parameter\ToArrayOfStrings;

final class Post
{
    #[ToArrayOfStrings(separator: ',')]
    public array $tags = [];    
}
```

Значение `tags` будет приведено к массиву строк, разделенных `,`. Например,
строка `news,city,hot` будет приведена к массиву `['news', 'city', 'hot']`.

Параметры атрибута:

- `trim` — обрезка каждой строки массива (логическое значение, по умолчанию
  `false`);
- `removeEmpty` — удаление пустой строки из массива (логическое значение, по
  умолчанию `false`);
- `splitResolvedValue` — разделить значения по разделителю (логическое
  значение, по умолчанию `true`);
- `separator` — символ перевода строки (по умолчанию, `\R`). Это часть
  регулярного выражения, поэтому ее следует учитывать или правильно
  экранировать с помощью `preg_quote()`.
