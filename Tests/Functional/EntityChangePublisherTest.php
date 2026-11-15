<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\PushBundle\Mercure\BufferingHub;
use Jul6Art\PushBundle\Mercure\EntityChangePublisher;
use Jul6Art\PushBundle\Tests\Fixtures\Entity\Article;
use Jul6Art\PushBundle\Tests\Fixtures\Entity\Secret;
use Jul6Art\PushBundle\Tests\Fixtures\Entity\Widget;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Mercure\Update;

/**
 * Le publisher contre une vraie base SQLite en mémoire.
 *
 * Simuler l'unité de travail ne prouverait rien : tout ce que cette classe fait dépend d'un
 * *change set*, et un change set n'existe qu'à l'intérieur d'un vrai `flush()`.
 */
#[CoversNothing]
final class EntityChangePublisherTest extends AbstractFunctionalTestCase
{
    private EntityManagerInterface $entityManager;

    private BufferingHub $hub;

    private EntityChangePublisher $publisher;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->boot();

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        // Le décorateur du bundle est en place : c'est lui qui détient le tampon, et le lire
        // prouve du même coup que la chaîne de décoration est bien montée.
        $hub = $container->get(BufferingHub::class);
        self::assertInstanceOf(BufferingHub::class, $hub);
        $this->hub = $hub;

        $publisher = $container->get(EntityChangePublisher::class);
        self::assertInstanceOf(EntityChangePublisher::class, $publisher);
        $this->publisher = $publisher;

        new SchemaTool($this->entityManager)->createSchema(
            $this->entityManager->getMetadataFactory()->getAllMetadata(),
        );
    }

    public function testCreatingABroadcastableEntityEmitsOneUpdate(): void
    {
        $article = $this->persist(new Article('Bonjour'));

        $payload = $this->onlyPayload();
        self::assertSame('Article', $payload['type']);
        self::assertSame($article->getId(), $payload['id']);
        self::assertSame('created', $payload['action']);
    }

    /**
     * L'assertion qui compte le plus de ce lot : la charge utile ne contient **aucune donnée
     * métier**. Un abonné au topic reçoit tout ce qui y passe, sans repasser par un voter — y
     * mettre une valeur de champ serait une fuite par conception.
     */
    public function testThePayloadCarriesNoBusinessData(): void
    {
        $this->persist(new Article('Un titre confidentiel'));

        $payload = $this->onlyPayload();

        self::assertSame(
            ['type', 'iri', 'id', 'action', 'topic', 'at', 'actorId', 'etag'],
            array_keys($payload),
            'La charge utile doit se limiter à ces clés — un abonné refetch via l\'API.',
        );
        self::assertStringNotContainsString('confidentiel', json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    /**
     * `at` à la milliseconde (3.4) : un abonné compare l'instant de la publication à celui où sa
     * page a commencé à se rendre. À la seconde, une modification et la page rendue juste après sa
     * redirection tombaient dans la même seconde — on ne savait plus laquelle précédait l'autre.
     */
    public function testThePublicationIsDatedToTheMillisecond(): void
    {
        $before = microtime(true);
        $this->persist(new Article('Bonjour'));
        $after = microtime(true);

        $at = $this->onlyPayload()['at'];
        self::assertIsString($at);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:\d{2}$/', $at);

        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.vP', $at);
        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        $published = (float) $date->format('U.v');
        self::assertGreaterThanOrEqual(floor($before * 1000) / 1000, $published);
        self::assertLessThanOrEqual($after, $published);
    }

    public function testAnEntityWithoutTheAttributeIsNeverPublished(): void
    {
        $secret = $this->persist(new Secret('rien à voir'));
        $secret->setValue('toujours rien');
        $this->entityManager->flush();
        $this->entityManager->remove($secret);
        $this->entityManager->flush();

        self::assertSame([], $this->drain());
    }

    public function testTheTypeCanBeRenamedForTheClient(): void
    {
        $this->persist(new Widget());

        self::assertSame('Gadget', $this->onlyPayload()['type']);
    }

    public function testAnUpdateReportsOnlyWhitelistedChangedFields(): void
    {
        $article = $this->persist(new Article('Avant'));
        $this->drain();

        $article->setTitle('Après')->view();
        $this->entityManager->flush();

        $payload = $this->onlyPayload();
        self::assertSame('updated', $payload['action']);
        self::assertSame(['title'], $payload['changedFields'], 'viewCount est hors liste blanche.');
    }

    /**
     * Une modification qui ne touche que des champs hors liste blanche est tout de même diffusée
     * — la ligne a bougé, le client doit rafraîchir — mais sans clé `changedFields` à exploiter.
     */
    public function testAnUpdateOutsideTheWhitelistCarriesNoChangedFields(): void
    {
        $article = $this->persist(new Article('Avant'));
        $this->drain();

        $article->view();
        $this->entityManager->flush();

        self::assertArrayNotHasKey('changedFields', $this->onlyPayload());
    }

    /**
     * Une suppression douce est techniquement un UPDATE. La diffuser comme telle obligerait
     * chaque client à deviner qu'une ligne a disparu.
     */
    public function testASoftDeleteIsBroadcastAsADeletion(): void
    {
        $article = $this->persist(new Article('À supprimer'));
        $this->drain();

        $article->softDelete();
        $this->entityManager->flush();

        self::assertSame('deleted', $this->onlyPayload()['action']);
    }

    public function testAHardDeleteIsBroadcast(): void
    {
        $article = $this->persist(new Article('À effacer'));
        $this->drain();

        $this->entityManager->remove($article);
        $this->entityManager->flush();

        self::assertSame('deleted', $this->onlyPayload()['action']);
    }

    /**
     * Sans résolveur applicatif, tout part sur `/global/feed`. Correct pour un locataire unique,
     * et c'est exactement ce que le README dit de ne pas garder au-delà.
     */
    public function testTheDefaultResolverPublishesOnTheGlobalFeed(): void
    {
        $this->persist(new Article('Bonjour'));

        self::assertSame('/global/feed', $this->onlyPayload()['topic']);
    }

    /**
     * Une rafale — plus de BURST_THRESHOLD entités de même type sur le même topic — se replie en
     * un seul événement `bulk`. Sans cela, importer 500 lignes déclencherait 500 rechargements
     * de datatable chez chaque abonné.
     */
    public function testABurstCollapsesIntoASingleBulkEvent(): void
    {
        for ($i = 0; $i <= EntityChangePublisher::BURST_THRESHOLD; ++$i) {
            $this->entityManager->persist(new Article('Lot '.$i));
        }
        $this->entityManager->flush();

        $payloads = $this->drain();
        self::assertCount(1, $payloads, 'Une rafale ne doit produire qu\'un événement.');
        self::assertSame('bulk', $payloads[0]['action']);
        self::assertSame(EntityChangePublisher::BURST_THRESHOLD + 1, $payloads[0]['count']);
    }

    /**
     * Une écriture DQL ou SQL en masse contourne l'unité de travail : sans `announce()` (3.5),
     * aucun écran qui suit l'entité ne l'apprend. Le changement annoncé part comme celui d'un flush,
     * liste blanche comprise.
     */
    public function testAChangeNoFlushSawIsAnnounced(): void
    {
        $article = $this->persist(new Article('Avant'));
        $this->drain();

        $this->entityManager->createQuery('UPDATE '.Article::class.' a SET a.title = :title, a.viewCount = 3 WHERE a.id = :id')
            ->setParameter('title', 'Après')
            ->setParameter('id', $article->getId())
            ->execute();
        self::assertSame([], $this->drain(), 'Une écriture en masse ne passe par aucun flush.');

        $this->publisher->announce([$article], 'updated', ['title', 'viewCount']);

        $payload = $this->onlyPayload();
        self::assertSame('Article', $payload['type']);
        self::assertSame($article->getId(), $payload['id']);
        self::assertSame('updated', $payload['action']);
        self::assertSame(['title'], $payload['changedFields'], 'Hors liste blanche, un champ ne part jamais.');
    }

    /**
     * L'appelant d'une écriture en masse tient souvent des identifiants, pas des entités : une
     * référence suffit.
     */
    public function testAReferenceIsAnnouncedAsItsEntity(): void
    {
        $id = $this->persist(new Article('Bonjour'))->getId();
        $this->drain();
        $this->entityManager->clear();

        $reference = $this->entityManager->getReference(Article::class, $id);
        self::assertNotNull($reference);
        $this->publisher->announce([$reference], 'deleted');

        $payload = $this->onlyPayload();
        self::assertSame('Article', $payload['type']);
        self::assertSame($id, $payload['id']);
        self::assertSame('deleted', $payload['action']);
    }

    public function testAnnouncedChangesCollapseIntoABulkEventLikeAFlush(): void
    {
        $articles = [];
        for ($i = 0; $i <= EntityChangePublisher::BURST_THRESHOLD; ++$i) {
            $articles[] = new Article('Lot '.$i);
            $this->entityManager->persist(end($articles));
        }
        $this->entityManager->flush();
        $this->drain();

        $this->publisher->announce($articles);

        $payloads = $this->drain();
        self::assertCount(1, $payloads);
        self::assertSame('bulk', $payloads[0]['action']);
        self::assertSame(EntityChangePublisher::BURST_THRESHOLD + 1, $payloads[0]['count']);
    }

    /**
     * Une réconciliation SQL ou une purge de rétention ne peut pas charger ce qu'elle a changé :
     * un seul événement `bulk`, sans identifiant, dit aux abonnés du topic de relire (3.6).
     */
    public function testABatchIsAnnouncedWithoutItsEntities(): void
    {
        $representative = $this->persist(new Article('Un parmi d\'autres'));
        $this->drain();

        $this->publisher->announceBulk($representative, 250);

        $payload = $this->onlyPayload();
        self::assertSame('Article', $payload['type']);
        self::assertSame('bulk', $payload['action']);
        self::assertSame(250, $payload['count']);
        self::assertNull($payload['id'], 'Le représentant n\'est pas le lot : son identifiant ne part pas.');
    }

    public function testAnEmptyBatchPublishesNothing(): void
    {
        $this->publisher->announceBulk(new Article(), 0);
        $this->publisher->announceBulk(new Secret('rien à voir'), 12);

        self::assertSame([], $this->drain());
    }

    public function testAnEntityWithoutTheAttributeIsNeverAnnounced(): void
    {
        $secret = $this->persist(new Secret('rien à voir'));

        $this->publisher->announce([$secret]);

        self::assertSame([], $this->drain());
    }

    public function testOnlyTheActionsOfAFlushCanBeAnnounced(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->publisher->announce([new Article()], 'bulk');
    }

    public function testTheActorIsNullOutsideAnySession(): void
    {
        $this->persist(new Article('Bonjour'));

        self::assertNull($this->onlyPayload()['actorId']);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function persist(object $entity): object
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    /**
     * Vidange le tampon et rend les charges utiles. Le décorateur n'ayant pas de hub réel
     * derrière lui ici, on lit son contenu avant qu'il ne parte.
     *
     * @return list<array<string, mixed>>
     */
    private function drain(): array
    {
        $buffer = new \ReflectionProperty(BufferingHub::class, 'buffer')->getValue($this->hub);
        self::assertIsArray($buffer);

        new \ReflectionProperty(BufferingHub::class, 'buffer')->setValue($this->hub, []);

        $payloads = [];

        foreach ($buffer as $update) {
            self::assertInstanceOf(Update::class, $update);

            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($update->getData(), true, 512, \JSON_THROW_ON_ERROR);
            $payloads[] = $decoded;
        }

        return $payloads;
    }

    /**
     * @return array<string, mixed>
     */
    private function onlyPayload(): array
    {
        $payloads = $this->drain();
        self::assertCount(1, $payloads, \sprintf('Une seule mise à jour attendue, %d trouvée(s).', \count($payloads)));

        return $payloads[0];
    }
}
