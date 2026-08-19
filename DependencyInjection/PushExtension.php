<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\DependencyInjection;

use Doctrine\ORM\Events;
use Jul6Art\CoreBundle\CoreBundle;
use Jul6Art\PushBundle\Mercure\BufferingHub;
use Jul6Art\PushBundle\Mercure\EntityChangePublisher;
use Jul6Art\PushBundle\Mercure\FeedTopicResolverInterface;
use Jul6Art\PushBundle\Mercure\GlobalFeedTopicResolver;
use Jul6Art\PushBundle\Mercure\MercureDrainListener;
use Jul6Art\PushBundle\Mercure\SubscriberCookieFactory;
use Jul6Art\PushBundle\Message\EntityAsyncEvent;
use Jul6Art\PushBundle\Twig\JwtTokenExtension;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Update;
use Twig\Extension\AbstractExtension;

/**
 * Class PushExtension.
 *
 * @phpstan-type PushConfig array{
 *     async: bool,
 *     enabled: bool,
 *     transport_type: string,
 *     transport_method: string,
 *     routing: array<string, string>,
 * }
 */
class PushExtension extends Extension implements PrependExtensionInterface
{
    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('services.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);
        $mercure = \is_array($config['mercure'] ?? null) ? $config['mercure'] : [];

        $this->registerMercure($container, $mercure);
    }

    /**
     * Le temps réel n'est enregistré que si l'application a un hub, ce que seul un
     * `MercureHubPass` peut constater — une extension tourne avant que MercureBundle ait parlé.
     * Ici on déclare les services et on laisse le pass trancher.
     *
     * @param array<mixed> $config
     */
    private function registerMercure(ContainerBuilder $container, array $config): void
    {
        // Toujours enregistré, même quand l'application fournit le sien : nommer explicitement
        // celui du bundle laisserait sinon un alias vers un service inexistant.
        $container->register(GlobalFeedTopicResolver::class, GlobalFeedTopicResolver::class);

        $topicResolver = $config['topic_resolver'] ?? null;
        $topicResolver = \is_string($topicResolver) && '' !== $topicResolver
            ? $topicResolver
            : GlobalFeedTopicResolver::class;

        $container->setAlias(FeedTopicResolverInterface::class, $topicResolver)->setPublic(true);

        $container->register(EntityChangePublisher::class, EntityChangePublisher::class)
            ->setArguments([
                new Reference('mercure.hub.default'),
                new Reference(FeedTopicResolverInterface::class),
                new Reference('security.helper', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('api_platform.iri_converter', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('doctrine.event_listener', ['event' => Events::onFlush])
            ->addTag('doctrine.event_listener', ['event' => Events::postFlush])
            ->setPublic(true);

        if (false !== ($config['buffering'] ?? true)) {
            $this->registerBuffering($container);
        }

        $this->registerSubscriberCookieFactory($container, $config);
        $this->registerJwtTokenExtension($container);
    }

    /**
     * La fonction Twig `jwt_token()` n'a de sens qu'avec un émetteur de JWT applicatif — lexik —
     * et un moteur Twig. Absents, la fonction n'existe pas : un gabarit qui l'appelle échoue au
     * rendu, ce qui est volontairement bruyant. Rendre une chaîne vide se lirait « non
     * connecté ».
     */
    private function registerJwtTokenExtension(ContainerBuilder $container): void
    {
        if (!interface_exists(JWTTokenManagerInterface::class) || !class_exists(AbstractExtension::class)) {
            return;
        }

        $container->register(JwtTokenExtension::class, JwtTokenExtension::class)
            ->setArguments([
                new Reference('security.helper'),
                new Reference('lexik_jwt_authentication.jwt_manager'),
            ])
            ->addTag('twig.extension');
    }

    /**
     * Le décorateur et son vidangeur vont ensemble : sans le `drain()` du
     * `kernel.terminate`, la mise en tampon ne publierait jamais rien.
     */
    private function registerBuffering(ContainerBuilder $container): void
    {
        $container->register(BufferingHub::class, BufferingHub::class)
            ->setDecoratedService('mercure.hub.default')
            ->setArguments([
                new Reference(BufferingHub::class.'.inner'),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);

        $container->register(MercureDrainListener::class, MercureDrainListener::class)
            ->setArguments([
                new Reference(BufferingHub::class),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            // Priorité basse : on vidange après tout le reste, quand la réponse est partie.
            ->addTag('kernel.event_listener', ['event' => KernelEvents::TERMINATE, 'method' => 'onKernelTerminate', 'priority' => -100])
            ->addTag('kernel.event_listener', ['event' => ConsoleEvents::TERMINATE, 'method' => 'onConsoleTerminate', 'priority' => -100]);
    }

    /**
     * Pas de secret, pas de fabrique : signer un jeton d'abonnement avec autre chose que le
     * secret du hub produit un jeton que le hub rejette, ce qui se lit « le temps réel ne
     * marche pas » et non « la configuration est fausse ».
     *
     * @param array<mixed> $config
     */
    private function registerSubscriberCookieFactory(ContainerBuilder $container, array $config): void
    {
        $secret = $config['jwt_secret'] ?? null;

        if (!\is_string($secret) || '' === $secret) {
            return;
        }

        $container->register('jul6art_push.mercure.token_factory', LcobucciFactory::class)
            ->setArguments([$secret]);

        $container->register(SubscriberCookieFactory::class, SubscriberCookieFactory::class)
            ->setArguments([
                new Reference('jul6art_push.mercure.token_factory'),
                $config['cookie_name'] ?? 'mercureAuthorization',
                $config['cookie_path'] ?? '/.well-known/mercure',
                $config['token_lifetime'] ?? 3600,
                false !== ($config['cookie_secure'] ?? true),
            ])
            ->setPublic(true);
    }

    /**
     * @throws \RuntimeException if CoreBundle is not registered
     */
    #[\Override]
    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (!\is_array($bundles) || !isset($bundles['CoreBundle'])) {
            throw new \RuntimeException(\sprintf('"%s" bundle is required', CoreBundle::class));
        }

        $config = $this->resolveConfig($container);

        foreach ($config as $key => $parameter) {
            $container->setParameter(\sprintf('%s.%s', $this->getAlias(), $key), $parameter);
        }

        $container->prependExtensionConfig('framework', [
            'messenger' => [
                'transports' => [
                    'sync' => 'sync://',
                    'async_priority_high' => [
                        'dsn' => $config['transport_method'],
                        'options' => [
                            'queue_name' => 'high',
                        ],
                    ],
                    'async_priority_low' => [
                        'dsn' => $config['transport_method'],
                        'options' => [
                            'queue_name' => 'low',
                        ],
                    ],
                ],
                'routing' => $this->buildRouting($config),
            ],
        ]);
    }

    /**
     * @param PushConfig $config
     *
     * @return array<string, string>
     */
    private function buildRouting(array $config): array
    {
        $routing = $config['routing'];

        if ($config['async']) {
            $routing[Update::class] = 'async_priority_high';
            $routing[EntityAsyncEvent::class] = 'async_priority_high';

            return $routing;
        }

        $routing[EntityAsyncEvent::class] = 'sync';

        return $routing;
    }

    /**
     * Normalises the processed configuration into a shape the rest of the class can
     * rely on: Symfony's config layer only guarantees an untyped array.
     *
     * @return PushConfig
     */
    private function resolveConfig(ContainerBuilder $container): array
    {
        $configs = $container->resolveEnvPlaceholders($container->getExtensionConfig($this->getAlias()), true);

        $config = $this->processConfiguration(new Configuration(), \is_array($configs) ? $configs : []);

        return [
            'async' => false !== ($config['async'] ?? true),
            'enabled' => false !== ($config['enabled'] ?? true),
            'transport_type' => self::asString($config['transport_type'] ?? null),
            'transport_method' => self::asString($config['transport_method'] ?? null),
            'routing' => self::asRouting($config['routing'] ?? null),
        ];
    }

    private static function asString(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    /**
     * @return array<string, string>
     */
    private static function asRouting(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $routing = [];

        foreach ($value as $message => $transport) {
            if (\is_string($message) && \is_string($transport)) {
                $routing[$message] = $transport;
            }
        }

        return $routing;
    }
}
