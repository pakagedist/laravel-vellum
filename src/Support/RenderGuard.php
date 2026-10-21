<?php

declare(strict_types=1);

namespace Vellum\Support;

use Vellum\Exceptions\RenderingTimeoutException;

/**
 * Runs a rendering task and aborts it when it takes too long.
 *
 * The alarm is only used when the pcntl extension is available, otherwise the
 * timeout is only checked once the render is over.
 */
final class RenderGuard
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(int $timeout, string $driver, callable $callback): mixed
    {
        if (! self::isSupported() || $timeout <= 0) {
            return $callback();
        }

        $previous = pcntl_signal_get_handler(SIGALRM);

        pcntl_async_signals(true);

        pcntl_signal(SIGALRM, static function () use ($driver, $timeout): void {
            throw RenderingTimeoutException::exceeded($driver, $timeout);
        });

        pcntl_alarm($timeout);

        try {
            return $callback();
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, $previous);
        }
    }

    public static function isSupported(): bool
    {
        return function_exists('pcntl_alarm')
            && function_exists('pcntl_async_signals')
            && function_exists('pcntl_signal_get_handler')
            && function_exists('pcntl_signal');
    }
}
