<?php

declare(strict_types=1);

namespace Vellum\Tests\Feature;

use Illuminate\Contracts\Config\Repository;
use Vellum\Config\ConfigResolver;
use Vellum\Config\ResolvedConfig;
use Vellum\Exceptions\SourceNotFoundException;
use Vellum\Sources\UrlSource;
use Vellum\Tests\TestCase;

final class UrlSourceTest extends TestCase
{
    /**
     * @var resource|null
     */
    private $process = null;

    private string $root = '';

    private int $port = 0;

    protected function tearDown(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }

        $this->process = null;

        parent::tearDown();
    }

    public function test_it_fetches_a_remote_document(): void
    {
        $url = $this->server();

        $content = UrlSource::make($url)->load($this->config());

        $this->assertStringContainsString('Remote document', $content);
    }

    public function test_it_sends_the_configured_headers(): void
    {
        $url = $this->server();

        $this->application()->make(Repository::class)
            ->set('pdf.sources.url.headers', ['X-Test-Header' => 'unified']);

        $content = UrlSource::make($url)->load($this->config());

        $this->assertStringContainsString('unified', $content);
    }

    public function test_it_fails_with_a_missing_page(): void
    {
        $url = $this->server().'/missing';

        $this->expectException(SourceNotFoundException::class);

        UrlSource::make($url)->load($this->config());
    }

    public function test_it_renders_a_remote_document(): void
    {
        $url = $this->server();

        $bytes = $this->manager()->url($url)->driver('fake')->render()->bytes;

        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    private function config(): ResolvedConfig
    {
        return $this->application()->make(ConfigResolver::class)->resolve('fake');
    }

    /**
     * Start a local HTTP server and return its base URL.
     */
    private function server(): string
    {
        if ($this->port !== 0) {
            return sprintf('http://127.0.0.1:%d', $this->port);
        }

        if (! function_exists('curl_init')) {
            $this->markTestSkipped('The cURL extension is not available.');
        }

        $this->root = sys_get_temp_dir().'/vellum-tests/server-'.uniqid();
        mkdir($this->root, 0o755, true);

        file_put_contents($this->root.'/router.php', <<<'PHP'
            <?php

            header('Content-Type: text/html');

            if (str_contains($_SERVER['REQUEST_URI'] ?? '/', 'missing')) {
                http_response_code(404);

                echo '<h1>Not found</h1>';

                return;
            }

            $name = $_SERVER['HTTP_X_TEST_HEADER'] ?? 'none';

            echo '<h1>Remote document</h1><span>'.$name.'</span>';
            PHP);

        $this->port = $this->freePort();

        $command = sprintf(
            'exec %s -S 127.0.0.1:%d %s',
            escapeshellarg(PHP_BINARY),
            $this->port,
            escapeshellarg($this->root.'/router.php'),
        );

        $process = proc_open(
            $command,
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', '/dev/null', 'w'],
                2 => ['file', '/dev/null', 'w'],
            ],
            $pipes,
        );

        if (! is_resource($process)) {
            $this->markTestSkipped('The local HTTP server could not be started.');
        }

        $this->process = $process;

        $this->waitForTheServer();

        return sprintf('http://127.0.0.1:%d', $this->port);
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($socket === false) {
            $this->markTestSkipped('Unable to reserve a local port.');
        }

        $name = (string) stream_socket_get_name($socket, false);

        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    private function waitForTheServer(): void
    {
        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            $connection = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.2);

            if (is_resource($connection)) {
                fclose($connection);

                return;
            }

            usleep(50000);
        }

        $this->markTestSkipped('The local HTTP server did not start in time.');
    }
}
