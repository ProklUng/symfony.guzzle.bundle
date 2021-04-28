<?php

namespace Prokl\GuzzleBundle;

use Prokl\GuzzleBundle\DependencyInjection\CompilerPass\LoaderPass;
use Prokl\GuzzleBundle\DependencyInjection\CompilerPass\MiddlewarePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Csa Guzzle Bundle.
 */
final class CsaGuzzleBundle extends Bundle
{
    /**
     * @inheritDoc
     */
    public function build(ContainerBuilder $container) : void
    {
        parent::build($container);

        $container->addCompilerPass(new MiddlewarePass());
        $container->addCompilerPass(new LoaderPass());
    }
}
