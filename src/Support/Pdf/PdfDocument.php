<?php

declare(strict_types=1);

namespace Vellum\Support\Pdf;

/**
 * A parsed PDF document, read only.
 */
final class PdfDocument
{
    private function __construct(
        public readonly string $bytes,
        public readonly XrefInfo $xref,
    ) {}

    public static function open(string $bytes): self
    {
        if (! str_starts_with($bytes, '%PDF-')) {
            throw new PdfFormatException('The payload is not a PDF document.');
        }

        $offset = XrefReader::lastStartXref($bytes);
        $xref = XrefReader::read($bytes, $offset);

        return new self($bytes, $xref);
    }

    /**
     * @return array<string, string>
     */
    public function trailer(): array
    {
        return $this->xref->trailer;
    }

    public function rootNumber(): ?int
    {
        return PdfLexer::referenceNumber($this->xref->trailer['Root'] ?? '');
    }

    public function infoNumber(): ?int
    {
        return PdfLexer::referenceNumber($this->xref->trailer['Info'] ?? '');
    }

    public function catalog(): ?PdfObject
    {
        $number = $this->rootNumber();

        return $number === null ? null : $this->object($number);
    }

    public function object(int $number): ?PdfObject
    {
        $offset = $this->xref->offsetOf($number);

        if ($offset === null) {
            return null;
        }

        return $this->readObjectAt($offset, $number);
    }

    /**
     * The number of the first page of the document, following the page tree.
     */
    public function firstPageNumber(): ?int
    {
        $catalog = $this->catalog();

        if ($catalog === null) {
            return null;
        }

        $pagesNumber = PdfLexer::referenceNumber($catalog->get('Pages') ?? '');

        if ($pagesNumber === null) {
            return null;
        }

        return $this->firstPageOf($pagesNumber, 0);
    }

    private function firstPageOf(int $number, int $depth): ?int
    {
        if ($depth > 16) {
            return null;
        }

        $object = $this->object($number);

        if ($object === null) {
            return null;
        }

        $type = trim($object->get('Type') ?? '', '/ ');

        if ($type === 'Page') {
            return $number;
        }

        $kids = $object->get('Kids');

        if ($kids === null) {
            return null;
        }

        foreach (PdfLexer::parseArray($kids) as $kid) {
            $kidNumber = PdfLexer::referenceNumber($kid);

            if ($kidNumber === null) {
                continue;
            }

            $page = $this->firstPageOf($kidNumber, $depth + 1);

            if ($page !== null) {
                return $page;
            }
        }

        return null;
    }

    public function readObjectAt(int $offset, ?int $expected = null): ?PdfObject
    {
        $bytes = $this->bytes;
        $position = $offset;

        PdfLexer::skipWhitespaceAndComments($bytes, $position);

        $number = PdfLexer::readToken($bytes, $position);
        PdfLexer::skipWhitespaceAndComments($bytes, $position);
        $generation = PdfLexer::readToken($bytes, $position);
        PdfLexer::skipWhitespaceAndComments($bytes, $position);
        $keyword = PdfLexer::readToken($bytes, $position);

        if ($keyword !== 'obj') {
            return null;
        }

        PdfLexer::skipWhitespaceAndComments($bytes, $position);

        $raw = PdfLexer::readValue($bytes, $position);

        try {
            $dictionary = PdfLexer::parseDictionary($raw);
        } catch (PdfFormatException) {
            return null;
        }

        $stream = null;
        $lookahead = $position;
        PdfLexer::skipWhitespaceAndComments($bytes, $lookahead);

        if (substr($bytes, $lookahead, 6) === 'stream') {
            $lookahead += 6;

            if (substr($bytes, $lookahead, 2) === "\r\n") {
                $lookahead += 2;
            } elseif (($bytes[$lookahead] ?? '') === "\n") {
                $lookahead++;
            }

            $length = isset($dictionary['Length']) ? (int) $dictionary['Length'] : 0;

            if ($length <= 0) {
                $end = strpos($bytes, 'endstream', $lookahead);
                $length = $end === false ? 0 : $end - $lookahead;
            }

            $stream = substr($bytes, $lookahead, $length);
        }

        return new PdfObject(
            (int) $number,
            (int) $generation,
            $dictionary,
            $stream,
        );
    }
}
