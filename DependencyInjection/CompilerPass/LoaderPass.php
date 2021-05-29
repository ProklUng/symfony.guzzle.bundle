<?php

namespace Prokl\GuzzleBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Csa Guzzle definition loaders compiler pass.
 */
class LoaderPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container) : void
    {
        $ids = $container->findTaggedServiceIds('csa_guzzle.description_loader');

        if (count($ids) === 0) {
            return;
        }

        $resolverDefinition = $container->findDefinition('csa_guzzle.description_loader.resolver');

        $loaders = [];

        /**
         * @var string $id
         * @var object $options
         */
        foreach ($ids as $id => $options) {
            $loaders[] = new Reference($id);
        }

        $resolverDefinition->setArguments([$loaders]);
    }
}
