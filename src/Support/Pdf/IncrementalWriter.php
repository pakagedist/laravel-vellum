<?php

declare(strict_types=1);

namespace Vellum\Support\Pdf;

/**
 * Writes incremental updates to a PDF document.
 *
 * An incremental update appends new objects and a new cross reference
 * section, which keeps the original bytes untouched and works with every
 * engine, whatever the way it produced the file.
 */
final class IncrementalWriter
{
    /**
     * @param  array<int, string>  $objects  Object number => object body.
     * @param  array<string, string>  $trailerOverrides  Trailer entries to override (Root, Info, ID).
     */
    public static function append(
        string $bytes,
        XrefInfo $xref,
        int $previousOffset,
        array $objects,
        array $trailerOverrides = [],
    ): string {
        $output = $bytes;

        if (! str_ends_with($output, "\n")) {
            $output .= "\n";
        }

        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($output);
            $output .= $number." 0 obj\n".$body."\nendobj\n";
        }

        ksort($offsets);

        $size = $xref->size();
        $size = max($size, (int) (max(array_keys($offsets) ?: [0]) + 1));

        $trailer = array_merge($xref->trailer, $trailerOverrides);

        $root = $trailer['Root'] ?? null;
        $info = $trailer['Info'] ?? null;
        $id = $trailer['ID'] ?? null;

        if ($xref->isStream) {
            return self::appendStream($output, $offsets, $size, $root, $info, $id, $previousOffset);
        }

        return self::appendTable($output, $offsets, $size, $root, $info, $id, $previousOffset);
    }

    /**
     * @param  array<int, int>  $offsets
     */
    private static function appendStream(
        string $output,
        array $offsets,
        int $size,
        ?string $root,
        ?string $info,
        ?string $id,
        int $previousOffset,
    ): string {
        $number = $size;
        $size++;

        $xrefOffset = strlen($output);
        $offsets[$number] = $xrefOffset;
        ksort($offsets);

        $data = '';
        $index = [];

        foreach (self::groups($offsets) as $group) {
            $index[] = $group['start'];
            $index[] = $group['count'];

            foreach ($group['numbers'] as $objectNumber) {
                $data .= chr(1).pack('N', $offsets[$objectNumber]).pack('n', 0);
            }
        }

        $compressed = gzcompress($data);

        if ($compressed === false) {
            throw new PdfFormatException('Unable to compress the cross reference stream.');
        }

        $dictionary = [
            'Type' => '/XRef',
            'Size' => (string) $size,
            'W' => '[1 4 2]',
            'Index' => '['.implode(' ', $index).']',
        ];

        if ($root !== null) {
            $dictionary['Root'] = $root;
        }

        if ($info !== null) {
            $dictionary['Info'] = $info;
        }

        if ($id !== null) {
            $dictionary['ID'] = $id;
        }

        $dictionary['Prev'] = (string) $previousOffset;
        $dictionary['Filter'] = '/FlateDecode';
        $dictionary['Length'] = (string) strlen($compressed);

        $body = PdfLexer::buildDictionary($dictionary)."\nstream\n".$compressed."\nendstream";

        $output .= $number." 0 obj\n".$body."\nendobj\n";
        $output .= "startxref\n".$xrefOffset."\n%%EOF\n";

        return $output;
    }

    /**
     * @param  array<int, int>  $offsets
     */
    private static function appendTable(
        string $output,
        array $offsets,
        int $size,
        ?string $root,
        ?string $info,
        ?string $id,
        int $previousOffset,
    ): string {
        $xrefOffset = strlen($output);
        $output .= "xref\n";

        foreach (self::groups($offsets) as $group) {
            $output .= $group['start'].' '.$group['count']."\n";

            foreach ($group['numbers'] as $objectNumber) {
                $output .= sprintf("%010d %05d n \n", $offsets[$objectNumber], 0);
            }
        }

        $trailer = ['Size' => (string) $size];

        if ($root !== null) {
            $trailer['Root'] = $root;
        }

        if ($info !== null) {
            $trailer['Info'] = $info;
        }

        if ($id !== null) {
            $trailer['ID'] = $id;
        }

        $trailer['Prev'] = (string) $previousOffset;

        $output .= "trailer\n".PdfLexer::buildDictionary($trailer)."\n";
        $output .= "startxref\n".$xrefOffset."\n%%EOF\n";

        return $output;
    }

    /**
     * Group consecutive object numbers into cross reference subsections.
     *
     * @param  array<int, int>  $offsets
     * @return list<array{start: int, count: int, numbers: list<int>}>
     */
    private static function groups(array $offsets): array
    {
        $numbers = array_keys($offsets);
        sort($numbers);

        $groups = [];
        $current = [];

        foreach ($numbers as $number) {
            if ($current !== [] && $number !== $current[count($current) - 1] + 1) {
                $groups[] = self::group($current);
                $current = [];
            }

            $current[] = $number;
        }

        if ($current !== []) {
            $groups[] = self::group($current);
        }

        return $groups;
    }

    /**
     * @param  list<int>  $numbers
     * @return array{start: int, count: int, numbers: list<int>}
     */
    private static function group(array $numbers): array
    {
        return [
            'start' => $numbers[0],
            'count' => count($numbers),
            'numbers' => $numbers,
        ];
    }
}
