<?php

namespace Prokl\GuzzleBundle\Middlewares\History;

use Closure;
use GuzzleHttp\Promise\RejectedPromise;
use Psr\Http\Message\RequestInterface;

/**
 * History Middleware.
 */
class HistoryMiddleware
{
    /**
     * @var History $container
     */
    private $container;

    /**
     * HistoryMiddleware constructor.
     *
     * @param History $container Контейнер.
     */
    public function __construct(History $container)
    {
        $this->container = $container;
    }

    /**
     * @param callable $handler Handler.
     *
     * @return Closure
     */
    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            return $handler($request, $options)->then(
                function ($response) use ($request, $options) {
                    $this->container->mergeInfo($request, [
                        'response' => $response,
                        'error' => null,
                        'options' => $options,
                        'info' => [],
                    ]);

                    return $response;
                },
                function ($reason) use ($request, $options) : RejectedPromise {
                    $this->container->mergeInfo($request, [
                        'response' => null,
                        'error' => $reason,
                        'options' => $options,
                        'info' => [],
                    ]);

                    return new RejectedPromise($reason);
                }
            );
        };
    }
}
