<?php

declare(strict_types=1);

namespace Vellum\Sources;

use Throwable;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Vellum\Config\ResolvedConfig;
use Vellum\Contracts\Source;
use Vellum\Exceptions\SourceNotFoundException;

/**
 * Renders a Twig template.
 */
final class TwigSource implements Source
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        private readonly string $template,
        private readonly array $data,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function make(string $template, array $data = []): self
    {
        return new self($template, $data);
    }

    public function type(): string
    {
        return 'twig';
    }

    public function describe(): string
    {
        return $this->template;
    }

    public function load(ResolvedConfig $config): string
    {
        if (! class_exists(Environment::class)) {
            throw SourceNotFoundException::missingLibrary('twig/twig', $this->type());
        }

        $sources = $config->sources();
        $paths = $sources->twigPaths;
        $extension = $sources->twigExtension;

        $candidates = $extension === ''
            ? [$this->template]
            : [$this->template.'.'.$extension, $this->template];

        foreach ($paths as $path) {
            foreach ($candidates as $candidate) {
                if (is_file($path.DIRECTORY_SEPARATOR.$candidate)) {
                    return $this->render($path, $candidate);
                }
            }
        }

        throw SourceNotFoundException::missingFile($this->template);
    }

    private function render(string $path, string $candidate): string
    {
        $loader = new FilesystemLoader([$path]);
        $twig = new Environment($loader, ['debug' => false, 'strict_variables' => false]);

        try {
            return $twig->render($candidate, $this->data);
        } catch (Throwable $exception) {
            throw new SourceNotFoundException(
                sprintf('The Twig template [%s] could not be rendered: %s', $this->template, $exception->getMessage()),
                0,
                $exception,
            );
        }
    }
}
