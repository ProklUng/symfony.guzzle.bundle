<?php

namespace Prokl\GuzzleBundle\Middlewares\Raw;

use Closure;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Promise\Create;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use Throwable;

/**
 * Class GuzzleLogger
 * @package Prokl\GuzzleBundle\Middlewares\Raw
 *
 * @since 06.03.2021
 * @internal Fork from https://github.com/rtheunissen/guzzle-log-middleware
 */
class GuzzleLogger
{
    /**
     * @var LoggerInterface|callable $logger
     */
    private $logger;

    /**
     * @var MessageFormatter|callable
     */
    private $formatter;

    /**
     * @var string|callable Constant or callable that accepts a Response.
     */
    private $logLevel;

    /**
     * @var boolean Whether or not to log requests as they are made.
     */
    private $logRequests;

    /**
     * Creates a callable middleware for logging requests and responses.
     *
     * @param LoggerInterface|callable $logger    Логгер.
     * @param string|callable|null     $formatter Constant or callable that accepts a Response.
     *
     * @return void
     */
    public function __construct($logger, $formatter = null)
    {
        // Use the setters to take care of type validation
        $this->setLogger($logger);
        $this->setFormatter($formatter ?? $this->getDefaultFormatter());
    }

    /**
     * Returns the default formatter;
     *
     * @return MessageFormatter
     */
    private function getDefaultFormatter()
    {
        return new MessageFormatter();
    }

    /**
     * Sets whether requests should be logged before the response is received.
     *
     * @param boolean $logRequests
     *
     * @return void
     */
    private function setRequestLoggingEnabled(bool $logRequests = true) : void
    {
        $this->logRequests = $logRequests;
    }

    /**
     * Sets the logger, which can be a PSR-3 logger or a callable that accepts
     * a log level, message, and array context.
     *
     * @param LoggerInterface|callable $logger Логгер.
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function setLogger($logger) : void
    {
        if ($logger instanceof LoggerInterface || is_callable($logger)) {
            $this->logger = $logger;
        } else {
            throw new InvalidArgumentException(
                'Logger has to be a Psr\Log\LoggerInterface or callable'
            );
        }
    }

    /**
     * Sets the formatter, which can be a MessageFormatter or callable that
     * accepts a request, response, and a reason if an error has occurred.
     *
     * @param MessageFormatter|callable $formatter
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function setFormatter($formatter) : void
    {
        if ($formatter instanceof MessageFormatter || is_callable($formatter)) {
            $this->formatter = $formatter;
        } else {
            throw new InvalidArgumentException(
                'Formatter has to be a \GuzzleHttp\MessageFormatter or callable'
            );
        }
    }

    /**
     * Sets the log level to use, which can be either a string or a callable
     * that accepts a response (which could be null). A log level could also
     * be null, which indicates that the default log level should be used.
     *
     * @param string|callable $logLevel Log level.
     *
     * @return void
     */
    public function setLogLevel($logLevel) : void
    {
        $this->logLevel = $logLevel;
    }

    /**
     * Logs a request and/or a response.
     *
     * @param RequestInterface       $request  Request.
     * @param ResponseInterface|null $response Response.
     * @param Throwable|null         $reason   Reason.
     *
     * @return mixed
     */
    protected function log(
        RequestInterface $request,
        ResponseInterface $response = null,
        ?Throwable $reason = null
    ) {
        if ($reason instanceof RequestException) {
            $response = $reason->getResponse();
        }

        $level = $this->getLogLevel($response);
        $message = $this->getLogMessage($request, $response, $reason);
        $context = compact('request', 'response', 'reason');

        // Make sure that the content of the body is available again.
        if ($response) {
            $response->getBody()->seek(0);
        }

        if (is_callable($this->logger)) {
            return call_user_func($this->logger, $level, $message, $context);
        }

        return $this->logger->log($level, $message, $context);
    }

    /**
     * Formats a request and response as a log message.
     *
     * @param RequestInterface       $request  Request.
     * @param ResponseInterface|null $response Response.
     * @param Throwable|null         $reason   Reason.
     *
     * @return string The formatted message.
     */
    protected function getLogMessage(
        RequestInterface $request,
        ResponseInterface $response = null,
        ?Throwable $reason = null
    ) {
        if ($this->formatter instanceof MessageFormatter) {
            return $this->formatter->format(
                $request,
                $response,
                $reason
            );
        }

        return call_user_func($this->formatter, $request, $response, $reason);
    }

    /**
     * Returns a log level for a given response.
     *
     * @param ResponseInterface|null $response The response being logged.
     *
     * @return mixed
     */
    protected function getLogLevel(?ResponseInterface $response = null)
    {
        if (!$this->logLevel) {
            return $this->getDefaultLogLevel($response);
        }

        if (is_callable($this->logLevel)) {
            return call_user_func($this->logLevel, $response);
        }

        return $this->logLevel;
    }

    /**
     * Returns the default log level for a response.
     *
     * @param ResponseInterface|null $response Response.
     *
     * @return string
     */
    protected function getDefaultLogLevel(?ResponseInterface $response = null)
    {
        if ($response && $response->getStatusCode() >= 300) {
            return LogLevel::NOTICE;
        }

        return LogLevel::INFO;
    }

    /**
     * Returns a function which is handled when a request was successful.
     *
     * @param RequestInterface $request Request.
     *
     * @return Closure
     */
    protected function onSuccess(RequestInterface $request)
    {
        return function ($response) use ($request) {
            $this->log($request, $response);

            return $response;
        };
    }

    /**
     * Returns a function which is handled when a request was rejected.
     *
     * @param RequestInterface $request Request.
     *
     * @return Closure
     */
    protected function onFailure(RequestInterface $request) : Closure
    {
        return function ($reason) use ($request) {

            // Only log a rejected request if it hasn't already been logged.
            if (!$this->logRequests) {
                $this->log($request, null, $reason);
            }

            return Create::rejectionFor($reason);
        };
    }

    /**
     * Called when the middleware is handled by the client.
     *
     * @param callable $handler Handler.
     *
     * @return Closure
     */
    public function __invoke(callable $handler)
    {
        /**
         * @param RequestInterface $request
         * @param array            $options
         *
         * @return mixed
         */
        return function (RequestInterface $request, array $options) use ($handler) {
            // Only log requests if explicitly set to do so
            if ($this->logRequests) {
                $this->log($request);
            }

            return $handler($request, $options)->then(
                $this->onSuccess($request),
                $this->onFailure($request)
            );
        };
    }
}
