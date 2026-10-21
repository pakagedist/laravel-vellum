<?php

declare(strict_types=1);

namespace Vellum\Support\Pdf;

/**
 * A very small PDF object scanner.
 *
 * It understands just enough of the PDF syntax to read and rewrite the
 * objects of a document: dictionaries, arrays, names, strings and numbers.
 */
final class PdfLexer
{
    public static function isWhitespace(string $character): bool
    {
        return $character === "\x00" || $character === "\t" || $character === "\n"
            || $character === "\x0c" || $character === "\r" || $character === ' ';
    }

    public static function isDelimiter(string $character): bool
    {
        return in_array($character, ['(', ')', '<', '>', '[', ']', '{', '}', '/', '%'], true);
    }

    public static function skipWhitespaceAndComments(string $content, int &$position): void
    {
        $length = strlen($content);

        while ($position < $length) {
            $character = $content[$position];

            if (self::isWhitespace($character)) {
                $position++;

                continue;
            }

            if ($character === '%') {
                while ($position < $length && $content[$position] !== "\n" && $content[$position] !== "\r") {
                    $position++;
                }

                continue;
            }

            break;
        }
    }

    /**
     * Read a bare token (keyword, number, name without the slash).
     */
    public static function readToken(string $content, int &$position): string
    {
        $length = strlen($content);
        $start = $position;

        while ($position < $length) {
            $character = $content[$position];

            if (self::isWhitespace($character) || self::isDelimiter($character)) {
                break;
            }

            $position++;
        }

        return substr($content, $start, $position - $start);
    }

    /**
     * Read a name and return its decoded value (without the leading slash).
     */
    public static function readName(string $content, int &$position): string
    {
        if (($content[$position] ?? '') !== '/') {
            return '';
        }

        $position++;

        $length = strlen($content);
        $name = '';

        while ($position < $length) {
            $character = $content[$position];

            if (self::isWhitespace($character) || self::isDelimiter($character)) {
                break;
            }

            if ($character === '#' && isset($content[$position + 1], $content[$position + 2])) {
                $hex = substr($content, $position + 1, 2);

                if (ctype_xdigit($hex)) {
                    $codepoint = (int) hexdec($hex);

                    if ($codepoint < 0 || $codepoint > 255) {
                        $codepoint = 0;
                    }

                    $name .= chr($codepoint);
                    $position += 3;

                    continue;
                }
            }

            $name .= $character;
            $position++;
        }

        return $name;
    }

    /**
     * Read the raw text of the value starting at the given position.
     */
    public static function readValue(string $content, int &$position): string
    {
        self::skipWhitespaceAndComments($content, $position);

        $start = $position;
        $character = $content[$position] ?? '';

        if ($character === '<' && ($content[$position + 1] ?? '') === '<') {
            $position += 2;
            self::skipDictionaryBody($content, $position);

            return substr($content, $start, $position - $start);
        }

        if ($character === '[') {
            $position++;
            self::skipArrayBody($content, $position);

            return substr($content, $start, $position - $start);
        }

        if ($character === '(') {
            $position++;
            self::skipLiteralString($content, $position);

            return substr($content, $start, $position - $start);
        }

        if ($character === '<') {
            $position++;

            while ($position < strlen($content) && $content[$position] !== '>') {
                $position++;
            }

            $position++;

            return substr($content, $start, $position - $start);
        }

        if ($character === '/') {
            self::readName($content, $position);

            return substr($content, $start, $position - $start);
        }

        $token = self::readToken($content, $position);

        if (is_numeric($token)) {
            $restore = $position;

            self::skipWhitespaceAndComments($content, $position);
            $generation = self::readToken($content, $position);

            if (is_numeric($generation)) {
                self::skipWhitespaceAndComments($content, $position);
                $keyword = self::readToken($content, $position);

                if ($keyword === 'R') {
                    return substr($content, $start, $position - $start);
                }
            }

            $position = $restore;
        }

        return substr($content, $start, $position - $start);
    }

    /**
     * Parse a dictionary and return its entries as raw values.
     *
     * @return array<string, string>
     */
    public static function parseDictionary(string $content): array
    {
        $position = 0;

        self::skipWhitespaceAndComments($content, $position);

        if (substr($content, $position, 2) !== '<<') {
            throw new PdfFormatException('Expected a PDF dictionary.');
        }

        $position += 2;

        $entries = [];
        $length = strlen($content);

        while ($position < $length) {
            self::skipWhitespaceAndComments($content, $position);

            if (substr($content, $position, 2) === '>>') {
                break;
            }

            if (($content[$position] ?? '') !== '/') {
                // Skip anything unexpected instead of failing the whole parse.
                self::readToken($content, $position);

                continue;
            }

            $key = self::readName($content, $position);
            $entries[$key] = self::readValue($content, $position);
        }

        return $entries;
    }

    /**
     * Build a dictionary from raw values.
     *
     * @param  array<string, string>  $entries
     */
    public static function buildDictionary(array $entries): string
    {
        $dictionary = '<<';

        foreach ($entries as $key => $value) {
            $dictionary .= ' /'.$key.' '.$value;
        }

        return $dictionary.' >>';
    }

    /**
     * Split the raw text of an array into its items.
     *
     * @return list<string>
     */
    public static function parseArray(string $raw): array
    {
        $trimmed = trim($raw);
        $trimmed = substr($trimmed, 1, -1);

        $items = [];
        $position = 0;
        $length = strlen($trimmed);

        while ($position < $length) {
            self::skipWhitespaceAndComments($trimmed, $position);

            if ($position >= $length) {
                break;
            }

            $items[] = trim(self::readValue($trimmed, $position));
        }

        return $items;
    }

    /**
     * Extract the object number of an indirect reference such as "12 0 R".
     */
    public static function referenceNumber(string $raw): ?int
    {
        if (preg_match('/^\s*(\d+)\s+\d+\s+R\s*$/', $raw, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Encode a text string as a PDF literal string.
     */
    public static function encodeTextString(string $value): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value) !== 1) {
            return self::encodeHexString($value);
        }

        $escaped = str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', '\\r'], $value);

        return '('.$escaped.')';
    }

    public static function encodeHexString(string $value): string
    {
        return '<'.bin2hex($value).'>';
    }

    private static function skipDictionaryBody(string $content, int &$position): void
    {
        $length = strlen($content);

        while ($position < $length) {
            self::skipWhitespaceAndComments($content, $position);

            if (substr($content, $position, 2) === '>>') {
                $position += 2;

                return;
            }

            if (($content[$position] ?? '') !== '/') {
                $before = $position;
                self::readToken($content, $position);

                if ($position === $before) {
                    $position++;
                }

                continue;
            }

            self::readName($content, $position);
            self::readValue($content, $position);
        }

        throw new PdfFormatException('Unterminated PDF dictionary.');
    }

    private static function skipArrayBody(string $content, int &$position): void
    {
        $length = strlen($content);

        while ($position < $length) {
            self::skipWhitespaceAndComments($content, $position);

            $character = $content[$position] ?? '';

            if ($character === ']') {
                $position++;

                return;
            }

            if ($character === '') {
                break;
            }

            $before = $position;
            self::readValue($content, $position);

            if ($position === $before) {
                $position++;
            }
        }

        throw new PdfFormatException('Unterminated PDF array.');
    }

    private static function skipLiteralString(string $content, int &$position): void
    {
        $length = strlen($content);
        $depth = 1;

        while ($position < $length) {
            $character = $content[$position];

            if ($character === '\\') {
                $position += 2;

                continue;
            }

            if ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;

                if ($depth === 0) {
                    $position++;

                    return;
                }
            }

            $position++;
        }

        throw new PdfFormatException('Unterminated PDF string.');
    }
}
