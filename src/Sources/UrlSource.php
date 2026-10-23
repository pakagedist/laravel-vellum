<?php

declare(strict_types=1);

namespace Vellum\Sources;

use Vellum\Config\ResolvedConfig;
use Vellum\Contracts\Source;
use Vellum\Exceptions\SourceNotFoundException;

/**
 * Fetches the content of a remote URL.
 */
final class UrlSource implements Source
{
    private function __construct(
        private readonly string $url,
    ) {}

    public static function make(string $url): self
    {
        return new self($url);
    }

    public function type(): string
    {
        return 'url';
    }

    public function describe(): string
    {
        return $this->url;
    }

    public function load(ResolvedConfig $config): string
    {
        $scheme = strtolower((string) parse_url($this->url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw SourceNotFoundException::missingUrl($this->url, 'Only http and https URLs are supported.');
        }

        if (! $config->sources()->allowRemote) {
            throw SourceNotFoundException::missingUrl($this->url, 'Remote sources are disabled by configuration.');
        }

        return $this->fetch($config);
    }

    private function fetch(ResolvedConfig $config): string
    {
        if (! function_exists('curl_init')) {
            throw SourceNotFoundException::missingLibrary('ext-curl', $this->type());
        }

        $timeout = $config->sources()->urlTimeout;
        $handle = curl_init($this->url);

        if ($handle === false) {
            throw SourceNotFoundException::missingUrl($this->url, 'Unable to initialise the HTTP client.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_HTTPHEADER => $this->headers($config),
            CURLOPT_USERAGENT => 'Vellum/1.0',
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false || $status < 200 || $status >= 300) {
            throw SourceNotFoundException::missingUrl(
                $this->url,
                $error !== '' ? $error : sprintf('The server responded with the HTTP status %d.', $status),
            );
        }

        return (string) $body;
    }

    /**
     * @return list<string>
     */
    private function headers(ResolvedConfig $config): array
    {
        $headers = [];

        foreach ($config->sources()->urlHeaders as $name => $value) {
            $headers[] = $name.': '.$value;
        }

        return $headers;
    }
}
