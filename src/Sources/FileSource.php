<?php

declare(strict_types=1);

namespace Vellum\Sources;

use Vellum\Config\ResolvedConfig;
use Vellum\Contracts\Source;
use Vellum\Exceptions\SourceNotFoundException;

/**
 * Reads the content of a local file.
 */
final class FileSource implements Source
{
    private function __construct(
        private readonly string $path,
    ) {}

    public static function make(string $path): self
    {
        return new self($path);
    }

    public function type(): string
    {
        return 'file';
    }

    public function describe(): string
    {
        return $this->path;
    }

    public function load(ResolvedConfig $config): string
    {
        $path = $this->resolvePath();

        if (! is_file($path) || ! is_readable($path)) {
            throw SourceNotFoundException::missingFile($this->path);
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw SourceNotFoundException::missingFile($this->path);
        }

        return $contents;
    }

    private function resolvePath(): string
    {
        $path = $this->path;

        if (str_starts_with($path, 'file://')) {
            return substr($path, 7);
        }

        if ($path !== '' && $path[0] === DIRECTORY_SEPARATOR) {
            return $path;
        }

        $base = function_exists('base_path') ? base_path() : getcwd();

        return $base.DIRECTORY_SEPARATOR.$path;
    }
}
