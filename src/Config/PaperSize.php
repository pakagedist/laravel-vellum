<?php

declare(strict_types=1);

namespace Vellum\Config;

use Vellum\Exceptions\InvalidConfigurationException;

/**
 * Paper sizes understood by the package.
 */
enum PaperSize: string
{
    case A3 = 'a3';
    case A4 = 'a4';
    case A5 = 'a5';
    case B5 = 'b5';
    case Letter = 'letter';
    case Legal = 'legal';
    case Tabloid = 'tabloid';
    case Executive = 'executive';

    /**
     * The label used by the different engines ("A4", "Letter"...).
     */
    public function label(): string
    {
        return match ($this) {
            self::A3 => 'A3',
            self::A4 => 'A4',
            self::A5 => 'A5',
            self::B5 => 'B5',
            self::Letter => 'Letter',
            self::Legal => 'Legal',
            self::Tabloid => 'Tabloid',
            self::Executive => 'Executive',
        };
    }

    /**
     * The size in millimetres, used when an engine only understands points.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private const DIMENSIONS = [
        'a3' => [297.0, 420.0],
        'a4' => [210.0, 297.0],
        'a5' => [148.0, 210.0],
        'b5' => [176.0, 250.0],
        'letter' => [215.9, 279.4],
        'legal' => [215.9, 355.6],
        'tabloid' => [279.4, 431.8],
        'executive' => [184.15, 266.7],
    ];

    public function widthInMillimetres(): float
    {
        return self::DIMENSIONS[$this->value][0];
    }

    public function heightInMillimetres(): float
    {
        return self::DIMENSIONS[$this->value][1];
    }

    /**
     * Resolve a paper size from a user supplied string.
     */
    public static function parse(string $value): self
    {
        $normalized = strtolower(trim($value));

        $case = self::tryFrom($normalized);

        if ($case instanceof self) {
            return $case;
        }

        throw InvalidConfigurationException::unsupportedPaperSize($value);
    }
}
