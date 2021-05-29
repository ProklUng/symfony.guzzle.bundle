<?php

namespace Prokl\GuzzleBundle\HttpFoundation;

use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class StreamResponse
 * @package Prokl\GuzzleBundle\HttpFoundation
 */
class StreamResponse extends Response
{
    /**
     * @const BUFFER_SIZE
     */
    public const BUFFER_SIZE = 4096;

    /**
     * @var integer|mixed $bufferSize
     */
    private $bufferSize;

    /**
     * StreamResponse constructor.
     *
     * @param ResponseInterface $response   Response.
     * @param integer           $bufferSize Buffer size.
     */
    public function __construct(ResponseInterface $response, int $bufferSize = self::BUFFER_SIZE)
    {
        parent::__construct(null, $response->getStatusCode(), $response->getHeaders());

        $this->content = $response->getBody();
        $this->bufferSize = $bufferSize;
    }

    /**
     * @return $this
     */
    public function sendContent() : self
    {
        $chunked = $this->headers->has('Transfer-Encoding');
        $this->content->seek(0);

        for (;;) {
            $chunk = (string)$this->content->read($this->bufferSize);

            if ($chunked) {
                echo sprintf("%x\r\n", strlen($chunk));
            }

            echo $chunk;

            if ($chunked) {
                echo "\r\n";
            }

            flush();

            if (!$chunk) {
                return $this;
            }
        }

        return $this;
    }

    /**
     * @return string|false
     */
    public function getContent()
    {
        return false;
    }
}
