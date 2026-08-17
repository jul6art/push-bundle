<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Class Pusher.
 *
 * Publishes through Mercure's HubInterface: PublisherInterface and its __invoke()
 * contract have been deprecated since symfony/mercure 0.5.
 */
class Pusher
{
    protected HubInterface $hub;

    protected MessageBusInterface $bus;

    public function __construct(
        protected readonly bool $async,
        protected readonly bool $enabled,
    ) {
    }

    #[Required]
    public function setHub(HubInterface $hub): void
    {
        $this->hub = $hub;
    }

    #[Required]
    public function setBus(MessageBusInterface $bus): void
    {
        $this->bus = $bus;
    }

    /**
     * @param iterable<array-key, mixed> $data
     *
     * @throws \JsonException if the payload cannot be encoded
     */
    public function push(string $url, iterable $data = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $payload = json_encode(
            $data instanceof \Traversable ? iterator_to_array($data) : $data,
            \JSON_THROW_ON_ERROR,
        );

        $update = new Update($url, $payload);

        if ($this->async) {
            $this->bus->dispatch($update);

            return;
        }

        $this->hub->publish($update);
    }
}
