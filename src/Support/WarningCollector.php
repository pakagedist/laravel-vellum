<?php

declare(strict_types=1);

namespace Vellum\Support;

/**
 * Collects the warnings raised by the engine while it renders a document.
 *
 * PHP warnings are never swallowed: they are captured and reported by the
 * builder, either as log entries or as an exception.
 */
final class WarningCollector
{
    /**
     * @var list<string>
     */
    private array $warnings = [];

    private bool $active = false;

    /**
     * Start collecting the warnings.
     */
    public function start(): void
    {
        if ($this->active) {
            return;
        }

        $this->warnings = [];
        $this->active = true;

        set_error_handler(function (int $severity, string $message, string $file = '', int $line = 0): bool {
            $this->warnings[] = sprintf(
                '%s (%s:%d)',
                $message,
                $file !== '' ? $file : 'unknown',
                $line,
            );

            return true;
        });
    }

    /**
     * Stop collecting and return the warnings that were raised.
     *
     * @return list<string>
     */
    public function stop(): array
    {
        if ($this->active) {
            restore_error_handler();
            $this->active = false;
        }

        return $this->warnings;
    }
}
