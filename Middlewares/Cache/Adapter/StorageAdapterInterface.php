<?php

namespace Prokl\GuzzleBundle\Middlewares\Cache\Adapter;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface StorageAdapterInterface
 * @package Prokl\GuzzleBundle\Middlewares\Cache\Adapter
 */
interface StorageAdapterInterface
{
    /**
     * @param RequestInterface $request Request.
     *
     * @return null|ResponseInterface
     */
    public function fetch(RequestInterface $request): ?ResponseInterface;

    /**
     * @param RequestInterface  $request  Request.
     * @param ResponseInterface $response Response.
     *
     * @return void
     */
    public function save(RequestInterface $request, ResponseInterface $response) : void;
}
