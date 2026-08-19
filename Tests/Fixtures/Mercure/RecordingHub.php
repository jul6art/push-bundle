<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Fixtures\Mercure;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Update;

/**
 * Un hub qui n'ouvre aucune connexion et retient ce qu'on lui donne. C'est ce qui permet
 * d'affirmer quelque chose sur le **contenu** publié plutôt que sur le fait qu'un appel a eu
 * lieu.
 */
final class RecordingHub implements HubInterface
{
    /** @var list<Update> */
    public array $published = [];

    /**
     * @param list<string> $failOnTopics topics dont la publication doit échouer, pour vérifier
     *                                   qu'une mise à jour en erreur n'emporte pas les autres
     */
    public function __construct(private readonly array $failOnTopics = [])
    {
    }

    public function publish(Update $update): string
    {
        foreach ($update->getTopics() as $topic) {
            if (\in_array($topic, $this->failOnTopics, true)) {
                throw new \RuntimeException(\sprintf('Refusing %s', $topic));
            }
        }

        $this->published[] = $update;

        return 'recorded-'.\count($this->published);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payloads(): array
    {
        return array_map(
            static function (Update $update): array {
                /** @var array<string, mixed> $decoded */
                $decoded = json_decode($update->getData(), true, 512, \JSON_THROW_ON_ERROR);

                return $decoded;
            },
            $this->published,
        );
    }

    /**
     * @return list<string>
     */
    public function topics(): array
    {
        $topics = [];

        foreach ($this->published as $update) {
            foreach ($update->getTopics() as $topic) {
                // `Update::getTopics()` n'est pas typé plus finement que `array` : on restreint
                // ici plutôt que de l'affirmer sans preuve.
                if (\is_string($topic)) {
                    $topics[] = $topic;
                }
            }
        }

        return $topics;
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
