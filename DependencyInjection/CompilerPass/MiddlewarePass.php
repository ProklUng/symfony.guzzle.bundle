<?php

namespace Prokl\GuzzleBundle\DependencyInjection\CompilerPass;

use GuzzleHttp\HandlerStack;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Csa Guzzle middleware compiler pass.
 */
class MiddlewarePass implements CompilerPassInterface
{
    public const MIDDLEWARE_TAG = 'csa_guzzle.middleware';

    public const CLIENT_TAG = 'csa_guzzle.client';

    /**
     * @param ContainerBuilder $container Container.
     *
     * @return void
     */
    public function process(ContainerBuilder $container) : void
    {
        $middleware = $this->findAvailableMiddleware($container);

        $this->registerMiddleware($container, $middleware);
    }

    /**
     * Fetches the list of available middleware.
     *
     * @param ContainerBuilder $container Container.
     *
     * @return array
     */
    private function findAvailableMiddleware(ContainerBuilder $container) : array
    {
        $services = $container->findTaggedServiceIds(self::MIDDLEWARE_TAG);
        $middleware = [];

        /**
         * @var string $id
         * @var array $tags
         */
        foreach ($services as $id => $tags) {
            if (count($tags) > 1) {
                throw new \LogicException(sprintf('Middleware should only use a single \'%s\' tag', self::MIDDLEWARE_TAG));
            }

            // @phpstan-ignore-next-line
            if (!isset($tags[0]['alias'])) {
                throw new \LogicException(sprintf('The \'alias\' attribute is mandatory for the \'%s\' tag', self::MIDDLEWARE_TAG));
            }

            // @phpstan-ignore-next-line
            $priority = isset($tags[0]['priority']) ? $tags[0]['priority'] : 0;

            $middleware[$priority][] = [
                'alias' => $tags[0]['alias'],
                'id' => $id,
            ];
        }

        if (!$middleware) {
            return [];
        }

        krsort($middleware);

        /** @psalm-suppress MixedReturnStatement */
        return call_user_func_array('array_merge', $middleware);
    }

    /**
     * Sets up handlers and registers middleware for each tagged client.
     *
     * @param ContainerBuilder $container     Container.
     * @param array            $middlewareBag Middlewares.
     *
     * @return void
     */
    private function registerMiddleware(ContainerBuilder $container, array $middlewareBag) : void
    {
        if (!$middlewareBag) {
            return;
        }

        $clients = $container->findTaggedServiceIds(self::CLIENT_TAG);

        /**
         * @var string $clientId
         * @var array  $tags
         */
        foreach ($clients as $clientId => $tags) {
            if (count($tags) > 1) {
                throw new \LogicException(sprintf('Clients should use a single \'%s\' tag', self::CLIENT_TAG));
            }

            $clientMiddleware = $this->filterClientMiddleware($middlewareBag, $tags);

            if (!$clientMiddleware) {
                continue;
            }

            $clientDefinition = $container->findDefinition($clientId);

            $arguments = $clientDefinition->getArguments();

            if ($arguments) {
                $options = array_shift($arguments);
            } else {
                $options = [];
            }

            // @phpstan-ignore-next-line
            if (!isset($options['handler'])) {
                $handlerStack = new Definition(HandlerStack::class);
                $handlerStack->setFactory([HandlerStack::class, 'create']);
                $handlerStack->setPublic(false);
            } else {
                $handlerStack = $this->wrapHandlerInHandlerStack($options['handler'], $container);
            }

            $this->addMiddlewareToHandlerStack($handlerStack, $clientMiddleware);
            $options['handler'] = $handlerStack;

            array_unshift($arguments, $options);
            $clientDefinition->setArguments($arguments);
        }
    }

    /**
     * @param Reference|Definition|callable $handler   The configured Guzzle handler.
     * @param ContainerBuilder              $container The container builder.
     *
     * @return Definition
     */
    private function wrapHandlerInHandlerStack($handler, ContainerBuilder $container)
    {
        if ($handler instanceof Reference) {
            $handler = $container->getDefinition((string) $handler);
        }

        if ($handler instanceof Definition && HandlerStack::class === $handler->getClass()) {
            // no need to wrap the Guzzle handler if it already resolves to a HandlerStack
            return $handler;
        }

        $handlerDefinition = new Definition(HandlerStack::class);
        $handlerDefinition->setArguments([$handler]);
        $handlerDefinition->setPublic(false);

        return $handlerDefinition;
    }

    /**
     * @param Definition $handlerStack  Handler stack.
     * @param array      $middlewareBag Middlewares.
     *
     * @return void
     */
    private function addMiddlewareToHandlerStack(Definition $handlerStack, array $middlewareBag): void
    {
        /** @var array $middleware */
        foreach ($middlewareBag as $middleware) {
            $handlerStack->addMethodCall('push', [new Reference((string)$middleware['id']), $middleware['alias']]);
        }
    }

    /**
     * @param array $middlewareBag The list of availables middleware.
     * @param array $tags          The tags containing middleware configuration.
     *
     * @return array The list of middleware to enable for the client
     *
     * @throws LogicException When middleware configuration is invalid.
     */
    private function filterClientMiddleware(array $middlewareBag, array $tags)
    {
        // @phpstan-ignore-next-line
        if (!isset($tags[0]['middleware'])) {
            return $middlewareBag;
        }

        $clientMiddlewareList = explode(' ', (string)$tags[0]['middleware']);

        $whiteList = [];
        $blackList = [];
        foreach ($clientMiddlewareList as $middleware) {
            if ('!' === $middleware[0]) {
                $blackList[] = substr($middleware, 1);
            } else {
                $whiteList[] = $middleware;
            }
        }

        if ($whiteList && $blackList) {
            throw new LogicException('You cannot mix whitelisting and blacklisting of middleware at the same time.');
        }

        if ($whiteList) {
            return array_filter($middlewareBag, static function ($value) use ($whiteList) : bool {
                return in_array($value['alias'], $whiteList, true);
            });
        } else {
            return array_filter($middlewareBag, static function ($value) use ($blackList) : bool {
                return !in_array($value['alias'], $blackList, true);
            });
        }
    }
}
