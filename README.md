<p align="center">
    <a href="https://devinthehood.com"><img src="https://github.com/jul6art/symfony-skeleton-generator/blob/master/public/img/logo.png?raw=true" alt="logo dev in the hood" width="400"></a>
</p>

<p align="center">
    <a href="https://opensource.org/licenses/MIT" target="_blank"><img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="License"></a>
    <img src="https://img.shields.io/static/v1?label=stable&message=v2&color=orange" alt="Version">
</p>

jul6art/push-bundle
===================
Symfony real-time notification bundle
-------------------------------------

> :warning: Work in progress so keep calm. The good news: this is maintained!

Requirements
------------

* **php ^8.5**
* **symfony ^7.4 || ^8.0**
* **jul6art/core-bundle ^2.0**
* **mercure** (symfony/mercure-bundle ^0.3)

Installation
------------

```shell
composer require jul6art/push-bundle
```

Then Download the [mercure hub](https://github.com/dunglas/mercure/releases/tag/v0.3.3) depending on your operating system and install it in the root of your project. 
For each release, the assets section list operating systems implementations. The folder must contain the mercure bin. Rename this folder **mercure**.

EventSource Polyfill
--------------------

```shell
npm install event-source-polyfill
```

and import it on client side to make push works on IE and Edge

Generate new JWT token (Optionnal)
----------------------------------

Go to [jwt.io](http://jwt.io) and put your future mercure secret key (default it's **!ChangeMe!**) in the **verify signature** textarea and this array in the **payload** textarea

```json
{
    "mercure": {
        "publish": []
    }
}
```

Because the array is empty, the Symfony app will only be authorized to publish public updates (see the [authorization](https://symfony.com/doc/current/mercure.html#authorization) section of symfony/mercure-bundle for further information).

THen store the generated token in your .env file as **MERCURE_JWT_TOKEN** parameter

Start mercure server
--------------------

The default token is signed with the secret key: !ChangeMe!

CORS_ALLOWED_ORIGINS is the client URL and port. It can be * or a list of domains
ADDR is the server url and 3000 is the port for mercure server

```shell
JWT_KEY='!ChangeMe!' ADDR='localhost:3000' ALLOW_ANONYMOUS=1 CORS_ALLOWED_ORIGINS="http://localhost:80" ./mercure/mercure
```

> :warning: By default, push messages are async so you need to launch a crawler in a terminal to dequeue messages and send it

```shell
bin/console messenger:consume async_priority_high --time-limit 600
```

Using with api-platform
-----------------------

Server side

```php
use ApiPlatform\Metadata\ApiResource;

#[ApiResource(mercure: true)]
class SomeTopic
{
}
```

Client side

```javascript
import {EventSourcePolyfill} from "../polyfills/Polyfills";

export default class MercureProvider {
    provide = () => {
        const publishUrl = new URL('http://publish.url:3000/hub');
        publishUrl.searchParams.append("topic", "/some_topic");
        publishUrl.searchParams.append("topic", "/some_topic/{id}");

        const es = new EventSourcePolyfill(publishUrl, {
            headers: {
                'Authorization': 'Bearer ' + YOUR_MERCURE_JWT_TOKEN
            }
        });
        es.onmessage = e => {
            const data = JSON.parse(e.data);

            const regex = /\/api\/(?<type>\w+)\//gm;

            const match = regex.exec(data['@id']);

            if (null !== match) {
                const event = new CustomEvent(match.groups.type, { "data": data });
                document.dispatchEvent(event);
            }

        };
    }
};

// somewhere else
document.addEventListener('...', function() {
  // what you need
});
```

Using without api-platform
--------------------------

Server side

```php
use Jul6Art\PushBundle\Service\Traits\PusherAwareTrait;

class SomeService
{
    use PusherAwareTrait;

    public function notify(): void
    {
        $this->pusher->push('/some/topic', ['test' => true]);
    }
}
```

Sync (Optionnal)
----------------

```yaml
push:
    async: false
```

Other messenger messages (Optionnal)
------------------------------------

```yaml
push:
    routing:
        'PathToSomeAsyncMessage': async_priority_high
```

Can be **async_priority_high** or **async_priority_low** or **sync**

Asyncable Attribute (Optionnal)
-------------------------------

The Asyncable annotation is now a PHP attribute: `Jul6Art\PushBundle\Attribute\Asyncable`.

My Entity

```php
use App\Event\MyClassEvent;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\PushBundle\Attribute\Asyncable;

#[ORM\Entity(repositoryClass: MyClassRepository::class)]
#[Asyncable(eventClass: MyClassEvent::class)]
class MyClass
{
}
```

My EntityEvent

The event class must accept the entity as its only constructor argument and must
extend `Jul6Art\CoreBundle\Event\AbstractEvent`.

```php
<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\MyClass;
use Jul6Art\CoreBundle\Event\AbstractEvent;

class MyClassEvent extends AbstractEvent
{
    public const string CREATED = 'event.my_class.created';
    public const string DELETED = 'event.my_class.deleted';
    public const string EDITED = 'event.my_class.edited';
    public const string VIEWED = 'event.my_class.viewed';

    public function __construct(private MyClass $myClass)
    {
        parent::__construct();
    }

    public function getMyClass(): MyClass
    {
        return $this->myClass;
    }

    public function setMyClass(MyClass $myClass): static
    {
        $this->myClass = $myClass;

        return $this;
    }
}
```

All actions in listeners who listen these event class consts will be async

> You can also specify which doctrine events you want to track

```php
#[ORM\Entity(repositoryClass: MyClassRepository::class)]
#[Asyncable(eventClass: MyClassEvent::class, events: ['postLoad', 'postPersist'])]
class MyClass
{
}
```

Available events are

* postLoad
* postPersist
* postUpdate
* preRemove

Real-time entity feed
---------------------

Everything above pushes messages you write yourself. This part pushes them **for** you: annotate
an entity, and every insert, update and delete on it emits a small Mercure update.

```php
use Jul6Art\PushBundle\Attribute\BroadcastableEntity;

#[ORM\Entity]
#[BroadcastableEntity]
class Page { … }

#[BroadcastableEntity(type: 'CmsPage', changedFields: ['title', 'publishedAt'])]
class BlogPost { … }
```

`Mercure\EntityChangePublisher` reads the attribute on Doctrine's `onFlush` / `postFlush`. There
is nothing else to wire — but there are three things to know before relying on it.

### The payload carries no business data, on purpose

```json
{"type":"Page","iri":"/api/pages/12","id":12,"action":"updated",
 "topic":"/organizations/7/feed","at":"2026-08-20T09:12:44+00:00","actorId":3,"etag":"a1b2c3d4e5f6",
 "changedFields":["title"]}
```

A subscriber receives **everything** published on a topic it is allowed to listen to, without
passing through a voter. Putting a field value in the payload would therefore broadcast it past
every access rule you have. Consumers refetch through the API, which is where those rules live.

`changedFields` is a **whitelist**, not a diff: only the intersection of what actually changed
with what the attribute names is exposed, so a client can decide whether a change concerns it
without learning about the fields you did not list.

### The topic is your decision

The bundle publishes on whatever `Mercure\FeedTopicResolverInterface` returns, and ships
`GlobalFeedTopicResolver` — everything on `/global/feed`. That is right for a single tenant and a
leak for two.

```php
final class OrganizationFeedTopicResolver implements FeedTopicResolverInterface
{
    public function resolveTopic(object $entity): string
    {
        if ($entity instanceof Organization) {
            return '/organizations/'.$entity->getId().'/feed';
        }

        if (method_exists($entity, 'getOrganization')) {
            $organization = $entity->getOrganization();

            // A tenant-scoped row with no tenant — a super-admin account, an orphan.
            return $organization instanceof Organization
                ? '/organizations/'.$organization->getId().'/feed'
                : '/admin/feed';
        }

        return '/global/feed';
    }
}
```

```yaml
push:
    mercure:
        topic_resolver: App\Mercure\OrganizationFeedTopicResolver
```

> ⚠️ **Do not let a tenant-scoped entity fall back to the shared topic.** Every tenant user is
> subscribed to `/global/feed`, so a row landing there leaks its existence and triggers a reload
> for everyone. The example routes it to `/admin/feed`, which only an administrator's token
> covers. This mistake is silent: the feed keeps working, for too many people.

The topic is also how a burst is grouped: more than `EntityChangePublisher::BURST_THRESHOLD`
entities of one type on one topic in a single flush collapse into a single `action: "bulk"` event
carrying a `count`. Importing 500 rows then costs one reload per subscriber instead of 500.

### A hub that is down must not break a write

Publishing inside `postFlush` means an HTTP call inside `$em->flush()`. A slow hub makes a slow
response; an unreachable one throws, and the user gets a 500 on a write that **succeeded**.

`Mercure\BufferingHub` decorates the hub so `publish()` only fills an in-memory buffer, and
`Mercure\MercureDrainListener` empties it on `kernel.terminate` — after the response has left —
and on `console.terminate` for commands. A hub failure there is logged and swallowed.

It is on by default. The trade is explicit: between the response and the drain there is a
millisecond-scale window where the client has been told "saved" and subscribers have not heard
yet, and if the process dies in between those updates are lost. No data is lost — the row is
committed — only a notification, and the next page load shows the new state anyway.

```yaml
push:
    mercure:
        buffering: false   # publish inline; a hub outage becomes a 500
```

### Letting a browser subscribe

A subscriber authenticates with a JWT carrying a `mercure.subscribe` claim, and an `EventSource`
cannot send an `Authorization` header — hence a cookie scoped to the hub path.
`Mercure\SubscriberCookieFactory` mints both:

```yaml
push:
    mercure:
        jwt_secret: '%env(MERCURE_JWT_SECRET)%'   # the secret the hub validates with
```

```php
#[Route('/organization/mercure-token', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
public function __invoke(SubscriberCookieFactory $factory): Response
{
    $topics = $this->topicsFor($this->getUser());          // ← your authorisation decision
    $token = $factory->createToken($topics);

    $response = new JsonResponse(['subscribed' => $topics, 'token' => $token]);
    $response->headers->setCookie($factory->createCookie($token));

    return $response;
}
```

**Which topics an account may subscribe to stays in your application** — it is an authorisation
decision and the claim is what enforces it on the hub. Hand the same list back in the response so
the front end subscribes to exactly what the token allows; deriving it twice is how the two
drift.

> ⚠️ **`*` is a wildcard on both sides.** A claim of `['*']` covers every topic, present and
> future. Convenient for an administrator, a tenant leak for anyone else.

Without `jwt_secret` the factory is not registered — signing a subscriber token with anything but
the hub's own secret produces a token the hub rejects, which reads as "real-time is broken"
rather than "the configuration is wrong".

### Messenger routing and buffering are alternatives, not layers

With `push.async: true` — the default — this bundle routes
`Symfony\Component\Mercure\Update` to the `async_priority_high` transport, so a worker
publishes it instead of the request. That is a second way of solving the same problem the
buffering hub solves, and running both is not twice as safe:

```yaml
push:
    async: false        # Update is not routed; the (buffering) hub publishes directly
```

> ⚠️ **Routing to `async_priority_high` requires a worker consuming that transport.** An
> application whose supervisor only runs `messenger:consume async` will queue every real-time
> update into a transport nobody drains, and the feed stops — **silently**, with the rows
> committed and the queue growing. Check your worker before leaving `async: true`, and remember
> that an application with no `#[Asyncable]` entity has nothing else to gain from it.

### The trap worth repeating

**No hub, no publishing — silently.** The whole real-time stack is registered only when the
application configures `mercure.hubs`; without it, `#[BroadcastableEntity]` emits nothing and
reports nothing. That is deliberate — an application can install this bundle for its Messenger
side alone — but it means "my events never arrive" and "I never configured a hub" look identical.

Quality assurance
-----------------

```shell
composer qa           # coding standards, Rector, static analysis and tests
composer test         # PHPUnit
composer phpstan      # PHPStan, level max
composer cs           # PHP-CS-Fixer, writes the fixes
composer rector       # Rector, writes the fixes
```
License
-------

The Push Bundle is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

&copy; 2026 [jul6art](https://devinthehood.com)
