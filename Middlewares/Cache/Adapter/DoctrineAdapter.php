<?php

namespace Prokl\GuzzleBundle\Middlewares\Cache\Adapter;

use Prokl\GuzzleBundle\Middlewares\Cache\NamingStrategy\HashNamingStrategy;
use Doctrine\Common\Cache\Cache;
use GuzzleHttp\Psr7\Response;
use Prokl\GuzzleBundle\Middlewares\Cache\NamingStrategy\NamingStrategyInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class DoctrineAdapter
 * @package Prokl\GuzzleBundle\Middlewares\Cache\Adapter
 */
class DoctrineAdapter implements StorageAdapterInterface
{
    /**
     * @var Cache $cache Cache.
     */
    private $cache;

    /**
     * @var HashNamingStrategy|NamingStrategyInterface $namingStrategy Naming strategy.
     */
    private $namingStrategy;

    /**
     * @var integer $ttl Cache ttl.
     */
    private $ttl;

    /**
     * @param Cache                        $cache          Cache.
     * @param integer                      $ttl            Cache TTL.
     * @param NamingStrategyInterface|null $namingStrategy Naming strategy.
     */
    public function __construct(Cache $cache, $ttl = 0, ?NamingStrategyInterface $namingStrategy = null)
    {
        $this->cache = $cache;
        $this->namingStrategy = $namingStrategy ?? new HashNamingStrategy();
        $this->ttl = $ttl;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(RequestInterface $request) : ?ResponseInterface
    {
        $key = $this->namingStrategy->filename($request);

        if ($this->cache->contains($key)) {
            $data = $this->cache->fetch($key);

            return new Response($data['status'], $data['headers'], $data['body'], $data['version'], $data['reason']);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function save(RequestInterface $request, ResponseInterface $response) : void
    {
        $data = [
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => (string)$response->getBody(),
            'version' => $response->getProtocolVersion(),
            'reason' => $response->getReasonPhrase(),
        ];

        $this->cache->save($this->namingStrategy->filename($request), $data, $this->ttl);

        $response->getBody()->seek(0);
    }
}
