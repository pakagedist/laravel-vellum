<?php

declare(strict_types=1);

namespace Vellum\Lumen;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Vellum\Config\ConfigResolver;
use Vellum\Config\PdfConfig;
use Vellum\Contracts\Processor;
use Vellum\Drivers\BrowsershotDriver;
use Vellum\Drivers\DomPdfDriver;
use Vellum\Drivers\DriverRegistry;
use Vellum\Drivers\FakeDriver;
use Vellum\Drivers\MpdfDriver;
use Vellum\PdfManager;
use Vellum\Pipeline\Pipeline;

/**
 * Registers the package in a Lumen application.
 *
 * Lumen does not read the "extra.laravel" section of composer.json, the
 * provider has to be registered by hand:
 *
 *     $app->register(Vellum\Lumen\LumenServiceProvider::class);
 */
class LumenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->configure('pdf');

        $this->app->singleton(PdfConfig::class, static function (Container $app): PdfConfig {
            /** @var Repository $config */
            $config = $app->make('config');

            return PdfConfig::fromArray((array) $config->get('pdf', []));
        });

        $this->app->singleton(ConfigResolver::class, static function (Container $app): ConfigResolver {
            return new ConfigResolver($app->make(PdfConfig::class));
        });

        $this->app->singleton(DriverRegistry::class, static function (): DriverRegistry {
            return new DriverRegistry([
                new DomPdfDriver,
                new BrowsershotDriver,
                new MpdfDriver,
                new FakeDriver,
            ]);
        });

        $this->app->singleton(Pipeline::class, function (Container $app): Pipeline {
            $pipeline = new Pipeline;

            /** @var Repository $config */
            $config = $app->make('config');

            foreach ((array) $config->get('pdf.processors', []) as $processor) {
                if (! is_string($processor) || ! class_exists($processor)) {
                    continue;
                }

                $resolved = $app->make($processor);

                if ($resolved instanceof Processor) {
                    $pipeline->add($resolved);
                }
            }

            return $pipeline;
        });

        $this->app->singleton(PdfManager::class, function (Container $app): PdfManager {
            return new PdfManager(
                $app->make(ConfigResolver::class),
                $app->make(DriverRegistry::class),
                $app->make(Pipeline::class),
                $this->logger($app),
            );
        });
    }

    private function logger(Container $app): LoggerInterface
    {
        try {
            $log = $app->make('log');
        } catch (Throwable) {
            return new NullLogger;
        }

        $channel = $app->make(PdfConfig::class)->logChannel;

        if ($channel === null || ! method_exists($log, 'channel')) {
            return $log instanceof LoggerInterface ? $log : new NullLogger;
        }

        $channeled = $log->channel($channel);

        return $channeled instanceof LoggerInterface ? $channeled : new NullLogger;
    }
}
