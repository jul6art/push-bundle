<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Mercure;

use Jul6Art\PushBundle\Mercure\BufferingHub;
use Jul6Art\PushBundle\Mercure\MercureDrainListener;
use Jul6Art\PushBundle\Tests\Fixtures\Mercure\FailingHub;
use Jul6Art\PushBundle\Tests\Fixtures\Mercure\RecordingHub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Mercure\Update;

/**
 * Ce que ce décorateur garantit, et pourquoi il existe : une écriture en base réussie ne doit
 * jamais devenir un 500 parce qu'un hub temps réel est lent ou éteint.
 */
#[CoversClass(BufferingHub::class)]
#[CoversClass(MercureDrainListener::class)]
final class BufferingHubTest extends TestCase
{
    public function testPublishingDuringTheRequestTouchesNothing(): void
    {
        $inner = new RecordingHub();
        $hub = new BufferingHub($inner, new CollectingLogger());

        $hub->publish(new Update('/feed', '{}'));
        $hub->publish(new Update('/feed', '{}'));

        self::assertSame([], $inner->published, 'Rien ne doit partir sur le réseau pendant la requête.');
    }

    /**
     * L'API Mercure impose que `publish()` rende un identifiant d'événement. On en fabrique un,
     * faute de réponse du hub — le vrai identifiant est perdu, ce qui casserait un code qui
     * corrélerait dessus.
     */
    public function testPublishStillReturnsAnEventId(): void
    {
        $id = new BufferingHub(new RecordingHub(), new CollectingLogger())->publish(new Update('/feed', '{}'));

        self::assertNotSame('', $id);
    }

    public function testDrainSendsEverythingItHeld(): void
    {
        $inner = new RecordingHub();
        $hub = new BufferingHub($inner, new CollectingLogger());

        $hub->publish(new Update('/feed', '{"a":1}'));
        $hub->publish(new Update('/other', '{"b":2}'));

        self::assertSame(['sent' => 2, 'failed' => 0], $hub->drain());
        self::assertCount(2, $inner->published);
        self::assertSame(['/feed', '/other'], $inner->topics());
    }

    public function testDrainingTwiceDoesNotResend(): void
    {
        $inner = new RecordingHub();
        $hub = new BufferingHub($inner, new CollectingLogger());
        $hub->publish(new Update('/feed', '{}'));

        $hub->drain();

        self::assertSame(['sent' => 0, 'failed' => 0], $hub->drain());
        self::assertCount(1, $inner->published);
    }

    /**
     * Le cœur du sujet : un hub qui échoue est **journalisé et avalé**. La requête a déjà été
     * répondue quand le drain a lieu ; propager l'erreur ne servirait qu'à salir un log de
     * terminaison.
     */
    public function testAFailingHubIsLoggedAndSwallowed(): void
    {
        $logger = new CollectingLogger();
        $inner = new FailingHub();
        $hub = new BufferingHub($inner, $logger);

        $hub->publish(new Update('/feed', '{}'));

        self::assertSame(['sent' => 0, 'failed' => 1], $hub->drain());
        self::assertSame(1, $inner->attempts);
        self::assertContains('mercure.publish_failed', $logger->messages);
    }

    /**
     * Une mise à jour qui échoue ne doit pas emporter les suivantes : chacune est tentée pour
     * elle-même.
     */
    public function testOneFailureDoesNotDropTheRest(): void
    {
        $inner = new RecordingHub(failOnTopics: ['/boom']);
        $hub = new BufferingHub($inner, new CollectingLogger());
        $hub->publish(new Update('/boom', '{}'));
        $hub->publish(new Update('/feed', '{}'));

        self::assertSame(['sent' => 1, 'failed' => 1], $hub->drain());
        self::assertSame(['/feed'], $inner->topics());
    }

    public function testTheHubIsDrainedOnAConsoleCommandToo(): void
    {
        $inner = new RecordingHub();
        $hub = new BufferingHub($inner, new CollectingLogger());
        $hub->publish(new Update('/feed', '{}'));

        new MercureDrainListener($hub, new CollectingLogger())->onConsoleTerminate();

        self::assertCount(1, $inner->published, 'Une commande CLI doit vidanger comme une requête.');
    }

    public function testAPartialDrainIsReportedByTheListener(): void
    {
        $logger = new CollectingLogger();
        $hub = new BufferingHub(new FailingHub(), new CollectingLogger());
        $hub->publish(new Update('/feed', '{}'));

        new MercureDrainListener($hub, $logger)->onKernelTerminate();

        self::assertContains('mercure.drain_partial', $logger->messages);
    }

    public function testTheDecoratorForwardsTheHubMetadata(): void
    {
        $inner = new RecordingHub();
        $hub = new BufferingHub($inner, new CollectingLogger());

        self::assertSame($inner->getUrl(), $hub->getUrl());
        self::assertSame($inner->getPublicUrl(), $hub->getPublicUrl());
        self::assertNull($hub->getFactory());
    }
}

/**
 * Un logger qui retient les messages, pour affirmer qu'un échec de hub laisse une trace — sinon
 * « avalé » voudrait dire « perdu ».
 */
final class CollectingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $messages = [];

    /**
     * La signature suit `LoggerInterface` à la lettre : `psr/log` en version minimale ne type ni
     * `$level` ni `$message`, et typer ici casse la compatibilité — le job « lowest deps » de la
     * CI l'a montré là où une installation locale en psr/log 3 ne pouvait pas.
     *
     * @param string|\Stringable $message
     * @param array<mixed>       $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }
}
