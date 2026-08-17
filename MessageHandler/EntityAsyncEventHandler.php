<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\MessageHandler;

use Jul6Art\CoreBundle\Event\AbstractEvent;
use Jul6Art\CoreBundle\Service\Traits\EntityManagerAwareTrait;
use Jul6Art\CoreBundle\Service\Traits\EventDispatcherAwareTrait;
use Jul6Art\PushBundle\Attribute\Asyncable;
use Jul6Art\PushBundle\Attribute\Traits\AsyncAttributeReaderAwareTrait;
use Jul6Art\PushBundle\Message\Constants\EntityAsyncEventType;
use Jul6Art\PushBundle\Message\EntityAsyncEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Class EntityAsyncEventHandler.
 *
 * Declared through #[AsMessageHandler]: MessageHandlerInterface was deprecated in
 * Symfony 6.2 and removed in 7.0.
 */
#[AsMessageHandler]
class EntityAsyncEventHandler
{
    use AsyncAttributeReaderAwareTrait;
    use EntityManagerAwareTrait;
    use EventDispatcherAwareTrait;

    /**
     * @throws \LogicException if the configured event class is not an AbstractEvent
     */
    public function __invoke(EntityAsyncEvent $message): void
    {
        $entity = $this->entityManager->getRepository($message->getEntityClass())->find($message->getEntityId());

        if (null === $entity) {
            return;
        }

        $attribute = $this->asyncAttributeReader->getAsyncAttribute($entity);

        if (!$attribute instanceof Asyncable) {
            return;
        }

        $eventClass = $attribute->getEventClass();

        if (!is_a($eventClass, AbstractEvent::class, true)) {
            throw new \LogicException(\sprintf('The event class "%s" configured on "%s" must extend "%s".', $eventClass, $entity::class, AbstractEvent::class));
        }

        $eventName = match ($message->getType()) {
            EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_CREATED => $this->asyncAttributeReader->hasPostPersistEvent($entity) ? $eventClass::CREATED : null,
            EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_DELETED => $this->asyncAttributeReader->hasPreRemoveEvent($entity) ? $eventClass::DELETED : null,
            EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_EDITED => $this->asyncAttributeReader->hasPostUpdateEvent($entity) ? $eventClass::EDITED : null,
            // An unknown type is treated as a view, as the previous switch did.
            default => $this->asyncAttributeReader->hasPostLoadEvent($entity) ? $eventClass::VIEWED : null,
        };

        if (null === $eventName) {
            return;
        }

        $this->eventDispatcher->dispatch($this->buildEvent($eventClass, $entity, $message), $eventName);
    }

    /**
     * The event class is expected to take the entity as its only constructor
     * argument, a contract PHP cannot check on a dynamic class name; reflection keeps
     * the failure readable instead of a raw ArgumentCountError.
     *
     * @param class-string<AbstractEvent> $eventClass
     *
     * @throws \LogicException if the event cannot be built from the entity
     */
    private function buildEvent(string $eventClass, object $entity, EntityAsyncEvent $message): AbstractEvent
    {
        try {
            $event = new \ReflectionClass($eventClass)->newInstance($entity);
        } catch (\ReflectionException|\ArgumentCountError|\TypeError $exception) {
            throw new \LogicException(\sprintf('The event class "%s" must accept a "%s" as its only constructor argument.', $eventClass, $entity::class), 0, $exception);
        }

        $event->getData()->set('createdById', $message->getCreatedById());

        return $event;
    }
}
