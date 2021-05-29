<?php

namespace Prokl\GuzzleBundle\Tests\Cases;

use Prokl\GuzzleBundle\HttpFoundation\StreamResponse;
use Prokl\TestingTools\Base\BaseTestCase;
use GuzzleHttp\Psr7\Response;

/**
 * Class StreamResponseTest
 * @package Prokl\GuzzleBundle\Tests\Cases
 */
class StreamResponseTest extends BaseTestCase
{
    /**
     * @return void
     */
    public function testNormalOutput() : void
    {
        $this->expectOutputString('this should not be streamed');

        $mock = new Response(200, [], 'this should not be streamed');
        $response = new StreamResponse($mock);
        $response->send();
    }

    /**
     * @return void
     */
    public function testChunkedOutput() : void
    {
        $this->expectOutputString("a\r\nthis shoul\r\na\r\nd not be s\r\n7\r\ntreamed\r\n0\r\n\r\n");

        $mock = new Response(200, ['Transfer-Encoding' => 'chunked'], 'this should not be streamed');
        $response = new StreamResponse($mock, 10);
        $response->send();
    }
}