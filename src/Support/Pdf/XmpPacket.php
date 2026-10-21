<?php

declare(strict_types=1);

namespace Vellum\Support\Pdf;

use Vellum\Support\DocumentMetadata;

/**
 * Builds XMP metadata packets.
 */
final class XmpPacket
{
    /**
     * @param  array<string, string>  $properties  rdf properties, without the namespace prefix.
     * @param  array<string, string>  $namespaces  Extra namespace declarations (prefix => uri).
     */
    public static function build(
        DocumentMetadata $metadata,
        array $properties = [],
        array $namespaces = [],
        ?string $documentId = null,
    ): string {
        $description = self::properties($metadata, $properties, $documentId);

        $declarations = [
            'dc' => 'http://purl.org/dc/elements/1.1/',
            'xmp' => 'http://ns.adobe.com/xap/1.0/',
            'pdf' => 'http://ns.adobe.com/pdf/1.3/',
            'xmpMM' => 'http://ns.adobe.com/xap/1.0/mm/',
            'pdfaid' => 'http://www.aiim.org/pdfa/ns/id/',
        ];

        foreach ($namespaces as $prefix => $uri) {
            $declarations[$prefix] = $uri;
        }

        $attributes = '';

        foreach ($declarations as $prefix => $uri) {
            $attributes .= sprintf(' xmlns:%s="%s"', $prefix, self::escape($uri));
        }

        return self::wrap(sprintf(
            '<rdf:Description rdf:about=""%s>%s</rdf:Description>',
            $attributes,
            $description,
        ));
    }

    /**
     * @param  array<string, string>  $properties
     */
    private static function properties(DocumentMetadata $metadata, array $properties, ?string $documentId): string
    {
        $values = [];

        $title = $metadata->title;

        if ($title !== null && $title !== '') {
            $values[] = sprintf(
                '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">%s</rdf:li></rdf:Alt></dc:title>',
                self::escape($title),
            );
        }

        $author = $metadata->author;

        if ($author !== null && $author !== '') {
            $values[] = sprintf(
                '<dc:creator><rdf:Seq><rdf:li>%s</rdf:li></rdf:Seq></dc:creator>',
                self::escape($author),
            );
        }

        $subject = $metadata->subject;

        if ($subject !== null && $subject !== '') {
            $values[] = sprintf(
                '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">%s</rdf:li></rdf:Alt></dc:description>',
                self::escape($subject),
            );
        }

        $keywords = $metadata->keywords;

        if ($keywords !== null && $keywords !== '') {
            $values[] = sprintf('<pdf:Keywords>%s</pdf:Keywords>', self::escape($keywords));
        }

        $creator = $metadata->creator;

        if ($creator !== null && $creator !== '') {
            $values[] = sprintf('<xmp:CreatorTool>%s</xmp:CreatorTool>', self::escape($creator));
        }

        $producer = $metadata->producer;

        if ($producer !== null && $producer !== '') {
            $values[] = sprintf('<pdf:Producer>%s</pdf:Producer>', self::escape($producer));
        }

        $language = $metadata->language;

        if ($language !== null && $language !== '') {
            $values[] = sprintf(
                '<dc:language><rdf:Bag><rdf:li>%s</rdf:li></rdf:Bag></dc:language>',
                self::escape($language),
            );
        }

        if ($metadata->creationDate !== null) {
            $values[] = sprintf('<xmp:CreateDate>%s</xmp:CreateDate>', self::escape($metadata->creationDate));
        }

        if ($metadata->modificationDate !== null) {
            $values[] = sprintf('<xmp:ModifyDate>%s</xmp:ModifyDate>', self::escape($metadata->modificationDate));
        }

        if ($documentId !== null) {
            $values[] = sprintf(
                '<xmpMM:DocumentID>uuid:%s</xmpMM:DocumentID><xmpMM:InstanceID>uuid:%s</xmpMM:InstanceID>',
                self::escape($documentId),
                self::escape($documentId),
            );
        }

        foreach ($properties as $name => $value) {
            $values[] = sprintf('<%s>%s</%s>', $name, self::escape($value), $name);
        }

        return implode('', $values);
    }

    public static function documentId(string $bytes): string
    {
        $hash = md5($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    private static function wrap(string $description): string
    {
        return '<?xpacket begin="'.pack('H*', 'EFBBBF').'" id="W5M0MpCehiHzreSzNTczkc9d"?>'."\n"
            .'<x:xmpmeta xmlns:x="adobe:ns:meta/">'."\n"
            .'<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'."\n"
            .$description."\n"
            .'</rdf:RDF>'."\n"
            .'</x:xmpmeta>'."\n"
            .'<?xpacket end="w"?>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
