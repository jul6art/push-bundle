<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Mercure;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Jul6Art\PushBundle\Attribute\BroadcastableEntity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publishes a minimal Mercure update whenever an entity carrying #[BroadcastableEntity]
 * is inserted, updated or deleted.
 *
 * The topic comes from the application, through {@see FeedTopicResolverInterface} — this class
 * knows *that* something changed, never *who should hear about it*.
 *
 * Payload is intentionally tiny — no business data. Consumers refetch through the API, which is
 * where the access rules live.
 *
 * Behaviors:
 *   - Soft-deletes: an UPDATE that sets a previously-null `deletedAt` column to
 *     non-null is normalized to action:"deleted" so consumers can remove the row.
 *   - changedFields whitelist: when the attribute declares a whitelist, UPDATE
 *     payloads include only the intersection of actually-changed fields with
 *     that whitelist under the "changedFields" key.
 *   - Burst coalescence: when a single flush touches more than BURST_THRESHOLD
 *     entities sharing a {type, topic} pair, they are collapsed into one
 *     `action:"bulk"` event carrying a `count`.
 */
final class EntityChangePublisher
{
    public const BURST_THRESHOLD = 10;

    /** @var list<array{entity: object, action: string, changedFields: list<string>}> */
    private array $buffered = [];

    public function __construct(
        private readonly HubInterface $hub,
        private readonly FeedTopicResolverInterface $topicResolver,
        private readonly ?Security $security = null,
        private readonly ?IriConverterInterface $iriConverter = null,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->buffer($entity, 'created', []);
        }
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $changeSet = $uow->getEntityChangeSet($entity);
            [$action, $changedFields] = $this->classifyUpdate($entity, $changeSet);
            $this->buffer($entity, $action, $changedFields);
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->buffer($entity, 'deleted', []);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->buffered) {
            return;
        }

        $buffer = $this->buffered;
        $this->buffered = [];

        foreach ($this->coalesceBursts($buffer) as $item) {
            if (isset($item['bulk'])) {
                $this->publishBulk($item['representative'], $item['count']);
            } else {
                $this->publish($item['entity'], $item['action'], $item['changedFields']);
            }
        }
    }

    /**
     * @param list<string> $changedFields
     */
    private function buffer(object $entity, string $action, array $changedFields): void
    {
        $reflection = new \ReflectionClass($entity);
        if ([] === $reflection->getAttributes(BroadcastableEntity::class)) {
            return;
        }

        $this->buffered[] = [
            'entity' => $entity,
            'action' => $action,
            'changedFields' => $changedFields,
        ];
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}|\Doctrine\ORM\PersistentCollection<int|string, object>> $changeSet
     *
     * @return array{0: string, 1: list<string>}
     */
    private function classifyUpdate(object $entity, array $changeSet): array
    {
        // Soft-delete detection: deletedAt transitioning between null and non-null
        if (isset($changeSet['deletedAt']) && \is_array($changeSet['deletedAt'])) {
            [$old, $new] = $changeSet['deletedAt'];
            if (null === $old && null !== $new) {
                return ['deleted', []];
            }
            // Restore: a previously soft-deleted row becomes visible again.
            // Emitted as its own action so datatables on the consumer side
            // can treat it like a `created` (row reappears) without faking
            // the intent in a generic `updated`.
            if (null !== $old && null === $new) {
                return ['restored', []];
            }
        }

        $attribute = $this->readAttribute($entity);
        if (null === $attribute || [] === $attribute->changedFields) {
            return ['updated', []];
        }

        $changed = array_values(array_intersect(array_keys($changeSet), $attribute->changedFields));

        return ['updated', $changed];
    }

    /**
     * Groups same-{type,orgId} events above BURST_THRESHOLD into a single bulk emission.
     * Returns a heterogeneous list: either individual items or {bulk:true, representative, count}.
     *
     * @param list<array{entity: object, action: string, changedFields: list<string>}> $buffer
     *
     * @return list<array<string, mixed>>
     */
    private function coalesceBursts(array $buffer): array
    {
        $groups = [];
        foreach ($buffer as $index => $item) {
            $key = $this->groupKey($item['entity'], $item['action']);
            $groups[$key][] = $index;
        }

        $out = [];
        $consumed = [];
        foreach ($groups as $indices) {
            if (\count($indices) >= self::BURST_THRESHOLD) {
                $out[] = [
                    'bulk' => true,
                    'representative' => $buffer[$indices[0]]['entity'],
                    'count' => \count($indices),
                ];
                foreach ($indices as $i) {
                    $consumed[$i] = true;
                }
            }
        }
        foreach ($buffer as $i => $item) {
            if (!isset($consumed[$i])) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Discriminant d'un lot : type + topic + action.
     *
     * Le **topic** sert de discriminant de tenant, et pas un identifiant d'organisation lu sur
     * l'entité : c'est ce qui débarrasse cette classe de toute entité applicative, et c'est
     * aussi plus juste — deux entités qui partent sur le même topic sont exactement celles dont
     * un abonné verra la rafale.
     */
    private function groupKey(object $entity, string $action): string
    {
        $type = new \ReflectionClass($entity)->getShortName();

        return $type.'|'.$this->resolveTopic($entity).'|'.$action;
    }

    /**
     * @param list<string> $changedFields
     */
    private function publish(object $entity, string $action, array $changedFields): void
    {
        $attribute = $this->readAttribute($entity);
        if (null === $attribute) {
            return;
        }

        $reflection = new \ReflectionClass($entity);
        $type = $attribute->type ?? $reflection->getShortName();
        $id = method_exists($entity, 'getId') ? $entity->getId() : null;
        $iri = $this->resolveIri($entity);
        $topic = $this->resolveTopic($entity);

        $payload = $this->buildBasePayload($type, $iri, $id, $action, $topic);

        if ('updated' === $action && [] !== $changedFields) {
            $payload['changedFields'] = $changedFields;
        }

        $this->emit($topic, $payload);
    }

    private function publishBulk(object $representative, int $count): void
    {
        $attribute = $this->readAttribute($representative);
        if (null === $attribute) {
            return;
        }

        $reflection = new \ReflectionClass($representative);
        $type = $attribute->type ?? $reflection->getShortName();
        $topic = $this->resolveTopic($representative);

        $payload = $this->buildBasePayload($type, null, null, 'bulk', $topic);
        $payload['count'] = $count;

        $this->emit($topic, $payload);
    }

    /**
     * `topic` is echoed inside the payload so the JS bus can filter on
     * origin at consumer level (e.g. an org-scoped datatable ignores
     * `/admin/feed` or `/global/feed` events even if the EventSource
     * ever receives them).
     *
     * @return array<string, mixed>
     */
    private function buildBasePayload(string $type, ?string $iri, int|string|null $id, string $action, string $topic): array
    {
        $actorId = $this->resolveActorId();

        return [
            'type' => $type,
            'iri' => $iri,
            'id' => $id,
            'action' => $action,
            'topic' => $topic,
            'at' => new \DateTimeImmutable()->format(\DateTimeInterface::ATOM),
            'actorId' => $actorId,
            'etag' => $this->buildEtag($iri, $type, $id, $action),
        ];
    }

    /**
     * Identifiant de l'utilisateur courant, `null` hors session — une commande de console, un
     * consumer. `UserInterface` ne déclare aucun identifiant, donc on lit `getId()` quand la
     * classe en expose un : le bundle n'a pas à connaître la classe utilisateur du projet.
     */
    private function resolveActorId(): int|string|null
    {
        $user = $this->security?->getUser();

        if (null === $user || !method_exists($user, 'getId')) {
            return null;
        }

        $id = $user->getId();

        return \is_int($id) || \is_string($id) ? $id : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function emit(string $topic, array $payload): void
    {
        try {
            $json = json_encode($payload, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        $this->hub->publish(new Update($topic, $json, true));
    }

    private function readAttribute(object $entity): ?BroadcastableEntity
    {
        $reflection = new \ReflectionClass($entity);
        $attrs = $reflection->getAttributes(BroadcastableEntity::class);
        if ([] === $attrs) {
            return null;
        }

        /** @var BroadcastableEntity $instance */
        $instance = $attrs[0]->newInstance();

        return $instance;
    }

    private function resolveIri(object $entity): ?string
    {
        if (null === $this->iriConverter) {
            return null;
        }

        try {
            return $this->iriConverter->getIriFromResource($entity);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveTopic(object $entity): string
    {
        return $this->topicResolver->resolveTopic($entity);
    }

    private function buildEtag(?string $iri, string $type, int|string|null $id, string $action): string
    {
        $seed = ($iri ?? $type.'/'.($id ?? '0')).'|'.$action.'|'.microtime(true);

        return substr(hash('sha256', $seed), 0, 12);
    }
}
