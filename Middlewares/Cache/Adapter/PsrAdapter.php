<?php

namespace Prokl\GuzzleBundle\Middlewares\Cache\Adapter;

use Prokl\GuzzleBundle\Middlewares\Cache\NamingStrategy\NamingStrategyInterface;
use GuzzleHttp\Psr7\Response;
use Prokl\GuzzleBundle\Middlewares\Cache\NamingStrategy\PostHashNamingStrategy;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class PsrAdapter
 * @package Prokl\GuzzleBundle\Middlewares\Cache\Adapter
 *
 * @since 02.12.2020 Исключить кэширование PUT & DELETE запросов.
 */
class PsrAdapter implements StorageAdapterInterface
{
    /**
     * @var CacheItemPoolInterface $cache Кэшер.
     */
    private $cache;

    /**
     * @var NamingStrategyInterface|PostHashNamingStrategy $namingStrategy Формирование ключа кэша.
     */
    private $namingStrategy;

    /**
     * @var integer $ttl Время жизни кэша.
     */
    private $ttl;

    /**
     * @param CacheItemPoolInterface       $cache          Кэшер.
     * @param integer                      $ttl            Время жизни кэша.
     * @param NamingStrategyInterface|null $namingStrategy Формирование ключа кэша.
     */
    public function __construct(
        CacheItemPoolInterface $cache,
        int $ttl = 0,
        ?NamingStrategyInterface $namingStrategy = null
    ) {
        $this->cache = $cache;
        $this->namingStrategy = $namingStrategy ?? new PostHashNamingStrategy();
        $this->ttl = $ttl;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(RequestInterface $request) : ?ResponseInterface
    {
        if (!$this->checkValidTypeRequest($request)) {
            return null;
        }

        $key = $this->namingStrategy->filename($request);

        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            $data = $item->get();

            return new Response($data['status'], $data['headers'], $data['body'], $data['version'], $data['reason']);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function save(RequestInterface $request, ResponseInterface $response) : void
    {
        if (!$this->checkValidTypeRequest($request)) {
            return;
        }

        $key = $this->namingStrategy->filename($request);

        $item = $this->cache->getItem($key);
        $item->expiresAfter($this->ttl);
        $item->set([
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => (string) $response->getBody(),
            'version' => $response->getProtocolVersion(),
            'reason' => $response->getReasonPhrase(),
        ]);

        $this->cache->save($item);

        $response->getBody()->seek(0);
    }

    /**
     * PUT и POST запросы исключить из кэширования.
     *
     * @param RequestInterface $request
     *
     * @return boolean
     *
     * @since 02.12.2020
     */
    private function checkValidTypeRequest(RequestInterface $request) : bool
    {
        if ($request->getMethod() === 'PUT' || $request->getMethod() === 'DELETE') {
            return false;
        }

        return true;
    }
}
