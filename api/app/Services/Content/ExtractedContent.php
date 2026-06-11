<?php

namespace App\Services\Content;

class ExtractedContent
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $title,
        public readonly string $finalUrl,
    ) {}
}
