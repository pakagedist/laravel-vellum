<?php

declare(strict_types=1);

namespace Vellum\Drivers;

use Spatie\Browsershot\Browsershot;
use Throwable;
use Vellum\Config\ResolvedConfig;
use Vellum\Contracts\Capability;
use Vellum\Contracts\Driver;
use Vellum\Contracts\Source;
use Vellum\Exceptions\DriverNotSupportedException;
use Vellum\Exceptions\PdfGenerationException;
use Vellum\Exceptions\RenderingTimeoutException;
use Vellum\Support\PageHtml;

/**
 * Renders the PDF with a headless Chromium browser through Browsershot.
 *
 * It is the driver to use when the document relies on modern CSS, web fonts
 * or Tailwind.
 */
final class BrowsershotDriver implements Driver
{
    /**
     * The engine options handled by the driver itself, they are never passed
     * through as raw Puppeteer options.
     */
    private const RESERVED_OPTIONS = [
        'node_binary',
        'npm_binary',
        'node_modules_path',
        'chrome_path',
        'timeout',
    ];

    public function name(): string
    {
        return 'browsershot';
    }

    /**
     * @return list<string>
     */
    public function capabilities(): array
    {
        return Capability::all();
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    public function render(Source $source, ResolvedConfig $config): string
    {
        if (! class_exists(Browsershot::class)) {
            throw DriverNotSupportedException::missingEngine($this->name(), 'spatie/browsershot');
        }

        $html = $source->load($config);

        if ($config->font !== null && $config->font !== '') {
            $html = PageHtml::applyFontFamily($html, $config->font);
        }

        if ($config->fontFile !== null && is_file($config->fontFile)) {
            $html = PageHtml::injectFont($html, (string) $config->font, $config->fontFile);
        }

        $browsershot = new Browsershot;

        $this->configureBinary($browsershot, $config, 'node_binary', 'BROWSERSHOT_NODE_PATH', 'setNodeBinary');
        $this->configureBinary($browsershot, $config, 'npm_binary', 'BROWSERSHOT_NPM_PATH', 'setNpmBinary');
        $this->configureBinary($browsershot, $config, 'node_modules_path', 'BROWSERSHOT_NODE_MODULES_PATH', 'setNodeModulePath');
        $this->configureBinary($browsershot, $config, 'chrome_path', 'BROWSERSHOT_CHROME_PATH', 'setChromePath');

        // The timeout is enforced by Puppeteer, it aborts the render instead
        // of letting it hang forever.
        $browsershot->timeout($config->timeout);

        $browsershot->html($html);

        $browsershot->setOption('format', $config->paper->label());
        $browsershot->setOption('landscape', $config->orientation->isLandscape());
        $browsershot->setOption('printBackground', true);
        $browsershot->setOption('preferCSSPageSize', false);

        $browsershot->setOption('marginTop', $this->inches($config->margins->top));
        $browsershot->setOption('marginBottom', $this->inches($config->margins->bottom));
        $browsershot->setOption('marginLeft', $this->inches($config->margins->left));
        $browsershot->setOption('marginRight', $this->inches($config->margins->right));

        foreach ($config->options as $key => $value) {
            if (in_array((string) $key, self::RESERVED_OPTIONS, true)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $browsershot->setOption((string) $key, $value);
            }
        }

        foreach ($config->documentOptions as $key => $value) {
            if (in_array((string) $key, self::RESERVED_OPTIONS, true)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $browsershot->setOption((string) $key, $value);
            }
        }

        $target = tempnam(sys_get_temp_dir(), 'vellum-browser-');

        if ($target === false) {
            throw new PdfGenerationException('Unable to create a temporary file for the Browsershot render.');
        }

        try {
            $browsershot->save($target);

            $output = (string) file_get_contents($target);
        } catch (Throwable $exception) {
            if ($this->isTimeout($exception)) {
                throw RenderingTimeoutException::exceeded($this->name(), $config->timeout);
            }

            throw PdfGenerationException::driverFailed($this->name(), $exception->getMessage(), $exception);
        } finally {
            @unlink($target);
        }

        if ($output === '') {
            throw new PdfGenerationException('Browsershot returned an empty document.');
        }

        return $output;
    }

    private function configureBinary(
        Browsershot $browsershot,
        ResolvedConfig $config,
        string $option,
        string $environmentVariable,
        string $method,
    ): void {
        $value = $config->documentOptions[$option] ?? $config->options[$option] ?? getenv($environmentVariable);

        if (! is_string($value) || $value === '') {
            return;
        }

        $browsershot->{$method}($value);
    }

    private function inches(float $millimetres): float
    {
        return round($millimetres / 25.4, 4);
    }

    private function isTimeout(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'timed out') || str_contains($message, 'timeout');
    }
}
