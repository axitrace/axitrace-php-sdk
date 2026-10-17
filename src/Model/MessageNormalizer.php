<?php

declare(strict_types=1);

namespace AxiTrace\Model;

/**
 * Normalizes the free-text inquiry message of a lead event (the text a visitor types
 * into "Your remarks" on an inquiry form).
 *
 * The message is plain text, never markup. The rule is the one the ingestion API applies
 * again on arrival, so what this SDK sends is what gets stored:
 *  1. invalid UTF-8 removed, Unicode NFC when ext-intl is available, CRLF / CR / NEL /
 *     LS / PS turned into LF;
 *  2. control characters removed except LF and TAB, and so are the bidi embedding,
 *     override and isolate marks, LRM / RLM / ALM, the BOM and the interlinear annotation
 *     marks;
 *  3. trailing spaces and tabs removed from every line, runs of more than two blank lines
 *     collapsed to two, leading and trailing whitespace trimmed;
 *  4. longer than MAX_LENGTH code points: cut, and an ellipsis appended inside the cap.
 *
 * Pure PCRE: works on PHP 7.4 without ext-mbstring.
 */
final class MessageNormalizer
{
    /**
     * Maximum length of a message in Unicode code points, after normalization.
     */
    public const MAX_LENGTH = 5000;

    /**
     * Ends a message that was cut to MAX_LENGTH (U+2026 HORIZONTAL ELLIPSIS).
     */
    public const TRUNCATION_MARKER = "\xE2\x80\xA6";

    private const VALID_UTF8 = '/[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]'
        . '|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}'
        . '|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}/';

    private const INVISIBLE_MARKS = '/[\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}\x{FFF9}-\x{FFFB}]/u';

    private function __construct()
    {
    }

    /**
     * @param string $text
     * @return string The normalized message, '' when nothing is left.
     */
    public static function normalize(string $text): string
    {
        $text = self::validUtf8($text);

        if (class_exists('Normalizer')) {
            $composed = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($composed)) {
                $text = $composed;
            }
        }

        $text = str_replace(["\r\n", "\r", "\xC2\x85", "\xE2\x80\xA8", "\xE2\x80\xA9"], "\n", $text);
        $text = (string) preg_replace('/(?![\n\t])\p{Cc}/u', '', $text);
        $text = (string) preg_replace(self::INVISIBLE_MARKS, '', $text);

        $lines = [];
        $blank = 0;
        foreach (explode("\n", $text) as $line) {
            $line = (string) preg_replace('/[ \t\p{Zs}]+$/u', '', $line);
            if ($line === '') {
                $blank++;
                if ($blank > 2) {
                    continue;
                }
            } else {
                $blank = 0;
            }
            $lines[] = $line;
        }

        $text = self::trim(implode("\n", $lines));

        if ((int) preg_match_all('/./su', $text) <= self::MAX_LENGTH) {
            return $text;
        }

        preg_match('/^.{' . (self::MAX_LENGTH - 1) . '}/su', $text, $match);

        return rtrim(self::trim($match[0] ?? ''), "\n") . self::TRUNCATION_MARKER;
    }

    private static function validUtf8(string $text): string
    {
        if (preg_match('//u', $text) === 1) {
            return $text;
        }

        preg_match_all(self::VALID_UTF8, $text, $characters);

        return implode('', $characters[0]);
    }

    private static function trim(string $text): string
    {
        return (string) preg_replace('/^[\s\p{Zs}]+|[\s\p{Zs}]+$/u', '', $text);
    }
}
