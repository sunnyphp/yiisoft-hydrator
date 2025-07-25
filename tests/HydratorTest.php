<?php

declare(strict_types=1);

namespace Yiisoft\Hydrator\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Yiisoft\Hydrator\ArrayData;
use Yiisoft\Hydrator\Attribute\Parameter\Data;
use Yiisoft\Hydrator\Attribute\Parameter\DiResolver;
use Yiisoft\Hydrator\Attribute\Parameter\ToString;
use Yiisoft\Hydrator\Attribute\Data\DataAttributeResolverInterface;
use Yiisoft\Hydrator\Exception\AbstractClassException;
use Yiisoft\Hydrator\Exception\NonExistClassException;
use Yiisoft\Hydrator\Exception\NonPublicConstructorException;
use Yiisoft\Hydrator\Hydrator;
use Yiisoft\Hydrator\ObjectFactory\ContainerObjectFactory;
use Yiisoft\Hydrator\Attribute\Parameter\ParameterAttributeResolverInterface;
use Yiisoft\Hydrator\AttributeHandling\ResolverFactory\ContainerAttributeResolverFactory;
use Yiisoft\Hydrator\Tests\Support\AbstractClass;
use Yiisoft\Hydrator\Tests\Support\Attribute\CounterResolver;
use Yiisoft\Hydrator\Tests\Support\Attribute\FromPredefinedArray;
use Yiisoft\Hydrator\Tests\Support\Attribute\FromPredefinedArrayResolver;
use Yiisoft\Hydrator\Tests\Support\Attribute\InvalidParameterResolver;
use Yiisoft\Hydrator\Tests\Support\Attribute\NotResolver;
use Yiisoft\Hydrator\Tests\Support\Classes\ConstructorParameterAttributesClass;
use Yiisoft\Hydrator\Tests\Support\Classes\ConstructorTypeClass;
use Yiisoft\Hydrator\Tests\Support\Classes\CounterClass;
use Yiisoft\Hydrator\Tests\Support\Classes\EnumsByDefault;
use Yiisoft\Hydrator\Tests\Support\Classes\FromPredefinedArrayClass;
use Yiisoft\Hydrator\Tests\Support\Classes\Inheritance\ReadOnly\ImageSlideDto;
use Yiisoft\Hydrator\Tests\Support\Classes\Inheritance\Figure\Circle;
use Yiisoft\Hydrator\Tests\Support\Classes\InvalidDataResolverClass;
use Yiisoft\Hydrator\Tests\Support\Classes\NestedModel\UserModel;
use Yiisoft\Hydrator\Tests\Support\Classes\NonInitializedReadonlyProperties;
use Yiisoft\Hydrator\Tests\Support\Classes\ObjectPropertyModel\ObjectPropertyModel;
use Yiisoft\Hydrator\Tests\Support\Classes\ObjectPropertyModel\RedCar;
use Yiisoft\Hydrator\Tests\Support\Classes\PreparePropertyClass;
use Yiisoft\Hydrator\Tests\Support\Classes\ReadonlyClass;
use Yiisoft\Hydrator\Tests\Support\Classes\SimpleClass;
use Yiisoft\Hydrator\Tests\Support\Classes\StaticClass;
use Yiisoft\Hydrator\Tests\Support\Classes\StringableCar;
use Yiisoft\Hydrator\Tests\Support\Classes\TypeClass;
use Yiisoft\Hydrator\Tests\Support\IntegerEnum;
use Yiisoft\Hydrator\Tests\Support\PrivateConstructor;
use Yiisoft\Hydrator\Tests\Support\ProtectedConstructor;
use Yiisoft\Hydrator\Tests\Support\String42TypeCaster;
use Yiisoft\Hydrator\Tests\Support\StringableObject;
use Yiisoft\Hydrator\Tests\Support\StringEnum;
use Yiisoft\Hydrator\TypeCaster\CompositeTypeCaster;
use Yiisoft\Hydrator\TypeCaster\NoTypeCaster;
use Yiisoft\Hydrator\TypeCaster\PhpNativeTypeCaster;
use Yiisoft\Injector\Injector;
use Yiisoft\Test\Support\Container\SimpleContainer;

final class HydratorTest extends TestCase
{
    public function testSimpleCreate(): void
    {
        $hydrator = new Hydrator();

        $object = $hydrator->create(
            SimpleClass::class,
            ['a' => '1', 'b' => '2', 'c' => '3'],
        );

        $this->assertSame('1', $object->getA());
        $this->assertSame('2', $object->getB());
        $this->assertSame('3', $object->getC());
    }

    public function testSimpleHydrate(): void
    {
        $hydrator = new Hydrator();

        $object = new SimpleClass();
        $hydrator->hydrate(
            $object,
            ['a' => '1', 'b' => '2', 'c' => '3'],
        );

        $this->assertSame('1', $object->getA());
        $this->assertSame('2', $object->getB());
        $this->assertSame('3', $object->getC());
    }

    public function testSimpleCreateStrict(): void
    {
        $hydrator = new Hydrator();

        $object = $hydrator->create(
            SimpleClass::class,
            new ArrayData(
                ['a' => '1', 'b' => '2', 'c' => '3'],
                ['b' => 'b'],
                true,
            ),
        );

        $this->assertSame('.', $object->getA());
        $this->assertSame('2', $object->getB());
        $this->assertSame('.', $object->getC());
    }

    public function testSimpleHydrateStrict(): void
    {
        $hydrator = new Hydrator();

        $object = new SimpleClass();
        $hydrator->hydrate(
            $object,
            new ArrayData(
                ['a' => '1', 'b' => '2', 'c' => '3'],
                ['b' => 'b'],
                true,
            ),
        );

        $this->assertSame('.', $object->getA());
        $this->assertSame('2', $object->getB());
        $this->assertSame('.', $object->getC());
    }

    public static function dataSimpleHydrateWithMap(): array
    {
        return [
            'simple' => [
                ['x' => '1', 'y' => '2', 'z' => '3'],
                ['a' => 'x', 'b' => 'y', 'c' => 'z'],
            ],
            'simple-partly' => [
                ['a' => '1', 'y' => '2', 'c' => '3'],
                ['b' => 'y'],
            ],
            'simple-with-dot-in-key' => [
                ['x.inner' => '1', 'y' => '2', 'z' => '3'],
                ['a' => 'x\.inner', 'b' => 'y', 'c' => 'z'],
            ],
            'nested' => [
                ['x' => ['inner' => '1'], 'y' => '2', 'z' => '3'],
                ['a' => 'x.inner', 'b' => 'y', 'c' => 'z'],
            ],
            'nested-with-array-map' => [
                ['x' => ['inner' => '1'], 'y' => '2', 'z' => '3'],
                ['a' => ['x', 'inner'], 'b' => 'y', 'c' => 'z'],
            ],
        ];
    }

    #[DataProvider('dataSimpleHydrateWithMap')]
    public function testSimpleHydrateWithMap(array $data, array $map): void
    {
        $hydrator = new Hydrator();

        $object = new SimpleClass();
        $hydrator->hydrate($object, new ArrayData($data, $map));

        $this->assertSame('1', $object->getA());
        $this->assertSame('2', $object->getB());
        $this->assertSame('3', $object->getC());
    }

    public function testPrepareProperty(): void
    {
        $hydrator = new Hydrator();

        $object = $hydrator->create(PreparePropertyClass::class, ['a' => 'test']);

        $this->assertSame('test!', $object->getA());
    }

    public function testCreateNestedObject(): void
    {
        $hydrator = new Hydrator();

        $object = $hydrator->create(
            UserModel::class,
            ['name.first' => 'Mike', 'name' => ['last' => 'Li']]
        );

        $this->assertSame('Mike Li', $object->getName());
    }

    public static function dataCreateNestedObjectWithMap(): array
    {
        return [
            [
                [
                    'fio.first' => 'Mike',
                    'fio' => ['last' => 'Li'],
                ],
                ['name' => 'fio'],
            ],
            [
                [
                    'person.fio' => [
                        'first' => 'Mike',
                    ],
                    'person.fio.last' => 'Li',
                ],
                ['name' => 'person.fio'],
            ],
        ];
    }

    #[DataProvider('dataCreateNestedObjectWithMap')]
    public function testCreateNestedObjectWithMap(array $data, array $map): void
    {
        $hydrator = new Hydrator();

        $object = $hydrator->create(UserModel::class, new ArrayData($data, $map));

        $this->assertSame('Mike Li', $object->getName());
    }

    public static function dataTypeCast(): array
    {
        return [
            // Integer
            'int-to-int' => [
                ['int' => 42],
                ['int' => 42],
            ],
            'string-to-int' => [
                ['int' => 42],
                ['int' => '42'],
            ],
            'float-to-int' => [
                ['int' => 42],
                ['int' => 42.36],
            ],
            'bool-to-int' => [
                ['int' => 1],
                ['int' => true],
            ],
            'null-to-int' => [
                ['int' => 0],
                ['int' => null],
            ],
            'object-to-int' => [
                [],
                ['int' => new stdClass()],
            ],
            'stringable-to-int' => [
                ['int' => 99],
                ['int' => new StringableObject('99')],
            ],
            'string-with-separators-to-int' => [
                ['int' => 1_000_242],
                ['int' => '1 000 242'],
            ],
            'array-to-int' => [
                [],
                ['int' => [42]],
            ],
            'null-to-nullable-int' => [
                ['intNullable' => null],
                ['intNullable' => null],
            ],

            // String
            'int-to-string' => [
                ['string' => '42'],
                ['string' => 42],
            ],
            'string-to-string' => [
                ['string' => '42'],
                ['string' => '42'],
            ],
            'float-to-string' => [
                ['string' => '42.36'],
                ['string' => '42.36'],
            ],
            'bool-to-string' => [
                ['string' => '1'],
                ['string' => true],
            ],
            'null-to-string' => [
                ['string' => ''],
                ['string' => null],
            ],
            'object-to-string' => [
                [],
                ['string' => new stdClass()],
            ],
            'stringable-to-string' => [
                ['string' => 'hello'],
                ['string' => new StringableObject('hello')],
            ],
            'array-to-string' => [
                [],
                ['string' => ['42']],
            ],
            'null-to-nullable-string' => [
                ['stringNullable' => null],
                ['stringNullable' => null],
            ],

            // Boolean
            'bool-to-bool' => [
                ['bool' => true],
                ['bool' => true],
            ],
            'int-to-bool' => [
                ['bool' => true],
                ['bool' => 1],
            ],
            'float-to-bool' => [
                ['bool' => true],
                ['bool' => 1.1],
            ],
            'string-to-bool' => [
                ['bool' => true],
                ['bool' => '1'],
            ],
            'array-to-bool' => [
                ['bool' => true],
                ['bool' => [1, 2, 3]],
            ],
            'null-to-bool' => [
                ['bool' => false],
                ['bool' => null],
                fn(TypeClass $object) => $object->bool = true,
            ],
            'object-to-bool' => [
                ['bool' => true],
                ['bool' => new stdClass()],
            ],
            'resource-to-bool' => [
                ['bool' => true],
                ['bool' => tmpfile()],
                fn(TypeClass $object) => $object->bool = true,
            ],

            // Float
            'int-to-float' => [
                ['float' => 42.0],
                ['float' => 42],
            ],
            'string-to-float' => [
                ['float' => 42.1],
                ['float' => '42.1'],
            ],
            'float-to-float' => [
                ['float' => 42.36],
                ['float' => 42.36],
            ],
            'bool-to-float' => [
                ['float' => 1.0],
                ['float' => true],
            ],
            'null-to-float' => [
                ['float' => 0.0],
                ['float' => null],
            ],
            'object-to-float' => [
                [],
                ['float' => new stdClass()],
            ],
            'stringable-to-float' => [
                ['float' => 99.36],
                ['float' => new StringableObject('99.36')],
            ],
            'string-with-separators-to-float' => [
                ['float' => 1_000_242.25],
                ['float' => '1 000 242,25'],
            ],

            // No type
            'int-to-noType' => [
                ['noType' => 9],
                ['noType' => 9],
            ],
            'array-to-noType' => [
                ['noType' => ['hello', 'world']],
                ['noType' => ['hello', 'world']],
            ],
            'null-to-noType' => [
                ['noType' => null],
                ['noType' => null],
            ],

            // Array
            'int-to-array' => [
                [],
                ['array' => 7],
            ],
            'array-to-array' => [
                ['array' => [7]],
                ['array' => [7]],
            ],

            // Array or string
            'stringable-to-arrayOrString' => [
                ['arrayOrString' => 'test'],
                ['arrayOrString' => new StringableObject('test')],
            ],

            // int|string
            'int-to-int-string' => [
                ['intString' => 42],
                ['intString' => 42],
            ],
            'string-to-int-string' => [
                ['intString' => '42'],
                ['intString' => '42'],
            ],

            // intersection type casting is not supported
            'intersection' => [
                ['intersection' => 'red car'],
                ['intersection' => new StringableCar('yellow')],
            ],
        ];
    }

    #[DataProvider('dataTypeCast')]
    public function testTypeCast(array $expectedValues, array $data, ?callable $prepareCallable = null): void
    {
        $object = new TypeClass();
        if ($prepareCallable !== null) {
            $prepareCallable($object);
        }

        $hydrator = new Hydrator();
        $hydrator->hydrate($object, $data);

        $expectedValues = array_merge(
            [
                'noType' => -1,
                'int' => -1,
                'intNullable' => -1,
                'string' => 'x',
                'stringNullable' => 'x',
                'bool' => false,
                'float' => -2.0,
                'array' => [-1],
                'arrayOrString' => 'x',
                'intString' => -1,
                'intersection' => 'red car',
            ],
            $expectedValues
        );

        $this->assertSame(
            $expectedValues,
            [
                'noType' => $object->noType,
                'int' => $object->int,
                'intNullable' => $object->intNullable,
                'string' => $object->string,
                'stringNullable' => $object->stringNullable,
                'bool' => $object->bool,
                'float' => $object->float,
                'array' => $object->array,
                'arrayOrString' => $object->arrayOrString,
                'intString' => $object->intString,
                'intersection' => (string) $object->intersection,
            ]
        );
    }

    public static function dataConstructorTypeCast(): array
    {
        return [
            'array-to-int' => [
                [],
                ['int' => [42]],
            ],
        ];
    }

    #[DataProvider('dataConstructorTypeCast')]
    public function testConstructorTypeCast(array $expectedValues, array $data): void
    {
        $hydrator = new Hydrator();
        $object = $hydrator->create(ConstructorTypeClass::class, $data);

        $expectedValues = array_merge(
            [
                'int' => -1,
            ],
            $expectedValues
        );

        $this->assertSame(
            $expectedValues,
            [
                'int' => $object->int,
            ]
        );
    }

    public function testCustomTypeCast(): void
    {
        $container = new SimpleContainer();
        $typeCaster = new CompositeTypeCaster(
            new String42TypeCaster(),
            new PhpNativeTypeCaster(),
        );
        $hydrator = new Hydrator(
            $typeCaster,
            new ContainerAttributeResolverFactory($container),
            new ContainerObjectFactory(new Injector($container))
        );

        $object = new TypeClass();
        $hydrator->hydrate($object, ['string' => 'test', 'int' => 7]);

        $this->assertSame('42', $object->string);
        $this->assertSame(7, $object->int);
    }

    public function testCompositeTypeCastWithoutCast(): void
    {
        $container = new SimpleContainer();
        $typeCaster = new CompositeTypeCaster(
            new String42TypeCaster(),
            new PhpNativeTypeCaster(),
        );
        $hydrator = new Hydrator(
            $typeCaster,
            new ContainerAttributeResolverFactory($container),
            new ContainerObjectFactory(new Injector($container))
        );

        $object = new TypeClass();
        $hydrator->hydrate($object, ['int' => new stdClass()]);

        $this->assertSame(-1, $object->int);
    }

    public function testWithPropertyAttributeResolver(): void
    {
        $resolver = new FromPredefinedArrayResolver();

        $hydrator = new Hydrator(
            new NoTypeCaster(),
            new ContainerAttributeResolverFactory(
                new SimpleContainer([
                    FromPredefinedArrayResolver::class => $resolver,
                ]),
            )
        );

        $object = new class () {
            #[FromPredefinedArray('number')]
            #[ToString]
            public string $a;
        };

        $resolver->setArray(['number' => 42]);

        $hydrator->hydrate($object);

        $this->assertSame('42', $object->a);
    }

    public function testWithClassAttributeResolver(): void
    {
        $resolver = new FromPredefinedArrayResolver();

        $hydrator = new Hydrator(
            attributeResolverFactory: new ContainerAttributeResolverFactory(
                new SimpleContainer([
                    FromPredefinedArrayResolver::class => $resolver,
                ]),
            )
        );

        $object = new FromPredefinedArrayClass();

        $resolver->setArray(['a' => 42]);

        $hydrator->hydrate($object);

        $this->assertSame('42', $object->a);
    }

    public function testConstructorParameterAttributes(): void
    {
        $hydrator = new Hydrator(
            new NoTypeCaster(),
            new ContainerAttributeResolverFactory(
                new SimpleContainer([
                    DiResolver::class => new DiResolver(
                        new SimpleContainer(['stringable42' => new StringableObject('42')])
                    ),
                ]),
            ),
        );

        $object = $hydrator->create(ConstructorParameterAttributesClass::class, ['a' => 7]);

        $this->assertSame('7', $object->getA());
        $this->assertSame('42', $object->getString());
    }

    public function testTypeCastAfterAttribute(): void
    {
        $hydrator = new Hydrator();

        $object = new class () {
            #[Data('a')]
            public ?string $x = null;

            public function __construct(
                #[Data('b')]
                public ?string $y = null,
            ) {
            }
        };

        $hydrator->hydrate(
            $object,
            data: [
                'a' => 1,
                'b' => 2,
            ],
        );

        $this->assertSame('1', $object->x);
        $this->assertSame('2', $object->y);
    }

    public function testCountParameterAttributeHandle(): void
    {
        $counterResolver = new CounterResolver();
        $container = new SimpleContainer([
            CounterResolver::class => $counterResolver,
        ]);
        $hydrator = new Hydrator(
            attributeResolverFactory: new ContainerAttributeResolverFactory($container),
            objectFactory: new ContainerObjectFactory(new Injector($container))
        );
        $hydrator->create(CounterClass::class);

        $this->assertSame(1, $counterResolver->getCount('a'));
        $this->assertSame(1, $counterResolver->getCount('b'));
        $this->assertSame(1, $counterResolver->getCount('c'));
    }

    public function testObjectProperty(): void
    {
        $hydrator = new Hydrator();

        $object = $hydrator->create(ObjectPropertyModel::class, ['car' => new RedCar()]);

        $this->assertSame('red', $object->car->getColor());
    }

    public function testNonExistPath(): void
    {
        $hydrator = new Hydrator();

        $object = new class () {
            public ?int $value = null;
        };
        $hydrator->hydrate($object, new ArrayData(['a' => ['b' => 23]], ['value' => 'a.b.c']));

        $this->assertNull($object->value);
    }

    public function testInvalidParameterAttributeResolver(): void
    {
        $hydrator = new Hydrator();

        $object = new class () {
            #[InvalidParameterResolver]
            public int $value;
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Parameter attribute resolver "' .
            NotResolver::class .
            '" must implement "' .
            ParameterAttributeResolverInterface::class . '".'
        );
        $hydrator->hydrate($object);
    }

    public function testInvalidDataAttributeResolver(): void
    {
        $hydrator = new Hydrator();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Data attribute resolver "' .
            NotResolver::class .
            '" must implement "' .
            DataAttributeResolverInterface::class . '".'
        );
        $hydrator->create(InvalidDataResolverClass::class);
    }

    public function testStaticProperty(): void
    {
        $hydrator = new Hydrator();

        $object = $hydrator->create(StaticClass::class, ['a' => 7, 'b' => 42, 'c' => 500]);

        $this->assertSame(7, $object->a);
        $this->assertSame(0, $object::$b);
        $this->assertSame(500, $object->c);
    }

    public function testCreateObjectWithPrivateConstructor(): void
    {
        $hydrator = new Hydrator();

        $this->expectException(NonPublicConstructorException::class);
        $hydrator->create(PrivateConstructor::class);
    }

    public function testCreateObjectWithProtectedConstructor(): void
    {
        $hydrator = new Hydrator();

        $this->expectException(NonPublicConstructorException::class);
        $hydrator->create(ProtectedConstructor::class);
    }

    public function testCreateInstanceOfAbstractClass(): void
    {
        $hydrator = new Hydrator();

        $this->expectException(AbstractClassException::class);
        $this->expectExceptionMessage('"' . AbstractClass::class . '" is not instantiable because it is abstract.');
        $hydrator->create(AbstractClass::class);
    }

    public function testCreateNonExistClass(): void
    {
        $hydrator = new Hydrator();

        $this->expectException(NonExistClassException::class);
        $this->expectExceptionMessage('Class "NonExistClass" not exist.');
        $hydrator->create('NonExistClass');
    }

    public function testReadonlyObject(): void
    {
        $hydrator = new Hydrator();

        $object = $hydrator->create(ReadonlyClass::class, ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);

        $this->assertSame(99, $object->a);
        $this->assertSame(2, $object->b);
        $this->assertSame(3, $object->c);
        $this->assertSame(4, $object->d);
    }

    public function testCreateNonInitializedReadonlyProperties(): void
    {
        $hydrator = new Hydrator();

        $object = $hydrator->create(NonInitializedReadonlyProperties::class, ['a' => 1, 'b' => 2]);

        $this->assertSame('1', $object->a);
        $this->assertSame('2', $object->b);
    }

    public function testHydrateNonInitializedReadonlyProperties(): void
    {
        $hydrator = new Hydrator();

        $object = new NonInitializedReadonlyProperties('3');
        $hydrator->hydrate($object, ['a' => 1, 'b' => 2]);

        $this->assertSame('1', $object->a);
        $this->assertSame('3', $object->b);
    }

    public function testBaseInheritance(): void
    {
        $hydrator = new Hydrator();

        $object = $hydrator->create(
            Circle::class,
            [
                'name' => 'Wheel',
                'color' => 'Red',
                'id' => 'x7',
                'radius' => 17,
            ]
        );

        $this->assertSame(17, $object->radius);
        $this->assertSame('Wheel', $object->name);
        $this->assertSame('Red', $object->getColor());
        $this->assertNull($object->getId());
    }

    public function testReadOnlyInheritance(): void
    {
        $hydrator = new Hydrator();

        $object = $hydrator->create(
            ImageSlideDto::class,
            [
                'src' => '/images/slide.jpg',
                'width' => 200,
                'height' => 300,
            ]
        );

        $this->assertSame('/images/slide.jpg', $object->src);
        $this->assertSame(200, $object->width);
        $this->assertSame(300, $object->height);
    }

    public function testHydrateEnumsByDefault(): void
    {
        $hydrator = new Hydrator();

        $data = [
            'string' => 'hello',
            'integer' => 42,
            'stringEnum' => StringEnum::B->value,
            'integerEnum' => IntegerEnum::C->value,
        ];

        $object = $hydrator->create(EnumsByDefault::class, $data);

        $this->assertSame($data['string'], $object->string);
        $this->assertSame($data['integer'], $object->integer);
        $this->assertSame(StringEnum::tryFrom($data['stringEnum']), $object->stringEnum);
        $this->assertSame(IntegerEnum::tryFrom($data['integerEnum']), $object->integerEnum);
    }
}
