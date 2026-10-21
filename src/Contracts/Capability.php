<?php

declare(strict_types=1);

namespace Vellum\Contracts;

/**
 * Capabilities a driver can advertise.
 *
 * The post processing layer implements the generic capabilities (xmp, pdfa,
 * embedded files, merge, sign) for every driver, but a driver may also expose
 * native engine support.
 */
final class Capability
{
    public const XMP = 'xmp';

    public const PDFA = 'pdfa';

    public const EMBEDDED_FILES = 'embedded_files';

    public const MERGE = 'merge';

    public const SIGNATURE = 'signature';

    public const METADATA = 'metadata';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::XMP,
            self::PDFA,
            self::EMBEDDED_FILES,
            self::MERGE,
            self::SIGNATURE,
            self::METADATA,
        ];
    }
}
