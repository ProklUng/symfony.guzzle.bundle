<?php

namespace Prokl\GuzzleBundle\Middlewares\Cache\NamingStrategy;

use Prokl\GuzzleBundle\Middlewares\Cache\CacheMiddleware;
use Psr\Http\Message\RequestInterface;

/**
 * Class AbstractNamingStrategy
 * @package Prokl\GuzzleBundle\Middlewares\Cache\NamingStrategy
 */
abstract class AbstractNamingStrategy implements NamingStrategyInterface
{
    /**
     * @var array $blacklist
     */
    private $blacklist = [
        'User-Agent',
        'Host',
        CacheMiddleware::DEBUG_HEADER,
    ];

    /**
     * AbstractNamingStrategy constructor.
     *
     * @param array $blacklist
     */
    public function __construct(array $blacklist = [])
    {
        if ($blacklist) {
            $this->blacklist = $blacklist;
        }
    }

    /**
     * Generates a fingerprint from a given request.
     *
     * @param RequestInterface $request
     *
     * @return string
     */
    protected function getFingerprint(RequestInterface $request)
    {
        return md5(serialize([
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'query' => $request->getUri()->getQuery(),
            'user_info' => $request->getUri()->getUserInfo(),
            'port' => $request->getUri()->getPort(),
            'scheme' => $request->getUri()->getScheme(),
            'headers' => array_diff_key($request->getHeaders(), array_flip($this->blacklist)),
        ]));
    }
}
