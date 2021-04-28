<?php

namespace Prokl\GuzzleBundle\Middlewares\History;

use GuzzleHttp\TransferStats;
use Psr\Http\Message\RequestInterface;

/**
 * Class History
 * @package Prokl\GuzzleBundle\Middlewares\History
 */
class History extends \SplObjectStorage
{
    /**
     * @param RequestInterface $request Request.
     * @param array            $info    Payload.
     *
     * @return void
     */
    public function mergeInfo(RequestInterface $request, array $info): void
    {
        $info = array_merge(
            ['response' => null, 'error' => null, 'info' => null],
            array_filter($this->contains($request) ? $this[$request] : []),
            array_filter($info)
        );

        $this->attach($request, $info);
    }

    /**
     * @param TransferStats $stats Статистика.
     *
     * @return void
     */
    public function addStats(TransferStats $stats): void
    {
        $this->mergeInfo($stats->getRequest(), ['info' => $stats->getHandlerStats()]);
    }
}
