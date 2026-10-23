<?php

declare(strict_types=1);

namespace Vellum\Support\Pdf;

/**
 * Builds a minimal, valid and deterministic PDF document.
 *
 * It is used by the fake driver so tests can exercise the whole pipeline
 * without rendering a real engine.
 */
final class MiniPdf
{
    /**
     * @param  array<string, string>  $options  Extra entries of the information dictionary.
     */
    public static function create(string $text = 'Vellum', array $options = []): string
    {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);

        $stream = "BT\n/F1 14 Tf\n72 760 Td\n(".$escaped.") Tj\nET\n";

        $information = [
            'Producer' => 'Vellum',
            'Creator' => 'Vellum',
        ];

        foreach ($options as $key => $value) {
            $information[$key] = $value;
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] '
                .'/Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            5 => '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream',
        ];

        $info = '<<';

        foreach ($information as $key => $value) {
            $info .= ' /'.$key.' '.PdfLexer::encodeTextString($value);
        }

        $info .= ' >>';

        $objects[6] = $info;

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $size = max(array_keys($objects)) + 1;

        $pdf .= "xref\n0 ".$size."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($number = 1; $number < $size; $number++) {
            $pdf .= array_key_exists($number, $offsets)
                ? sprintf("%010d 00000 n \n", $offsets[$number])
                : "0000000000 65535 f \n";
        }

        $pdf .= "trailer\n<< /Size ".$size." /Root 1 0 R /Info 6 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF\n";

        return $pdf;
    }
}
