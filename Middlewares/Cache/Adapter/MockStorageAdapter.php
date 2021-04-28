<?php

namespace Prokl\GuzzleBundle\Middlewares\Cache\Adapter;

use GuzzleHttp\Psr7\Message;
use Prokl\GuzzleBundle\Middlewares\Cache\NamingStrategy\LegacyNamingStrategy;
use Prokl\GuzzleBundle\Middlewares\Cache\NamingStrategy\NamingStrategyInterface;
use Prokl\GuzzleBundle\Middlewares\Cache\NamingStrategy\SubfolderNamingStrategy;
use Prokl\GuzzleBundle\Middlewares\Cache\CacheMiddleware;
use Prokl\GuzzleBundle\Middlewares\Cache\MockMiddleware;
use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class MockStorageAdapter
 * @package Prokl\GuzzleBundle\Middlewares\Cache\Adapter
 */
class MockStorageAdapter implements StorageAdapterInterface
{
    /**
     * @var NamingStrategyInterface[] $namingStrategies
     */
    private $namingStrategies = [];

    /** @var string $storagePath */
    private $storagePath;

    /** @var array $responseHeadersBlacklist */
    private $responseHeadersBlacklist = [
        CacheMiddleware::DEBUG_HEADER,
        MockMiddleware::DEBUG_HEADER,
    ];

    /**
     * @param string                       $storagePath
     * @param array                        $requestHeadersBlacklist
     * @param array                        $responseHeadersBlacklist
     * @param NamingStrategyInterface|null $namingStrategy
     */
    public function __construct($storagePath, array $requestHeadersBlacklist = [], array $responseHeadersBlacklist = [], NamingStrategyInterface $namingStrategy = null)
    {
        $this->storagePath = $storagePath;

        if ($namingStrategy) {
            $this->namingStrategies[] = $namingStrategy;
        } else {
            $this->namingStrategies[] = new SubfolderNamingStrategy($requestHeadersBlacklist);
            $this->namingStrategies[] = new LegacyNamingStrategy(true, $requestHeadersBlacklist);
            $this->namingStrategies[] = new LegacyNamingStrategy(false, $requestHeadersBlacklist);
        }

        if ($responseHeadersBlacklist) {
            $this->responseHeadersBlacklist = $responseHeadersBlacklist;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(RequestInterface $request) : ?ResponseInterface
    {
        foreach ($this->namingStrategies as $strategy) {
            if (file_exists($filename = $this->getFilename($strategy->filename($request)))) {
                return Message::parseResponse(file_get_contents($filename));
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function save(RequestInterface $request, ResponseInterface $response) : void
    {
        foreach ($this->responseHeadersBlacklist as $header) {
            $response = $response->withoutHeader($header);
        }

        [$strategy] = $this->namingStrategies;

        $filename = $this->getFilename($strategy->filename($request));

        $fs = new Filesystem();
        $fs->mkdir(dirname($filename));

        file_put_contents($filename, Psr7\str($response));
        $response->getBody()->rewind();
    }

    /**
     * Prefixes the generated file path with the adapter's storage path.
     *
     * @param string $name
     *
     * @return string The path to the mock file
     */
    private function getFilename($name)
    {
        return $this->storagePath.'/'.$name.'.txt';
    }
}
