<?php

namespace App\Domain\Typography;

class TypographyRules
{
    private const NBSP = "\u{00A0}";
    private const NB_HYPHEN = "\u{2011}";

    /**
     * Apply widow/orphan protection and hyphenation tightening to the given text.
     */
    public function apply(string $text): string
    {
        $paragraphs = preg_split("/(\r?\n){2,}/", $text) ?: [$text];

        $processed = array_map(function (string $paragraph): string {
            $paragraph = $this->protectShortWordOrphans($paragraph);
            $paragraph = $this->protectWidow($paragraph);

            return $this->protectHyphenatedCompounds($paragraph);
        }, $paragraphs);

        return $this->rejoinParagraphs($processed, $text);
    }

    private function protectShortWordOrphans(string $paragraph): string
    {
        $pattern = '/(?<!\S)([\p{L}]{1,3})\s+(?=\p{L})/u';

        return preg_replace($pattern, '$1'.self::NBSP, $paragraph) ?? $paragraph;
    }

    private function protectWidow(string $paragraph): string
    {
        if (str_word_count($paragraph) < 2) {
            return $paragraph;
        }

        return preg_replace('/\s+(\S+)$/u', self::NBSP.'$1', $paragraph) ?? $paragraph;
    }

    private function protectHyphenatedCompounds(string $paragraph): string
    {
        return preg_replace('/(?<=\p{L})-(?=\p{L})/u', self::NB_HYPHEN, $paragraph) ?? $paragraph;
    }

    private function rejoinParagraphs(array $paragraphs, string $original): string
    {
        if (preg_match('/\r?\n{2,}/', $original, $matches)) {
            return implode($matches[0], $paragraphs);
        }

        return implode("\n\n", $paragraphs);
    }
}
