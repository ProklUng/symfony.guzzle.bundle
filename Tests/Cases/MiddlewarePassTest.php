<?php

namespace Prokl\GuzzleBundle\Tests\Cases;

use LogicException;
use Prokl\GuzzleBundle\DependencyInjection\CompilerPass\MiddlewarePass;
use Prokl\TestingTools\Base\BaseTestCase;
use GuzzleHttp\HandlerStack;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class MiddlewarePassTest
 * @package Prokl\GuzzleBundle\Tests\Cases
 */
class MiddlewarePassTest extends BaseTestCase
{
    /**
     * @return void
     */
    public function testAllMiddlewareAddedToTaggedClientsByDefault()
    {
        $container = $this->createContainer();
        $container->setDefinition('client', $client = $this->createClient());
        $this->createMiddleware($container, 'my_mid');
        $this->createMiddleware($container, 'my_mid2');

        $pass = new MiddlewarePass();
        $pass->process($container);

        $handlerDefinition = $client->getArgument(0)['handler'];
        $this->assertCount(2, $calls = $handlerDefinition->getMethodCalls());
        $this->assertEquals(['push', [new Reference('my_mid'), 'my_mid']], $calls[0]);
        $this->assertEquals(['push', [new Reference('my_mid2'), 'my_mid2']], $calls[1]);
    }

    /**
     * @return void
     */
    public function testSpecificMiddlewareAddedToClient()
    {
        $client = $this->createClient(['foo', 'bar']);

        $container = $this->createContainer();
        $container->setDefinition('client', $client);

        foreach (['foo', 'bar', 'qux'] as $alias) {
            $this->createMiddleware($container, $alias);
        }

        $pass = new MiddlewarePass();
        $pass->process($container);

        $handlerDefinition = $client->getArgument(0)['handler'];
        $this->assertCount(2, $calls = $handlerDefinition->getMethodCalls());
        $this->assertEquals(['push', [new Reference('foo'), 'foo']], $calls[0]);
        $this->assertEquals(['push', [new Reference('bar'), 'bar']], $calls[1]);
    }

    /**
     * @return void
     */
    public function testDisableSpecificMiddlewareForClient() : void
    {
        $client = $this->createClient(['!foo']);

        $container = $this->createContainer();
        $container->setDefinition('client', $client);

        foreach (['foo', 'bar', 'qux'] as $alias) {
            $this->createMiddleware($container, $alias);
        }

        $pass = new MiddlewarePass();
        $pass->process($container);

        $handlerDefinition = $client->getArgument(0)['handler'];
        $this->assertCount(2, $calls = $handlerDefinition->getMethodCalls());
        $this->assertEquals(['push', [new Reference('bar'), 'bar']], $calls[0]);
        $this->assertEquals(['push', [new Reference('qux'), 'qux']], $calls[1]);
    }

    /**
     * @return void
     */
    public function testForbidWhitelistingAlongWithBlacklisting() : void
    {
        $client = $this->createClient(['!foo', 'bar']);

        $container = $this->createContainer();
        $container->setDefinition('client', $client);

        foreach (['foo', 'bar', 'qux'] as $alias) {
            $this->createMiddleware($container, $alias);
        }

        $pass = new MiddlewarePass();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You cannot mix whitelisting and blacklisting of middleware at the same time');
        $pass->process($container);
    }

    /**
     * @return void
     */
    public function testServicesCanUseEitherWhitelistingOrBlacklisting() : void
    {
        $client1 = $this->createClient(['foo', 'bar']);
        $client2 = $this->createClient(['!foo', '!bar']);

        $container = $this->createContainer();
        $container->setDefinition('client1', $client1);
        $container->setDefinition('client2', $client2);

        foreach (['foo', 'bar', 'qux'] as $alias) {
            $this->createMiddleware($container, $alias);
        }

        $pass = new MiddlewarePass();
        $pass->process($container);

        $handlerDefinition = $client1->getArgument(0)['handler'];
        $this->assertCount(2, $calls = $handlerDefinition->getMethodCalls());
        $this->assertEquals(['push', [new Reference('foo'), 'foo']], $calls[0]);
        $this->assertEquals(['push', [new Reference('bar'), 'bar']], $calls[1]);

        $handlerDefinition = $client2->getArgument(0)['handler'];
        $this->assertCount(1, $calls = $handlerDefinition->getMethodCalls());
        $this->assertEquals(['push', [new Reference('qux'), 'qux']], $calls[0]);
    }

    /**
     * @return void
     */
    public function testMiddlewareWithPriority()
    {
        $client = $this->createClient();

        $container = $this->createContainer();
        $container->setDefinition('client', $client);

        foreach (['foo' => 0, 'bar' => 10, 'qux' => -1000] as $alias => $priority) {
            $this->createMiddleware($container, $alias, $priority);
        }

        $pass = new MiddlewarePass();
        $pass->process($container);

        $handlerDefinition = $client->getArgument(0)['handler'];
        $this->assertCount(3, $calls = $handlerDefinition->getMethodCalls());
        $this->assertEquals(['push', [new Reference('bar'), 'bar']], $calls[0]);
        $this->assertEquals(['push', [new Reference('foo'), 'foo']], $calls[1]);
        $this->assertEquals(['push', [new Reference('qux'), 'qux']], $calls[2]);
    }

    /**
     * @return void
     */
    public function testNoMiddleware()
    {
        $client = $this->createClient();

        $container = $this->createContainer();
        $container->setDefinition('client', $client);

        $pass = new MiddlewarePass();
        $pass->process($container);

        $this->assertCount(0, $client->getArguments());
    }

    /**
     * @return void
     */
    public function testCustomHandlerStackIsKeptAndMiddlewareAdded()
    {
        $handler = new Definition(HandlerStack::class);
        $client = $this->createClient([], $handler);
        $container = $this->createContainer();
        $container->setDefinition('client', $client);

        foreach (['foo' => 0, 'bar' => 10, 'qux' => -1000] as $alias => $priority) {
            $this->createMiddleware($container, $alias, $priority);
        }

        $pass = new MiddlewarePass();
        $pass->process($container);

        $clientHandler = $client->getArgument(0)['handler'];
        $this->assertSame($handler, $clientHandler);
        $this->assertSame(HandlerStack::class, $clientHandler->getClass());
        $this->assertTrue($clientHandler->hasMethodCall('push'));
    }

    /**
     * @return void
     */
    public function testCustomHandlerCallableIsWrappedAndMiddlewareAdded()
    {
        $handler = function () {
        };
        $client = $this->createClient([], $handler);
        $container = $this->createContainer();
        $container->setDefinition('client', $client);

        foreach (['foo' => 0, 'bar' => 10, 'qux' => -1000] as $alias => $priority) {
            $this->createMiddleware($container, $alias, $priority);
        }

        $pass = new MiddlewarePass();
        $pass->process($container);

        $clientHandler = $client->getArgument(0)['handler'];
        $this->assertInstanceOf(Definition::class, $clientHandler);
        $this->assertSame(HandlerStack::class, $clientHandler->getClass());
        $this->assertSame($handler, $clientHandler->getArgument(0));
        $this->assertTrue($clientHandler->hasMethodCall('push'));
    }

    /**
     * @param ContainerBuilder $container
     * @param string           $alias
     * @param mixed            $priority
     */
    private function createMiddleware(ContainerBuilder $container, string $alias, $priority = null)
    {
        $middleware = new Definition();
        $middleware->addTag(MiddlewarePass::MIDDLEWARE_TAG, ['alias' => $alias, 'priority' => $priority]);
        $container->setDefinition($alias, $middleware);
    }

    private function createClient(array $middleware = null, $handler = null)
    {
        $client = new Definition();
        $client->addTag(
            MiddlewarePass::CLIENT_TAG,
            $middleware ? ['middleware' => implode(' ', $middleware)] : []
        );

        if ($handler) {
            $client->addArgument(['handler' => $handler]);
        }

        return $client;
    }

    /**
     * @return ContainerBuilder
     */
    private function createContainer() : ContainerBuilder
    {
        return new ContainerBuilder();
    }
}