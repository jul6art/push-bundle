<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle;

use Jul6Art\PushBundle\DependencyInjection\Compiler\MercureHubPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class PushBundle.
 */
class PushBundle extends Bundle
{
    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new MercureHubPass());
    }
}
