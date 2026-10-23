<?php

declare(strict_types=1);

namespace Vellum\Support\Pdf;

use ReflectionFunction;
use Vellum\Exceptions\PdfGenerationException;
use Vellum\Support\DocumentMetadata;

/**
 * High level PDF operations built on top of the incremental writer.
 *
 * Every operation works on the raw bytes, so it is completely independent from
 * the engine that produced the document.
 */
final class PdfEditor
{
    /**
     * The ASN.1 encoding of the CMS signature, the only one that allows a
     * detached signature.
     */
    private const CMS_ENCODING_ASN1 = 2;

    /**
     * Cached result of the openssl_cms_sign() capabilities check.
     */
    private static ?bool $cmsEncodingSupported = null;

    /**
     * Write the document metadata (title, author, dates...) in the /Info
     * dictionary and set the document language in the catalog.
     */
    public static function setMetadata(string $bytes, DocumentMetadata $metadata): string
    {
        $document = PdfDocument::open($bytes);

        $infoNumber = $document->infoNumber();
        $infoObject = $infoNumber === null ? null : $document->object($infoNumber);

        $entries = $infoObject instanceof PdfObject ? $infoObject->dictionary : [];

        $mapping = [
            'Title' => $metadata->title,
            'Author' => $metadata->author,
            'Subject' => $metadata->subject,
            'Keywords' => $metadata->keywords,
            'Creator' => $metadata->creator,
            'Producer' => $metadata->producer,
            'CreationDate' => $metadata->creationDate,
            'ModDate' => $metadata->modificationDate,
        ];

        foreach ($mapping as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $entries[$key] = PdfLexer::encodeTextString($value);
        }

        $updates = [];
        $trailer = [];

        if ($infoNumber !== null && $infoObject !== null) {
            $updates[$infoNumber] = PdfLexer::buildDictionary($entries);
        } else {
            $newNumber = self::nextNumber($document);
            $updates[$newNumber] = PdfLexer::buildDictionary($entries);
            $trailer['Info'] = $newNumber.' 0 R';
        }

        $catalogNumber = $document->rootNumber();
        $catalog = $document->catalog();

        if ($catalogNumber !== null && $catalog !== null && $metadata->language !== null && $metadata->language !== '') {
            $entries = $catalog->dictionary;
            $entries['Lang'] = PdfLexer::encodeTextString($metadata->language);
            $updates[$catalogNumber] = PdfLexer::buildDictionary($entries);
        }

        return self::write($document, $updates, $trailer);
    }

    /**
     * Attach an XMP metadata packet to the document.
     */
    public static function setXmp(string $bytes, string $packet): string
    {
        $document = PdfDocument::open($bytes);

        $catalogNumber = $document->rootNumber();
        $catalog = $document->catalog();

        if ($catalogNumber === null || $catalog === null) {
            throw new PdfFormatException('The PDF does not contain a document catalog.');
        }

        $metadataNumber = self::nextNumber($document);

        $updates = [
            $metadataNumber => self::streamObject([
                'Type' => '/Metadata',
                'Subtype' => '/XML',
                'Length' => (string) strlen($packet),
            ], $packet),
        ];

        $entries = $catalog->dictionary;
        $entries['Metadata'] = $metadataNumber.' 0 R';
        $updates[$catalogNumber] = PdfLexer::buildDictionary($entries);

        return self::write($document, $updates);
    }

    /**
     * Embed a file inside the document (ZUGFeRD / Factur-X invoices...).
     */
    public static function embedFile(
        string $bytes,
        string $name,
        string $content,
        string $relationship = 'Data',
        string $mimeType = 'text/xml',
    ): string {
        $document = PdfDocument::open($bytes);

        $catalogNumber = $document->rootNumber();
        $catalog = $document->catalog();

        if ($catalogNumber === null || $catalog === null) {
            throw new PdfFormatException('The PDF does not contain a document catalog.');
        }

        $embeddedNumber = self::nextNumber($document);
        $specificationNumber = $embeddedNumber + 1;
        $namesNumber = $specificationNumber + 1;

        $updates = [];

        $updates[$embeddedNumber] = self::streamObject([
            'Type' => '/EmbeddedFile',
            'Subtype' => '/'.ltrim($mimeType, '/'),
            'Length' => (string) strlen($content),
            'Params' => PdfLexer::buildDictionary([
                'Size' => (string) strlen($content),
            ]),
        ], $content);

        $updates[$specificationNumber] = PdfLexer::buildDictionary([
            'Type' => '/Filespec',
            'F' => PdfLexer::encodeTextString($name),
            'UF' => PdfLexer::encodeTextString($name),
            'Desc' => PdfLexer::encodeTextString($name),
            'AFRelationship' => '/'.ltrim($relationship, '/'),
            'EF' => PdfLexer::buildDictionary([
                'F' => $embeddedNumber.' 0 R',
            ]),
        ]);

        $catalogEntries = $catalog->dictionary;
        $namesReference = PdfLexer::referenceNumber($catalogEntries['Names'] ?? '');

        $namesEntries = [];

        if ($namesReference !== null) {
            $namesObject = $document->object($namesReference);
            $namesEntries = $namesObject instanceof PdfObject ? $namesObject->dictionary : [];
        }

        $namesEntries['EmbeddedFiles'] = PdfLexer::buildDictionary([
            'Names' => '['.PdfLexer::encodeTextString($name).' '.$specificationNumber.' 0 R]',
        ]);

        $updates[$namesNumber] = PdfLexer::buildDictionary($namesEntries);

        $catalogEntries['Names'] = $namesNumber.' 0 R';

        $associated = $catalogEntries['AF'] ?? null;
        $associatedFiles = $associated === null ? [] : PdfLexer::parseArray($associated);
        $associatedFiles[] = $specificationNumber.' 0 R';
        $catalogEntries['AF'] = '['.implode(' ', $associatedFiles).']';

        $updates[$catalogNumber] = PdfLexer::buildDictionary($catalogEntries);

        return self::write($document, $updates);
    }

    /**
     * Mark the document as PDF/A-3b: identification metadata, output intent
     * and a stable document identifier.
     */
    public static function markPdfA3b(string $bytes, string $packet, ?string $iccProfile, string $documentId): string
    {
        $document = PdfDocument::open($bytes);

        $catalogNumber = $document->rootNumber();
        $catalog = $document->catalog();

        if ($catalogNumber === null || $catalog === null) {
            throw new PdfFormatException('The PDF does not contain a document catalog.');
        }

        $updates = [];

        $metadataNumber = self::nextNumber($document);
        $updates[$metadataNumber] = self::streamObject([
            'Type' => '/Metadata',
            'Subtype' => '/XML',
            'Length' => (string) strlen($packet),
        ], $packet);

        $catalogEntries = $catalog->dictionary;
        $catalogEntries['Metadata'] = $metadataNumber.' 0 R';

        if ($iccProfile !== null && is_file($iccProfile) && is_readable($iccProfile)) {
            $profile = (string) file_get_contents($iccProfile);
            $profileNumber = $metadataNumber + 1;

            $updates[$profileNumber] = self::streamObject([
                'N' => '3',
                'Length' => (string) strlen($profile),
            ], $profile);

            $catalogEntries['OutputIntents'] = '['.PdfLexer::buildDictionary([
                'Type' => '/OutputIntent',
                'S' => '/GTS_PDFA1',
                'OutputConditionIdentifier' => PdfLexer::encodeTextString('sRGB IEC61966-2.1'),
                'Info' => PdfLexer::encodeTextString('sRGB IEC61966-2.1'),
                'DestOutputProfile' => $profileNumber.' 0 R',
            ]).']';
        }

        $updates[$catalogNumber] = PdfLexer::buildDictionary($catalogEntries);

        $identifier = strtoupper(md5($documentId));

        return self::write($document, $updates, [
            'ID' => '['.PdfLexer::encodeHexString($identifier).' '.PdfLexer::encodeHexString($identifier).']',
        ]);
    }

    /**
     * Sign the document with a PKCS#12 certificate.
     *
     * @param  array{name?: string, reason?: string, location?: string, contact_info?: string, signer_name?: string, length?: int, date?: string}  $options
     */
    /**
     * @param  array<string, mixed>  $options
     */
    public static function sign(string $bytes, string $certificatePath, string $password, array $options = []): string
    {
        $length = max(1024, self::optionInt($options, 'length', 16384));

        // The signature must fit in the space reserved in the document, the
        // space is doubled until the signature fits.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $signed = self::signWithLength($bytes, $certificatePath, $password, $options, $length);

            if ($signed !== null) {
                return $signed;
            }

            $length *= 2;
        }

        throw new PdfGenerationException('The signature is too large to fit in the document.');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function signWithLength(
        string $bytes,
        string $certificatePath,
        string $password,
        array $options,
        int $length,
    ): ?string {
        $document = PdfDocument::open($bytes);

        $catalogNumber = $document->rootNumber();
        $catalog = $document->catalog();

        if ($catalogNumber === null || $catalog === null) {
            throw new PdfFormatException('The PDF does not contain a document catalog.');
        }

        $pageNumber = $document->firstPageNumber();

        $valueNumber = self::nextNumber($document);
        $widgetNumber = $valueNumber + 1;

        $placeholder = str_repeat('0', $length);
        $byteRangePlaceholder = '[0000000000 0000000000 0000000000 0000000000]';

        $value = [
            'Type' => '/Sig',
            'Filter' => '/Adobe.PPKLite',
            'SubFilter' => '/adbe.pkcs7.detached',
            'ByteRange' => $byteRangePlaceholder,
            'Contents' => '<'.$placeholder.'>',
            'M' => PdfLexer::encodeTextString(self::optionString($options, 'date', gmdate('YmdHis')."+00'00'")),
        ];

        foreach (['reason' => 'Reason', 'location' => 'Location', 'contact_info' => 'ContactInfo', 'signer_name' => 'Name'] as $key => $name) {
            $value[$name] = PdfLexer::encodeTextString(self::optionString($options, $key));
        }

        $widget = [
            'Type' => '/Annot',
            'Subtype' => '/Widget',
            'FT' => '/Sig',
            'T' => PdfLexer::encodeTextString(self::optionString($options, 'name', 'Signature')),
            'V' => $valueNumber.' 0 R',
            'Rect' => '[0 0 0 0]',
            'F' => '4',
        ];

        if ($pageNumber !== null) {
            $widget['P'] = $pageNumber.' 0 R';
        }

        $updates = [
            $valueNumber => PdfLexer::buildDictionary($value),
            $widgetNumber => PdfLexer::buildDictionary($widget),
        ];

        $catalogEntries = $catalog->dictionary;
        $acroFormReference = PdfLexer::referenceNumber($catalogEntries['AcroForm'] ?? '');

        if ($acroFormReference !== null) {
            $acroForm = $document->object($acroFormReference);
            $entries = $acroForm instanceof PdfObject ? $acroForm->dictionary : [];
            $fields = isset($entries['Fields']) ? PdfLexer::parseArray($entries['Fields']) : [];
            $fields[] = $widgetNumber.' 0 R';
            $entries['Fields'] = '['.implode(' ', $fields).']';
            $entries['SigFlags'] = '3';
            $updates[$acroFormReference] = PdfLexer::buildDictionary($entries);
        } else {
            $acroFormNumber = $widgetNumber + 1;
            $updates[$acroFormNumber] = PdfLexer::buildDictionary([
                'Fields' => '['.$widgetNumber.' 0 R]',
                'SigFlags' => '3',
            ]);
            $catalogEntries['AcroForm'] = $acroFormNumber.' 0 R';
        }

        if ($pageNumber !== null) {
            $page = $document->object($pageNumber);

            if ($page !== null) {
                $entries = $page->dictionary;
                $annotations = isset($entries['Annots']) ? PdfLexer::parseArray($entries['Annots']) : [];
                $annotations[] = $widgetNumber.' 0 R';
                $entries['Annots'] = '['.implode(' ', $annotations).']';
                $updates[$pageNumber] = PdfLexer::buildDictionary($entries);
            }
        }

        $updates[$catalogNumber] = PdfLexer::buildDictionary($catalogEntries);

        $signed = self::write($document, $updates);

        return self::applySignature($signed, $placeholder, $byteRangePlaceholder, $certificatePath, $password);
    }

    /**
     * @param  array<int, string>  $updates
     * @param  array<string, string>  $trailer
     */
    private static function write(PdfDocument $document, array $updates, array $trailer = []): string
    {
        return IncrementalWriter::append(
            $document->bytes,
            $document->xref,
            XrefReader::lastStartXref($document->bytes),
            $updates,
            $trailer,
        );
    }

    private static function nextNumber(PdfDocument $document): int
    {
        return max($document->xref->maxObjectNumber(), $document->xref->size() - 1) + 1;
    }

    /**
     * @param  array<string, string>  $dictionary
     */
    private static function streamObject(array $dictionary, string $data): string
    {
        return PdfLexer::buildDictionary($dictionary)."\nstream\n".$data."\nendstream";
    }

    /**
     * Fill the reserved space with the signature and the real byte range.
     *
     * Returns null when the signature does not fit in the reserved space.
     */
    private static function applySignature(
        string $bytes,
        string $placeholder,
        string $byteRangePlaceholder,
        string $certificatePath,
        string $password,
    ): ?string {
        $contentsPosition = strpos($bytes, '<'.$placeholder.'>');

        if ($contentsPosition === false) {
            throw new PdfGenerationException('Unable to locate the signature placeholder.');
        }

        $start = $contentsPosition + 1;
        $end = $start + strlen($placeholder);
        $total = strlen($bytes);

        $byteRange = [0, $start, $end, $total - $end];

        // The byte range is written with its final values before the document
        // is signed: the placeholder and the replacement have the same length,
        // so the positions of the reserved space do not move and the signed
        // bytes are exactly the bytes of the final document.
        $replacement = '['.sprintf('%010d %010d %010d %010d', ...$byteRange).']';
        $position = strpos($bytes, $byteRangePlaceholder);

        if ($position === false) {
            throw new PdfGenerationException('Unable to locate the byte range placeholder.');
        }

        $bytes = substr_replace($bytes, $replacement, $position, strlen($byteRangePlaceholder));

        $signature = self::createSignature($bytes, $byteRange, $certificatePath, $password);

        $hex = bin2hex($signature);

        if (strlen($hex) > strlen($placeholder)) {
            return null;
        }

        $hex = substr($hex.str_repeat('0', strlen($placeholder)), 0, strlen($placeholder));

        return substr_replace($bytes, $hex, $start, strlen($placeholder));
    }

    /**
     * Build a DER encoded CMS signature of the given byte range.
     *
     * @param  list<int>  $byteRange
     */
    private static function createSignature(
        string $bytes,
        array $byteRange,
        string $certificatePath,
        string $password,
    ): string {
        if (! function_exists('openssl_cms_sign')) {
            throw new PdfGenerationException('The OpenSSL extension is required to sign PDF documents.');
        }

        if (! is_file($certificatePath) || ! is_readable($certificatePath)) {
            throw new PdfGenerationException(sprintf('The certificate [%s] could not be read.', $certificatePath));
        }

        $pkcs12 = (string) file_get_contents($certificatePath);
        $certs = [];

        if (openssl_pkcs12_read($pkcs12, $certs, $password) === false) {
            throw new PdfGenerationException('The PKCS#12 certificate could not be decrypted.');
        }

        if (! is_array($certs)
            || ! isset($certs['cert'], $certs['pkey'])
            || ! is_string($certs['cert'])
            || ! is_string($certs['pkey'])) {
            throw new PdfGenerationException('The PKCS#12 certificate does not contain a key pair.');
        }

        $content = substr($bytes, 0, $byteRange[1]).substr($bytes, $byteRange[2], $byteRange[3]);

        $contentFile = tempnam(sys_get_temp_dir(), 'vellum-content-');
        $signatureFile = tempnam(sys_get_temp_dir(), 'vellum-signature-');

        if ($contentFile === false || $signatureFile === false) {
            throw new PdfGenerationException('Unable to create the temporary files required to sign the document.');
        }

        try {
            file_put_contents($contentFile, $content);

            $der = self::signContent($contentFile, $signatureFile, $certs['cert'], $certs['pkey']);
        } finally {
            @unlink($contentFile);
            @unlink($signatureFile);
        }

        if ($der === '') {
            throw new PdfGenerationException('The OpenSSL library returned an empty signature.');
        }

        return $der;
    }

    /**
     * Sign the content and return the DER encoded signature.
     *
     * PHP 8.5 is able to write a detached CMS signature with the ASN.1
     * encoding, which is the format the PDF specification expects. Older
     * versions fall back to a detached PKCS#7 signature, whose payload is
     * extracted from the multipart MIME message written by OpenSSL.
     */
    /**
     * Read a string option.
     *
     * @param  array<string, mixed>  $options
     */
    private static function optionString(array $options, string $key, string $default = ''): string
    {
        $value = $options[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * Read an integer option.
     *
     * @param  array<string, mixed>  $options
     */
    private static function optionInt(array $options, string $key, int $default): int
    {
        $value = $options[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        return $default;
    }

    private static function signContent(string $contentFile, string $signatureFile, string $certificate, string $privateKey): string
    {
        if (self::supportsCmsEncoding()) {
            $result = @openssl_cms_sign(
                $contentFile,
                $signatureFile,
                $certificate,
                $privateKey,
                [],
                OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY | OPENSSL_CMS_NOATTR,
                self::CMS_ENCODING_ASN1,
            );

            if ($result !== false && is_file($signatureFile)) {
                $der = self::pemToDer((string) file_get_contents($signatureFile));

                if ($der !== '') {
                    return $der;
                }
            }
        }

        $result = @openssl_pkcs7_sign(
            $contentFile,
            $signatureFile,
            $certificate,
            $privateKey,
            [],
            PKCS7_DETACHED | PKCS7_BINARY,
        );

        if ($result === false || ! is_file($signatureFile)) {
            throw new PdfGenerationException('The OpenSSL library failed to sign the document.');
        }

        return self::mimeToDer((string) file_get_contents($signatureFile));
    }

    /**
     * Whether the openssl_cms_sign() function accepts the encoding argument.
     */
    private static function supportsCmsEncoding(): bool
    {
        if (! function_exists('openssl_cms_sign')) {
            return false;
        }

        if (self::$cmsEncodingSupported !== null) {
            return self::$cmsEncodingSupported;
        }

        $reflection = new ReflectionFunction('openssl_cms_sign');

        self::$cmsEncodingSupported = $reflection->getNumberOfParameters() >= 7;

        return self::$cmsEncodingSupported;
    }

    /**
     * Extract the DER payload from a multipart MIME signed message.
     */
    private static function mimeToDer(string $raw): string
    {
        $boundary = null;

        if (preg_match('/boundary="?([^";\r\n]+)"?/i', $raw, $matches) === 1) {
            $boundary = $matches[1];
        }

        if ($boundary === null) {
            return self::pemToDer($raw);
        }

        $parts = preg_split('/--'.preg_quote($boundary, '/').'/', $raw) ?: [];

        foreach ($parts as $part) {
            if (stripos($part, 'Content-Transfer-Encoding: base64') === false) {
                continue;
            }

            $sections = preg_split('/\r?\n\r?\n/', $part, 2);

            if (! is_array($sections) || count($sections) !== 2) {
                continue;
            }

            $body = preg_replace('/\s+/', '', $sections[1]) ?? '';
            $der = base64_decode($body, true);

            if ($der !== false && $der !== '') {
                return $der;
            }
        }

        return '';
    }

    /**
     * Convert a PEM encoded payload into its raw DER representation.
     */
    private static function pemToDer(string $pem): string
    {
        $body = preg_replace('/-----[^-]+-----/', '', $pem) ?? $pem;
        $body = preg_replace('/\s+/', '', $body) ?? $body;

        if ($body === '') {
            return '';
        }

        $decoded = base64_decode($body, true);

        return $decoded === false ? '' : $decoded;
    }
}
