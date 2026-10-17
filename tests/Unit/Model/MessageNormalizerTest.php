<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model;

use AxiTrace\Model\MessageNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * The lead message rule shared with the ingestion API (lead_message.go) and the web SDK.
 *
 * @covers \AxiTrace\Model\MessageNormalizer
 */
final class MessageNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function cases(): array
    {
        return [
            'plain text unchanged' => ['Please call me after 5 pm.', 'Please call me after 5 pm.'],
            'polish text unchanged' => ["Czy krzes\u{142}o jest r\u{F3}\u{17C}owe?", "Czy krzes\u{142}o jest r\u{F3}\u{17C}owe?"],
            'markup is text, kept verbatim' => ['<script>alert("x")</script><img src=x onerror=alert(1)>', '<script>alert("x")</script><img src=x onerror=alert(1)>'],
            'CRLF and CR become LF' => ["a\r\nb\rc", "a\nb\nc"],
            'NEL, LS and PS become LF' => ["a\u{85}b\u{2028}c\u{2029}d", "a\nb\nc\nd"],
            'tab kept' => ["size:\t42", "size:\t42"],
            'control characters removed' => ["a\x00b\x07c\x1bd\x7fe\u{9B}f", 'abcdef'],
            'bidi marks removed' => ["abc\u{202E}gpj.exe\u{202C} \u{2066}x\u{2069} \u{200E}y\u{200F} \u{61C}z", 'abcgpj.exe x y z'],
            'BOM and annotation marks removed' => ["\u{FEFF}hi\u{FFF9}there\u{FFFB}", 'hithere'],
            'zero width joiner kept' => ["\u{1F469}\u{200D}\u{1F4BB}", "\u{1F469}\u{200D}\u{1F4BB}"],
            'surrounding whitespace trimmed' => [" \t\n\u{A0} hello \u{3000}\n\n", 'hello'],
            'trailing line spaces trimmed' => ["line one   \nline two\t", "line one\nline two"],
            'two blank lines kept' => ["a\n\n\nb", "a\n\n\nb"],
            'more than two blank lines collapsed' => ["a\n\n\n\n\n\nb", "a\n\n\nb"],
            'whitespace-only lines count as blank' => ["a\n \n\t\n  \n\nb", "a\n\n\nb"],
            'only whitespace is empty' => [" \n\t\u{200E} ", ''],
            'invalid utf-8 removed' => ["ok\xff\xfeyes", 'okyes'],
        ];
    }

    /**
     * @dataProvider cases
     */
    public function testNormalize(string $input, string $expected): void
    {
        $normalized = MessageNormalizer::normalize($input);

        self::assertSame($expected, $normalized);
        self::assertSame($normalized, MessageNormalizer::normalize($normalized), 'normalization is idempotent');
    }

    public function testDecomposedTextIsComposedWhenIntlIsAvailable(): void
    {
        if (!class_exists('Normalizer')) {
            self::markTestSkipped('ext-intl is not installed');
        }

        self::assertSame("\u{F3}", MessageNormalizer::normalize("o\u{301}"));
    }

    public function testExactlyTheCapIsKeptWhole(): void
    {
        $message = str_repeat("\u{17C}", MessageNormalizer::MAX_LENGTH);

        self::assertSame($message, MessageNormalizer::normalize($message));
    }

    public function testOverTheCapIsCutAndMarked(): void
    {
        $normalized = MessageNormalizer::normalize(str_repeat("ab\u{1F600}", MessageNormalizer::MAX_LENGTH));

        self::assertSame(MessageNormalizer::MAX_LENGTH, preg_match_all('/./su', $normalized));
        self::assertSame(MessageNormalizer::TRUNCATION_MARKER, substr($normalized, -3));
        self::assertSame(1, preg_match('//u', $normalized), 'a cut never splits a character');
        self::assertSame($normalized, MessageNormalizer::normalize($normalized), 'a cut message is stable');
    }
}
