<?php

declare(strict_types=1);

namespace Vellum\Tests\Feature;

use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use OpenSSLCertificateSigningRequest;
use ReflectionFunction;
use Vellum\Contracts\Processor;
use Vellum\Exceptions\PdfGenerationException;
use Vellum\Pipeline\PdfContext;
use Vellum\Support\Pdf\PdfDocument;
use Vellum\Tests\TestCase;

final class PostProcessingTest extends TestCase
{
    public function test_it_writes_the_metadata(): void
    {
        $bytes = $this->manager()->html('<p>x</p>')->driver('fake')
            ->metadata(['title' => 'The title', 'author' => 'The author'])
            ->render()
            ->bytes;

        $this->assertStringContainsString('The title', $bytes);
        $this->assertStringContainsString('The author', $bytes);
    }

    public function test_it_embeds_the_xmp_metadata(): void
    {
        $bytes = $this->manager()->html('<p>x</p>')->driver('fake')
            ->xmp(['dc:title' => 'XMP title'])
            ->render()
            ->bytes;

        $this->assertStringContainsString('XMP title', $bytes);
        $this->assertStringContainsString('xpacket', $bytes);
    }

    public function test_it_marks_the_document_as_pdfa(): void
    {
        $bytes = $this->manager()->html('<p>x</p>')->driver('fake')->pdfa()->render()->bytes;

        $this->assertStringContainsString('pdfaid:part', $bytes);
        $this->assertStringContainsString('pdfaid:conformance', $bytes);
    }

    public function test_it_embeds_a_file(): void
    {
        $path = $this->temporaryPath('invoice.xml');

        file_put_contents($path, '<?xml version="1.0"?><Invoice/>');

        $bytes = $this->manager()->html('<p>x</p>')->driver('fake')
            ->embedFile($path, 'invoice.xml')
            ->render()
            ->bytes;

        $this->assertStringContainsString('invoice.xml', $bytes);
        $this->assertStringContainsString('/EmbeddedFile', $bytes);

        @unlink($path);
    }

    public function test_it_fails_when_the_embedded_file_does_not_exist(): void
    {
        $this->expectException(PdfGenerationException::class);
        $this->expectExceptionMessage('could not be read');

        $this->manager()->html('<p>x</p>')->driver('fake')->embedFile('/does/not/exist.xml')->render();
    }

    public function test_it_builds_a_zugferd_invoice(): void
    {
        $path = $this->temporaryPath('factur-x.xml');

        file_put_contents($path, '<?xml version="1.0"?><Invoice/>');

        $bytes = $this->manager()->html('<p>x</p>')->driver('fake')->zugferd($path)->render()->bytes;

        $this->assertStringContainsString('fx:DocumentType', $bytes);
        $this->assertStringContainsString('INVOICE', $bytes);
        $this->assertStringContainsString('pdfaid:part', $bytes);

        @unlink($path);
    }

    public function test_it_merges_other_documents(): void
    {
        $first = $this->temporaryPath('first.pdf');
        $second = $this->temporaryPath('second.pdf');

        file_put_contents($first, $this->manager()->html('<p>first</p>')->driver('dompdf')->bytes());
        file_put_contents($second, $this->manager()->html('<p>second</p>')->driver('dompdf')->bytes());

        $bytes = $this->manager()->html('<p>merged</p>')->driver('dompdf')
            ->merge([$first, $second])
            ->render()
            ->bytes;

        $document = PdfDocument::open($bytes);

        $this->assertNotNull($document->catalog());
        $this->assertStringStartsWith('%PDF-', $bytes);

        @unlink($first);
        @unlink($second);
    }

    public function test_it_runs_a_custom_processor(): void
    {
        $processor = new class implements Processor
        {
            public function name(): string
            {
                return 'stamp';
            }

            public function process(PdfContext $context): PdfContext
            {
                return $context->withAttribute('stamped', true);
            }
        };

        $bytes = $this->manager()->html('<p>x</p>')->driver('fake')->process($processor)->render()->bytes;

        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function test_it_signs_the_document(): void
    {
        $certificate = $this->certificate();

        if ($certificate === null) {
            $this->markTestSkipped('The OpenSSL extension is not available.');
        }

        $bytes = $this->manager()->html('<p>x</p>')->driver('dompdf')
            ->sign($certificate['path'], $certificate['password'])
            ->render()
            ->bytes;

        $this->assertStringContainsString('/SubFilter /adbe.pkcs7.detached', $bytes);
        $this->assertStringContainsString('/ByteRange', $bytes);

        $this->assertTrue($this->signatureIsValid($bytes));
    }

    public function test_the_signatures_are_not_swallowed(): void
    {
        $this->expectException(PdfGenerationException::class);
        $this->expectExceptionMessage('could not be read');

        $this->manager()->html('<p>x</p>')->driver('fake')->sign('/does/not/exist.p12', 'secret')->render();
    }

    /**
     * @return array{path: string, password: string}|null
     */
    private function certificate(): ?array
    {
        if (! function_exists('openssl_csr_sign')) {
            return null;
        }

        $directory = sys_get_temp_dir().'/vellum-tests';

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        $path = $directory.'/certificate.p12';
        $password = 'secret';

        if (is_file($path)) {
            return ['path' => $path, 'password' => $password];
        }

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (! $privateKey instanceof OpenSSLAsymmetricKey) {
            return null;
        }

        // The key is passed by reference, a copy keeps the type of the key.
        $signingKey = $privateKey;

        $csr = openssl_csr_new(['commonName' => 'vellum-tests'], $signingKey, ['digest_alg' => 'sha256']);

        if (! $csr instanceof OpenSSLCertificateSigningRequest) {
            return null;
        }

        $x509 = openssl_csr_sign($csr, null, $privateKey, 365, ['digest_alg' => 'sha256']);

        if (! $x509 instanceof OpenSSLCertificate) {
            return null;
        }

        $exported = $this->exportPkcs12($privateKey, $password, $x509);

        if ($exported === false) {
            return null;
        }

        file_put_contents($path, $exported);

        return ['path' => $path, 'password' => $password];
    }

    /**
     * Export a PKCS#12 bundle, the signature of the function changed in
     * PHP 8.5, so it is called through reflection.
     */
    private function exportPkcs12(
        OpenSSLAsymmetricKey $privateKey,
        string $password,
        OpenSSLCertificate $certificate,
    ): string|false {
        $reflection = new ReflectionFunction('openssl_pkcs12_export');
        $parameters = $reflection->getParameters();

        // PHP 8.5: openssl_pkcs12_export($certificate, $output, $private_key, $passphrase)
        if (($parameters[1]->getName() ?? '') === 'output') {
            $output = '';

            $arguments = [$certificate, &$output, $privateKey, $password];

            if ($reflection->invokeArgs($arguments) === false) {
                return false;
            }

            return $output;
        }

        // PHP 8.4 and older: openssl_pkcs12_export($private_key, $passphrase, $certificate)
        $result = $reflection->invokeArgs([$privateKey, $password, $certificate, '']);

        return is_string($result) ? $result : false;
    }

    private function signatureIsValid(string $pdf): bool
    {
        if (preg_match('/\/ByteRange\s*\[([^\]]+)\]/', $pdf, $matches) !== 1) {
            return false;
        }

        // The byte range is an array of [offset, length] pairs.
        $range = [];

        foreach (explode(' ', $matches[1]) as $part) {
            $range[] = (int) trim($part);
        }

        if (count($range) !== 4) {
            return false;
        }

        if (preg_match('/\/Contents\s*<([0-9a-fA-F]+)>/', $pdf, $contents) !== 1) {
            return false;
        }

        $hex = trim($contents[1]);
        $raw = (string) hex2bin(str_pad($hex, strlen($hex) + (strlen($hex) % 2), '0', STR_PAD_LEFT));

        // The length of the DER payload is in its ASN.1 header, the rest of
        // the reserved space is padding.
        if (strlen($raw) < 4 || $raw[0] !== "\x30") {
            return false;
        }

        $length = unpack('n', substr($raw, 2, 2));

        if (! is_array($length)) {
            return false;
        }

        $size = $length[1] ?? 0;

        if (! is_int($size)) {
            return false;
        }

        $der = substr($raw, 0, 4 + $size);

        $content = substr($pdf, $range[0], $range[1])
            .substr($pdf, $range[2], $range[3]);

        $certificate = $this->certificate();

        if ($certificate === null) {
            return false;
        }

        $pkcs12 = (string) file_get_contents($certificate['path']);
        $certs = [];

        if (openssl_pkcs12_read($pkcs12, $certs, $certificate['password']) === false) {
            return false;
        }

        if (! is_array($certs) || ! isset($certs['cert']) || ! is_string($certs['cert'])) {
            return false;
        }

        $public = openssl_pkey_get_public($certs['cert']);

        if ($public === false) {
            return false;
        }

        $details = openssl_pkey_get_details($public);

        if (! is_array($details) || ! isset($details['bits'])) {
            return false;
        }

        $bits = $details['bits'];

        if (! is_int($bits)) {
            return false;
        }

        // The signature is the last element of the signer information.
        $signature = substr($der, -intdiv($bits, 8));

        return openssl_verify($content, $signature, $public, OPENSSL_ALGO_SHA256) === 1;
    }
}
