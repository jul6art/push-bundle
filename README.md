<p align="center">
    <a href="https://devinthehood.com"><img src="https://github.com/jul6art/symfony-skeleton/blob/master/assets/img/devinthehood.png?raw=true" alt="logo dev in the hood"></a>
</p>

<p align="center">
    <a href="https://opensource.org/licenses/MIT" target="_blank"><img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="License"></a>
    <a href="https://github.com/jul6art/symfony-skeleton" target="_blank"><img src="https://img.shields.io/static/v1?label=stable&message=v1+coming+soon&color=orange" alt="Version"></a>
</p>

jul6art/push-bundle
===================
Symfony real-time notification bundle
-------------------------------------

> :warning: Work in progress so keep calm. The good news: this is maintained!

Requirements
------------

* **php ^7.4**
* **symfony 4.4 || ^5.0**
* **mercure**

Installation
------------

```console
composer require jul6art/push-bundle
```

Then Download the [https://github.com/dunglas/mercure/releases/tag/v0.3.3](mercure) hub depending on your operating system and install it in the root of your project. 
For each release, the assets section list operating systems implementations. The folder must contain the mercure bin. Rename this folder **mercure**.

EventSource Polyfill
--------------------

```console
npm install event-source-polyfill
```

and import it on client side to make push works on IE and Edge

Generate new JWT token (Optionnal)
----------------------------------

Go to [http://jwt.io](jwt.io) and put your future mercure secret key (default it's **!ChangeMe!**) in the **verify signature** textarea and this array in the **payload** textarea

```console
{
    "mercure": {
        "publish": []
    }
}
```

Because the array is empty, the Symfony app will only be authorized to publish public updates (see the [https://symfony.com/doc/current/mercure.html#authorization](authorization) section of symfony/mercure-bundle for further information).

THen store the generated token in your .env file as **MERCURE_JWT_TOKEN** parameter

Start mercure server
--------------------

The default token is signed with the secret key: !ChangeMe!

CORS_ALLOWED_ORIGINS is the client URL and port. It can be * or a list of domains
ADDR is the server url and 3000 is the port for mercure server

```console
JWT_KEY='!ChangeMe!' ADDR='localhost:3000' ALLOW_ANONYMOUS=1 CORS_ALLOWED_ORIGINS="http://localhost:80" ./mercure/mercure
```

> :warning: By default, push messages are async so you need to launch a crawler in a terminal to dequeue messages and send it

```console
bin/console messenger:consume async_priority_high --time-limit 600
```

Using with api-platform
-----------------------

Server side

```php
/**
 * @ApiResource(mercure=true)
 */
class SomeTopic {}
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

/**
 * Class RequestEventSubscriber
 */
class SomeService
{
    use PusherAwareTrait;

    public function function(): void
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
        PathToSomeDispatcher: async_priority_high
```

Can be **async_priority_high** or **async_priority_low** or **sync**

Asyncable Annotation (Optionnal)
--------------------------------

My Entity

```php
/**
 * @ORM\Entity(repositoryClass=MyClassRepository::class)
 * @Asyncable(eventClass="App\Event\MyClassEvent")
 */
class MyClass
{

}
```

My EntityEvent

```php
<?php

namespace App\Event;

use App\Entity\MyClass;
use Jul6Art\CoreBundle\Event\AbstractEvent;

/**
 * Class MyClassEvent
 */
class MyClassEvent extends AbstractEvent
{
    public const CREATED = 'event.my_class.created';
    public const DELETED = 'event.my_class.deleted';
    public const EDITED = 'event.my_class.edited';
    public const VIEWED = 'event.my_class.viewed';

    /**
     * @var MyClass
     */
    private $myClass;

    public function __construct(MyClass $myClass)
    {
        parent::__construct();

        $this->myClass = $myClass;
    }

    public function getMyClass(): MyClass
    {
        return $this->myClass;
    }

    public function setMyClass(MyClass $myClass): MyClassEvent
    {
        $this->myClass = $myClass;
        return $this;
    }
}
```

All actions in listeners who listen these event class consts whill be async

> You can also specify which doctrine events you want to track

```php
/**
 * @ORM\Entity(repositoryClass=MyClassRepository::class)
 * @Asyncable(eventClass="App\Event\MyClassEvent", events={"postLoad", "postPersist"})
 */
class MyClass
{

}
```

Available events are

* postLoad
* postPersist
* postUpdate
* preRemove

License
-------

The Push Bundle is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

&copy; 2021 [dev in the hood](https://devinthehood.com)
