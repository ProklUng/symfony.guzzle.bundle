<?php

namespace Prokl\GuzzleBundle\Middlewares\Cache\NamingStrategy;

use Psr\Http\Message\RequestInterface;

/**
 * Class HashNamingStrategy
 * @package Prokl\GuzzleBundle\Middlewares\Cache\NamingStrategy
 */
class HashNamingStrategy implements NamingStrategyInterface
{
    /**
     * {@inheritdoc}
     */
    public function filename(RequestInterface $request) : string
    {
        return md5(serialize([
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'headers' => $request->getHeaders(),
        ]));
    }
}
