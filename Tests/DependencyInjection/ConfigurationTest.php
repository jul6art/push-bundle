<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\DependencyInjection;

use Jul6Art\PushBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    public function testItsRootNodeIsPush(): void
    {
        self::assertSame('push', new Configuration()->getConfigTreeBuilder()->buildTree()->getName());
    }

    public function testItAppliesDefaultsWhenNothingIsConfigured(): void
    {
        self::assertSame([
            'async' => true,
            'enabled' => true,
            'transport_type' => 'database',
            'transport_method' => 'doctrine://default',
            'routing' => [],
        ], $this->process([]));
    }

    public function testItKeepsTheConfiguredValues(): void
    {
        $config = $this->process([[
            'async' => false,
            'enabled' => false,
            'transport_type' => 'amqp',
            'transport_method' => 'amqp://localhost',
            'routing' => ['App\Message\Foo' => 'sync'],
        ]]);

        self::assertFalse($config['async']);
        self::assertFalse($config['enabled']);
        self::assertSame('amqp', $config['transport_type']);
        self::assertSame('amqp://localhost', $config['transport_method']);
        self::assertSame(['App\Message\Foo' => 'sync'], $config['routing']);
    }

    /**
     * async and enabled are booleanNodes, so they no longer accept arbitrary scalars.
     */
    #[DataProvider('nonBooleanValues')]
    public function testItRejectsNonBooleanFlags(string $key, mixed $value): void
    {
        $this->expectException(InvalidTypeException::class);

        $this->process([[$key => $value]]);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function nonBooleanValues(): iterable
    {
        yield 'async as string' => ['async', 'yes'];
        yield 'async as int' => ['async', 0];
        yield 'enabled as string' => ['enabled', 'no'];
        yield 'enabled as int' => ['enabled', 1];
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     *
     * @return array<array-key, mixed>
     */
    private function process(array $configs): array
    {
        return new Processor()->processConfiguration(new Configuration(), $configs);
    }
}
