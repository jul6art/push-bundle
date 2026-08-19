<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Fixtures\Mercure;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Update;

/**
 * Le hub en panne — le cas qui a justifié `BufferingHub`. Une connexion refusée, un 502, un
 * timeout : tout cela ressort de `publish()` sous forme d'exception, et sans tampon cela tuait
 * la requête HTTP alors que l'écriture en base avait réussi.
 */
final class FailingHub implements HubInterface
{
    public int $attempts = 0;

    public function publish(Update $update): string
    {
        ++$this->attempts;

        throw new \RuntimeException('Hub unreachable');
    }

    public function getUrl(): string
    {
        return 'https://example.test/.well-known/mercure';
    }

    public function getPublicUrl(): string
    {
        return $this->getUrl();
    }

    public function getProvider(): TokenProviderInterface
    {
        return new StaticTokenProvider('token');
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return null;
    }
}
