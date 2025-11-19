<?php

namespace Tests\Unit;

use App\Domain\Typography\TypographyRules;
use PHPUnit\Framework\TestCase;

class TypographyRulesTest extends TestCase
{
    public function test_it_protects_widows(): void
    {
        $service = new TypographyRules();

        $result = $service->apply('This sentence should avoid widows');

        $this->assertSame("This sentence should avoid\u{00A0}widows", $result);
    }

    public function test_it_prevents_orphans_after_short_words(): void
    {
        $service = new TypographyRules();

        $result = $service->apply('A mix of short words in a paragraph');

        $this->assertSame("A\u{00A0}mix\u{00A0}of\u{00A0}short\u{00A0}words\u{00A0}in\u{00A0}a\u{00A0}paragraph", $result);
    }

    public function test_it_preserves_paragraph_breaks_and_hyphenation(): void
    {
        $service = new TypographyRules();

        $input = "A state-of-the-art tool\n\nAnother paragraph";
        $result = $service->apply($input);

        $expectedFirst = "A\u{00A0}state‑of‑the‑art\u{00A0}tool";
        $expectedSecond = "Another\u{00A0}paragraph";

        $this->assertSame($expectedFirst."\n\n".$expectedSecond, $result);
    }
}
