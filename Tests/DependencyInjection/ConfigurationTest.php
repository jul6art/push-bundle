<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\DependencyInjection;

use Jul6Art\PushBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
            'mercure' => [
                'buffering' => true,
                'topic_resolver' => null,
                'topic_prefix' => '',
                'token_lifetime' => 3600,
                'cookie_path' => '/.well-known/mercure',
                'cookie_secure' => true,
                'subscriber' => [
                    'provider' => null,
                    'access' => 'IS_AUTHENTICATED_FULLY',
                ],
            ],
            'routing' => [],
        ], $this->process([]));
    }

    /**
     * The subscriber endpoint (ADR-0001): which account may call it is a written choice, never left
     * blank — an empty attribute would be a question nobody asked `isGranted()`.
     */
    public function testTheSubscriberAccessIsNeverEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['mercure' => ['subscriber' => ['access' => '']]]]);
    }

    public function testTheSubscriberKeepsTheApplicationsProviderAndAccess(): void
    {
        $subscriber = $this->mercureDefaults([['mercure' => ['subscriber' => ['provider' => 'App\\Mercure\\SubscriptionProvider', 'access' => 'REALTIME_SUBSCRIBE']]]])['subscriber'] ?? null;

        self::assertSame(['provider' => 'App\\Mercure\\SubscriptionProvider', 'access' => 'REALTIME_SUBSCRIBE'], $subscriber);
    }

    /**
     * Les deux défauts qui protègent quelque chose : la mise en tampon (une panne de hub ne doit
     * pas devenir un 500) et le cookie `secure` (le jeton d'abonnement ne doit pas voyager en
     * clair). Les abaisser doit être un choix écrit.
     */
    public function testTheProtectiveMercureDefaultsAreOn(): void
    {
        $mercure = $this->mercureDefaults();

        self::assertTrue($mercure['buffering']);
        self::assertTrue($mercure['cookie_secure']);
    }

    /**
     * 3.0 : le secret et le nom du cookie sont ceux du hub (`mercure.hubs.<name>`). Les anciennes
     * clés sont refusées plutôt qu'ignorées : un `jwt_secret` resté dans `push.yaml` ne doit pas
     * laisser croire qu'il signe encore quoi que ce soit.
     */
    public function testTheSecretAndTheCookieNameNoLongerLiveHere(): void
    {
        foreach (['jwt_secret' => 'a-secret', 'cookie_name' => 'mercureAuthorization'] as $key => $value) {
            try {
                $this->process([['mercure' => [$key => $value]]]);
                self::fail(\sprintf('push.mercure.%s should be refused.', $key));
            } catch (InvalidConfigurationException $e) {
                self::assertStringContainsString($key, $e->getMessage());
            }
        }
    }

    /**
     * Le préfixe est un chemin, sans barre finale : `/shop`. Une barre finale doublerait la barre
     * de chaque sujet, et un nom sans barre ne serait le début d'aucun sujet.
     */
    public function testATopicPrefixIsAPathWithoutATrailingSlash(): void
    {
        $mercure = $this->process([['mercure' => ['topic_prefix' => '/shop']]])['mercure'] ?? null;
        self::assertIsArray($mercure);
        self::assertSame('/shop', $mercure['topic_prefix'] ?? null);

        foreach (['shop', '/shop/', '/a b'] as $invalid) {
            try {
                $this->process([['mercure' => ['topic_prefix' => $invalid]]]);
                self::fail(\sprintf('"%s" was accepted as a topic prefix.', $invalid));
            } catch (InvalidConfigurationException) {
                $this->addToAssertionCount(1);
            }
        }
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
     * @return array<mixed>
     */
    private function mercureDefaults(array $configs = []): array
    {
        $mercure = $this->process($configs)['mercure'] ?? null;
        self::assertIsArray($mercure);

        return $mercure;
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
