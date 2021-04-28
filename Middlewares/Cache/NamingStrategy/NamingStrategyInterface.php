<?php

namespace Prokl\GuzzleBundle\Middlewares\Cache\NamingStrategy;

use Psr\Http\Message\RequestInterface;

/**
 * Interface NamingStrategyInterface
 * @package Prokl\GuzzleBundle\Middlewares\Cache\NamingStrategy
 */
interface NamingStrategyInterface
{
    /**
     * @param RequestInterface $request Request.
     *
     * @return string
     */
    public function filename(RequestInterface $request) : string;
}
