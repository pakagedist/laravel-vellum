<?php

declare(strict_types=1);

namespace Vellum\Support\Pdf;

/**
 * Reads the cross reference table of a PDF, either the classic table or the
 * cross reference stream introduced by PDF 1.5.
 */
final class XrefReader
{
    /**
     * Return the offset of the last cross reference section of the file.
     */
    public static function lastStartXref(string $bytes): int
    {
        $position = strrpos($bytes, 'startxref');

        if ($position === false) {
            throw new PdfFormatException('The PDF does not contain a "startxref" marker.');
        }

        $position += strlen('startxref');
        PdfLexer::skipWhitespaceAndComments($bytes, $position);

        $offset = PdfLexer::readToken($bytes, $position);

        if (! is_numeric($offset)) {
            throw new PdfFormatException('The PDF has an invalid "startxref" value.');
        }

        return (int) $offset;
    }

    /**
     * Read the cross reference information of a document.
     *
     * Incremental updates are chained through the "/Prev" entry of the
     * trailer, so the objects of the previous revisions stay reachable.
     */
    public static function read(string $bytes, int $offset): XrefInfo
    {
        return self::readSection($bytes, $offset, 0);
    }

    private static function readSection(string $bytes, int $offset, int $depth): XrefInfo
    {
        if ($depth > 32) {
            throw new PdfFormatException('The cross reference chain is too deep.');
        }

        $position = $offset;
        PdfLexer::skipWhitespaceAndComments($bytes, $position);

        $current = substr($bytes, $position, 4) === 'xref'
            ? self::readTable($bytes, $offset)
            : self::readStream($bytes, $offset);

        $previous = $current->trailer['Prev'] ?? null;

        if ($previous === null || ! is_numeric($previous)) {
            return $current;
        }

        $older = self::readSection($bytes, (int) $previous, $depth + 1);

        return new XrefInfo(
            array_merge($older->entries, $current->entries),
            $current->isStream,
            array_merge($older->trailer, $current->trailer),
        );
    }

    private static function readTable(string $bytes, int $offset): XrefInfo
    {
        $entries = [];
        $position = $offset + 4;
        $length = strlen($bytes);

        while ($position < $length) {
            PdfLexer::skipWhitespaceAndComments($bytes, $position);

            if (substr($bytes, $position, 7) === 'trailer') {
                break;
            }

            $start = PdfLexer::readToken($bytes, $position);
            PdfLexer::skipWhitespaceAndComments($bytes, $position);
            $count = PdfLexer::readToken($bytes, $position);

            if (! is_numeric($start) || ! is_numeric($count)) {
                break;
            }

            $first = (int) $start;
            $total = (int) $count;

            for ($index = 0; $index < $total; $index++) {
                PdfLexer::skipWhitespaceAndComments($bytes, $position);

                $entryOffset = PdfLexer::readToken($bytes, $position);
                PdfLexer::skipWhitespaceAndComments($bytes, $position);
                $generation = PdfLexer::readToken($bytes, $position);
                PdfLexer::skipWhitespaceAndComments($bytes, $position);
                $type = PdfLexer::readToken($bytes, $position);

                if ($type !== 'n' && $type !== 'f') {
                    $type = 'n';
                }

                $entries[$first + $index] = [
                    'type' => $type === 'n' ? 1 : 0,
                    'offset' => is_numeric($entryOffset) ? (int) $entryOffset : 0,
                    'generation' => is_numeric($generation) ? (int) $generation : 0,
                ];
            }
        }

        $position = strpos($bytes, 'trailer', $offset);

        if ($position === false) {
            throw new PdfFormatException('The PDF table does not contain a trailer.');
        }

        $position += strlen('trailer');
        PdfLexer::skipWhitespaceAndComments($bytes, $position);

        $trailerRaw = PdfLexer::readValue($bytes, $position);
        $trailer = PdfLexer::parseDictionary($trailerRaw);

        return new XrefInfo($entries, false, $trailer);
    }

    private static function readStream(string $bytes, int $offset): XrefInfo
    {
        $position = $offset;
        PdfLexer::skipWhitespaceAndComments($bytes, $position);

        PdfLexer::readToken($bytes, $position);
        PdfLexer::skipWhitespaceAndComments($bytes, $position);
        PdfLexer::readToken($bytes, $position);
        PdfLexer::skipWhitespaceAndComments($bytes, $position);
        PdfLexer::readToken($bytes, $position);
        PdfLexer::skipWhitespaceAndComments($bytes, $position);

        $dictRaw = PdfLexer::readValue($bytes, $position);
        $dict = PdfLexer::parseDictionary($dictRaw);

        $position += 0;
        PdfLexer::skipWhitespaceAndComments($bytes, $position);

        if (substr($bytes, $position, 6) !== 'stream') {
            throw new PdfFormatException('The cross reference stream is malformed.');
        }

        $position += 6;

        if (substr($bytes, $position, 2) === "\r\n") {
            $position += 2;
        } elseif (($bytes[$position] ?? '') === "\n") {
            $position++;
        }

        $length = isset($dict['Length']) ? (int) $dict['Length'] : 0;

        if ($length <= 0) {
            $end = strpos($bytes, 'endstream', $position);
            $length = $end === false ? 0 : $end - $position;
        }

        $data = substr($bytes, $position, $length);
        $data = self::decode($data, $dict);

        $widths = array_map('intval', PdfLexer::parseArray($dict['W'] ?? '[1 4 2]'));
        $widths = array_slice($widths, 0, 3);

        while (count($widths) < 3) {
            $widths[] = 0;
        }

        $size = isset($dict['Size']) ? (int) $dict['Size'] : 0;

        $index = isset($dict['Index'])
            ? array_map('intval', PdfLexer::parseArray($dict['Index']))
            : [0, $size];

        $entries = [];
        $cursor = 0;
        $total = count($index) / 2;

        for ($range = 0; $range < (int) $total; $range++) {
            $first = $index[$range * 2];
            $count = $index[$range * 2 + 1];

            for ($item = 0; $item < $count; $item++) {
                $type = 1;

                if ($widths[0] > 0) {
                    $type = ord($data[$cursor] ?? "\x00");
                    $cursor += $widths[0];
                }

                $field2 = 0;

                if ($widths[1] > 0) {
                    $field2 = self::readInteger($data, $cursor, $widths[1]);
                    $cursor += $widths[1];
                }

                $field3 = 0;

                if ($widths[2] > 0) {
                    $field3 = self::readInteger($data, $cursor, $widths[2]);
                    $cursor += $widths[2];
                }

                $entries[$first + $item] = [
                    'type' => $type,
                    'offset' => $field2,
                    'generation' => $field3,
                ];
            }
        }

        return new XrefInfo($entries, true, $dict);
    }

    private static function readInteger(string $data, int $offset, int $width): int
    {
        $value = 0;

        for ($index = 0; $index < $width; $index++) {
            $value = ($value << 8) | ord($data[$offset + $index] ?? "\x00");
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $dict
     */
    private static function decode(string $data, array $dict): string
    {
        $raw = trim($dict['Filter'] ?? '');

        if ($raw === '') {
            $filters = [];
        } elseif (str_starts_with($raw, '[')) {
            $filters = PdfLexer::parseArray($raw);
        } else {
            $filters = [$raw];
        }

        $filters = array_map(
            static fn (string $filter): string => strtolower(ltrim(trim($filter), '/')),
            $filters,
        );

        $parameters = $dict['DecodeParms'] ?? null;

        foreach ($filters as $filter) {
            if ($filter === 'flatedecode') {
                $decoded = @gzuncompress($data);

                if ($decoded === false) {
                    $decoded = @gzinflate($data);
                }

                if ($decoded === false) {
                    throw new PdfFormatException('Unable to inflate the cross reference stream.');
                }

                $data = $decoded;

                if ($parameters !== null) {
                    $data = self::applyPredictor($data, $parameters);
                }
            }
        }

        return $data;
    }

    /**
     * @param  array<string, string>|string  $parameters
     */
    private static function applyPredictor(string $data, array|string $parameters): string
    {
        if (is_string($parameters)) {
            $trimmed = trim($parameters);

            if (str_starts_with($trimmed, '[')) {
                $items = PdfLexer::parseArray($trimmed);
                $parameters = $items === [] ? [] : PdfLexer::parseDictionary($items[0]);
            } else {
                $parameters = PdfLexer::parseDictionary($trimmed);
            }
        }

        $predictor = self::intParameter($parameters, 'Predictor', 1);

        if ($predictor <= 1) {
            return $data;
        }

        $columns = self::intParameter($parameters, 'Columns', 1);
        $colors = self::intParameter($parameters, 'Colors', 1);
        $bits = self::intParameter($parameters, 'BitsPerComponent', 8);
        $bpp = max(1, (int) ceil($colors * $bits / 8));
        $rowLength = max(1, (int) ceil($columns * $colors * $bits / 8));

        $rows = str_split($data, $rowLength + 1);
        $previous = array_fill(0, $rowLength, 0);
        $output = '';

        foreach ($rows as $row) {
            if ($row === '') {
                continue;
            }

            $type = ord($row[0]);
            $current = self::bytes(substr($row, 1));

            for ($index = 0; $index < $rowLength; $index++) {
                $current[$index] = ($current[$index] ?? 0) & 0xFF;
                $left = $index >= $bpp ? $current[$index - $bpp] : 0;
                $up = $previous[$index] ?? 0;
                $upLeft = $index >= $bpp ? ($previous[$index - $bpp] ?? 0) : 0;

                $current[$index] = match ($type) {
                    1 => ($current[$index] + $left) & 0xFF,
                    2 => ($current[$index] + $up) & 0xFF,
                    3 => ($current[$index] + intdiv($left + $up, 2)) & 0xFF,
                    4 => ($current[$index] + self::paeth($left, $up, $upLeft)) & 0xFF,
                    default => $current[$index],
                };
            }

            $output .= pack('C*', ...$current);
            $previous = $current;
        }

        return $output;
    }

    /**
     * Read a parameter of the stream dictionary.
     *
     * @param  array<string, string>  $parameters
     */
    private static function intParameter(array $parameters, string $key, int $default): int
    {
        $value = $parameters[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Unpack a string of bytes into a list of integers.
     *
     * @return list<int>
     */
    private static function bytes(string $value): array
    {
        $unpacked = unpack('C*', $value);

        if (! is_array($unpacked)) {
            return [];
        }

        $bytes = [];

        foreach ($unpacked as $byte) {
            $bytes[] = is_numeric($byte) ? (int) $byte : 0;
        }

        return $bytes;
    }

    private static function paeth(int $left, int $up, int $upLeft): int
    {
        $estimate = $left + $up - $upLeft;
        $distanceLeft = abs($estimate - $left);
        $distanceUp = abs($estimate - $up);
        $distanceUpLeft = abs($estimate - $upLeft);

        if ($distanceLeft <= $distanceUp && $distanceLeft <= $distanceUpLeft) {
            return $left;
        }

        return $distanceUp <= $distanceUpLeft ? $up : $upLeft;
    }
}
