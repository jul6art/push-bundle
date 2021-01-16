<?php

namespace Jul6Art\PushBundle\Service;

use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Class Pusher
 */
class Pusher
{
    /**
     * @var bool
     */
    protected $async;

    /**
     * @var bool
     */
    protected $enabled;

    /**
     * @var PublisherInterface
     */
    protected $publisher;

    /**
     * @var MessageBusInterface
     */
    protected $bus;

    /**
     * @required
     *
     * @param PublisherInterface $publisher
     */
    public function setPublisher(PublisherInterface $publisher): void
    {
        $this->publisher = $publisher;
    }

    /**
     * @required
     *
     * @param MessageBusInterface $bus
     */
    public function setBus(MessageBusInterface $bus): void
    {
        $this->bus = $bus;
    }

    /**
     * Pusher constructor.
     * @param bool $async
     * @param bool $enabled
     */
    public function __construct(bool $async, bool $enabled)
    {
        $this->async = $async;
        $this->enabled = $enabled;
    }

    /**
     * @param string $url
     * @param iterable $data
     */
    public function push(string $url, iterable $data = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $update = new Update($url, json_encode($data));

        if ($this->async) {
            $this->bus->dispatch($update);
        } else {
            $this->publisher->__invoke($update);
        }
    }
}
