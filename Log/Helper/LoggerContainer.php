<?php

namespace Modernrugs\Log\Helper;

use Psr\Log\LoggerInterface;

class LoggerContainer
{
    /** @var  LoggerInterface[] $loggerContainer */
    protected $loggerContainer;
    /**
     * @var \Psr\Log\LoggerInterface $logger
     */
    protected $logger;

    public function __construct(
        array $loggerContainer
    ) {
        $this->loggerContainer = $loggerContainer;
    }

    public function getLoggerAndSwitchTo($context = null)
    {
        if (array_key_exists($context, $this->loggerContainer)) {
            $this->logger = $this->loggerContainer[$context];
            return $this->logger;
        }
        return null;
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function critical($message, array $context = array())
    {
        $this->logger->critical($message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function error($message, array $context = array())
    {
        $this->logger->error($message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function warning($message, array $context = array())
    {
        $this->logger->warning($message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function notice($message, array $context = array())
    {
        $this->logger->notice($message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function info($message, array $context = array())
    {
        $this->logger->info($message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function debug($message, array $context = array())
    {
        $this->logger->debug($message, $context);
    }
}
