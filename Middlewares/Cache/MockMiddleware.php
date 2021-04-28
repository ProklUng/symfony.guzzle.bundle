<?php


namespace Prokl\GuzzleBundle\Middlewares\Cache;

use Closure;
use Prokl\GuzzleBundle\Middlewares\Cache\Adapter\StorageAdapterInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use Psr\Http\Message\RequestInterface;

/**
 * Mock Middleware.
 */
class MockMiddleware extends CacheMiddleware
{
    public const DEBUG_HEADER = 'X-Guzzle-Mock';
    public const DEBUG_HEADER_HIT = 'REPLAY';
    public const DEBUG_HEADER_MISS = 'RECORD';

    /**
     * @var mixed $mode
     */
    private $mode;

    /**
     * MockMiddleware constructor.
     *
     * @param StorageAdapterInterface $adapter
     * @param mixed                   $mode
     * @param boolean                 $debug
     */
    public function __construct(StorageAdapterInterface $adapter, $mode, $debug = false)
    {
        parent::__construct($adapter, $debug);

        $this->mode = $mode;
    }

    /**
     * @param callable $handler Обработчик.
     *
     * @return Closure
     */
    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            if ('record' === $this->mode) {
                return $this->handleSave($handler, $request, $options);
            }

            if (null === $response = $this->adapter->fetch($request)) {
                return new RejectedPromise(sprintf(
                    'Record not found for request: %s %s',
                    $request->getMethod(),
                    $request->getUri()
                ));
            }

            $response = $this->addDebugHeader($response, 'REPLAY');

            return new FulfilledPromise($response);
        };
    }
}
