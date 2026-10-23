<?php

declare(strict_types=1);

namespace Vellum\Sources;

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Throwable;
use Vellum\Config\ResolvedConfig;
use Vellum\Contracts\Source;
use Vellum\Exceptions\SourceNotFoundException;

/**
 * Renders a Blade view.
 */
final class BladeSource implements Source
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        private readonly string $view,
        private readonly array $data,
        private readonly ?string $engine,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function make(string $view, array $data = [], ?string $engine = null): self
    {
        return new self($view, $data, $engine);
    }

    public function type(): string
    {
        return 'blade';
    }

    public function describe(): string
    {
        return $this->view;
    }

    public function load(ResolvedConfig $config): string
    {
        $factory = $this->viewFactory();

        if (! $factory->exists($this->view)) {
            throw SourceNotFoundException::missingView($this->view);
        }

        try {
            $view = $factory->make($this->view, $this->data);

            if ($this->engine !== null && method_exists($view, 'getEngine')) {
                $engine = $view->getEngine();

                if (is_object($engine) && method_exists($engine, 'setEngine')) {
                    $engine->setEngine($this->engine);
                }
            }

            return (string) $view->render();
        } catch (SourceNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SourceNotFoundException(
                sprintf('The view [%s] could not be rendered: %s', $this->view, $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    private function viewFactory(): ViewFactory
    {
        if (! interface_exists(ViewFactory::class)) {
            throw SourceNotFoundException::missingLibrary('illuminate/view', $this->type());
        }

        $container = Container::getInstance();

        if (! $container->bound(ViewFactory::class)) {
            throw new SourceNotFoundException(sprintf(
                'The [%s] source needs the view factory of the application, it is not registered in the container.',
                $this->type(),
            ));
        }

        /** @var ViewFactory $factory */
        $factory = $container->make(ViewFactory::class);

        return $factory;
    }
}
