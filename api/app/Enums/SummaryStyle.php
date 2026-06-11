<?php

namespace App\Enums;

enum SummaryStyle: string
{
    case Tldr = 'tldr';
    case Bullets = 'bullets';
    case Short = 'short';

    /** Output token cap per style (cost guardrail). */
    public function maxTokens(): int
    {
        return match ($this) {
            self::Tldr => 300,
            self::Short => 250,
            self::Bullets => 400,
        };
    }

    /** Per-style instruction appended to the user prompt. */
    public function instruction(): string
    {
        return match ($this) {
            self::Tldr => 'Write a single tight paragraph (TL;DR) capturing the core message.',
            self::Bullets => 'Write 4–7 bullet points covering the key points, most important first.',
            self::Short => 'Write a 3–4 sentence summary.',
        };
    }
}
