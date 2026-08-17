<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Message\Constants;

/**
 * Class EntityAsyncEventType.
 *
 * Kept as string constants rather than turned into an enum: these values travel
 * inside serialised Messenger payloads, so changing their type would break messages
 * already sitting in a queue.
 */
final class EntityAsyncEventType
{
    public const string ENTITY_ASYNC_EVENT_TYPE_CREATED = 'app.notification_message_type.created';
    public const string ENTITY_ASYNC_EVENT_TYPE_DELETED = 'app.notification_message_type.deleted';
    public const string ENTITY_ASYNC_EVENT_TYPE_EDITED = 'app.notification_message_type.edited';
    public const string ENTITY_ASYNC_EVENT_TYPE_VIEWED = 'app.notification_message_type.viewed';
}
