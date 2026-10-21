<?php

declare(strict_types=1);

namespace Vellum\Support;

/**
 * Document metadata written to the PDF /Info dictionary and XMP packet.
 */
final class DocumentMetadata
{
    /**
     * @param  array<string, string>  $custom  Free form key/value pairs.
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $author = null,
        public readonly ?string $subject = null,
        public readonly ?string $keywords = null,
        public readonly ?string $creator = null,
        public readonly ?string $producer = null,
        public readonly ?string $creationDate = null,
        public readonly ?string $modificationDate = null,
        public readonly ?string $language = null,
        public readonly array $custom = [],
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function fromArray(array $metadata): self
    {
        $string = static function (mixed $value): ?string {
            if ($value === null) {
                return null;
            }

            if (is_string($value)) {
                return $value;
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }

            return null;
        };

        $known = [
            'title', 'author', 'subject', 'keywords', 'creator', 'producer',
            'creation_date', 'modification_date', 'language',
        ];

        $custom = [];

        foreach ($metadata as $key => $value) {
            if (in_array((string) $key, $known, true)) {
                continue;
            }

            $converted = $string($value);

            if ($converted !== null) {
                $custom[(string) $key] = $converted;
            }
        }

        return new self(
            title: $string($metadata['title'] ?? null),
            author: $string($metadata['author'] ?? null),
            subject: $string($metadata['subject'] ?? null),
            keywords: $string($metadata['keywords'] ?? null),
            creator: $string($metadata['creator'] ?? null),
            producer: $string($metadata['producer'] ?? null),
            creationDate: $string($metadata['creation_date'] ?? null),
            modificationDate: $string($metadata['modification_date'] ?? null),
            language: $string($metadata['language'] ?? null),
            custom: $custom,
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $data = array_filter([
            'title' => $this->title,
            'author' => $this->author,
            'subject' => $this->subject,
            'keywords' => $this->keywords,
            'creator' => $this->creator,
            'producer' => $this->producer,
            'creation_date' => $this->creationDate,
            'modification_date' => $this->modificationDate,
            'language' => $this->language,
        ], static fn (?string $value): bool => $value !== null);

        foreach ($this->custom as $key => $value) {
            $data[$key] = $value;
        }

        return $data;
    }
}
