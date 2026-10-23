<?php

declare(strict_types=1);

namespace Vellum\Pipeline;

use Throwable;
use Vellum\Contracts\Processor;
use Vellum\Exceptions\PdfGenerationException;

/**
 * Runs the post processors over the rendered PDF.
 */
final class Pipeline
{
    /**
     * @var list<Processor>
     */
    private array $processors = [];

    /**
     * @param  iterable<Processor>  $processors
     */
    public function __construct(iterable $processors = [])
    {
        foreach ($processors as $processor) {
            $this->add($processor);
        }
    }

    /**
     * Register a processor that runs for every document.
     */
    public function add(Processor ...$processors): self
    {
        foreach ($processors as $processor) {
            $this->processors[] = $processor;
        }

        return $this;
    }

    /**
     * @return list<Processor>
     */
    public function processors(): array
    {
        return $this->processors;
    }

    /**
     * @param  list<Processor>  $processors
     */
    public function process(PdfContext $context, array $processors = []): PdfContext
    {
        $chain = array_merge($this->processors, $processors);

        foreach ($chain as $processor) {
            try {
                $context = $processor->process($context);
            } catch (PdfGenerationException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw PdfGenerationException::processingFailed($processor->name(), $exception->getMessage(), $exception);
            }
        }

        return $context;
    }
}
