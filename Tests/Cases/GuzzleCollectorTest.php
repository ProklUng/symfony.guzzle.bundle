<?php

namespace Prokl\GuzzleBundle\Tests\Cases;

use Prokl\GuzzleBundle\DataCollector\GuzzleCollector;
use Prokl\GuzzleBundle\Middlewares\History\HistoryMiddleware;
use Prokl\TestingTools\Base\BaseTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class GuzzleCollectorTest
 * @package Prokl\GuzzleBundle\Tests\Cases
 */
class GuzzleCollectorTest extends BaseTestCase
{
    /**
     * @return void
     */
    public function testCollect() : void
    {
        $mocks = array_fill(0, 3, new Response(204));

        $mock = new MockHandler($mocks);
        $handler = HandlerStack::create($mock);
        $collector = new GuzzleCollector();
        $handler->push(new HistoryMiddleware($collector->getHistory()));
        $client = new Client(['handler' => $handler]);

        $request = Request::createFromGlobals();
        $response = $this->createMock('Symfony\Component\HttpFoundation\Response');
        $collector->collect($request, $response, new \Exception());
        $this->assertCount(0, $collector->getCalls());

        $client->get('http://foo.bar');
        $collector->collect($request, $response, new \Exception());
        $calls = $collector->getCalls();
        $this->assertCount(1, $calls);

        $client->get('http://foo.bar');
        $collector->collect($request, $response, new \Exception());
        $this->assertCount(2, $collector->getCalls());
    }
}