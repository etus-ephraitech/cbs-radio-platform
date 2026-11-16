<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase\UnitTestCase;

/**
 * @internal
 */
final class TextHelperTest extends UnitTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        helper('text');
    }

    public function testShortTextIsReturnedWhole(): void
    {
        $this->assertSame('Short and sweet', cbs_excerpt('Short and sweet', 50));
        $this->assertSame('exactly10!!', cbs_excerpt('exactly10!!', 11), 'Text exactly at the limit is not cut.');
    }

    public function testLongTextIsCutAtAWordBoundary(): void
    {
        $this->assertSame('The quick brown fox…', cbs_excerpt('The quick brown fox jumps over the lazy dog', 20));
    }

    public function testTextWithNoSpaceIsCutAtTheLimit(): void
    {
        $this->assertSame('abcde…', cbs_excerpt('abcdefghij', 5));
    }

    public function testTheSuffixCanBeChanged(): void
    {
        $this->assertSame('The quick brown fox (more)', cbs_excerpt('The quick brown fox jumps over the lazy dog', 20, ' (more)'));
    }

    public function testMarkupIsRemovedBeforeMeasuring(): void
    {
        $this->assertSame('Bold and fine', cbs_excerpt('<b>Bold</b> and <i>fine</i>', 50));
    }

    public function testMultibyteTextIsMeasuredInCharactersNotBytes(): void
    {
        $this->assertSame('éééééé', cbs_excerpt('éééééé', 6));
    }

    #[DataProvider('slugs')]
    public function testSlug(string $input, string $expected): void
    {
        $this->assertSame($expected, cbs_slug($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function slugs(): array
    {
        return [
            'plain words'                      => ['Birthdays', 'birthdays'],
            'punctuation becomes one hyphen'   => ['Tributes & In Memoriam', 'tributes-in-memoriam'],
            'surrounding space and symbols'    => ['  Hello   World!  ', 'hello-world'],
            'digits are kept'                  => ['Top 10 Hits', 'top-10-hits'],
            'nothing usable'                   => ['!!!', ''],
            'empty'                            => ['', ''],
        ];
    }

    #[DataProvider('phones')]
    public function testMaskedPhoneKeepsOnlyTheEnds(string $input, string $expected): void
    {
        $this->assertSame($expected, cbs_mask_phone($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function phones(): array
    {
        return [
            'a local number'                 => ['0772123456', '0772***456'],
            'an international number'        => ['+256 772 123 456', '2567*****456'],
            'too short to show any of it'    => ['12345', '*****'],
            'exactly six digits is still all hidden' => ['123456', '******'],
            'seven digits shows both ends'   => ['1234567', '1234567'],
            'no digits at all'               => ['abc', ''],
        ];
    }

    #[DataProvider('emails')]
    public function testMaskedEmailKeepsTheDomainAndTwoLetters(string $input, string $expected): void
    {
        $this->assertSame($expected, cbs_mask_email($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function emails(): array
    {
        return [
            'an ordinary address'        => ['grace@example.com', 'gr***@example.com'],
            'a long local part'          => ['nakato.grace@example.com', 'na**********@example.com'],
            'a very short local part'    => ['ab@x.co', 'ab***@x.co'],
            'not an address'             => ['not-an-email', 'not-an-email'],
        ];
    }

    public function testReferencesArePaddedAndPrefixed(): void
    {
        $this->assertSame('CBS-000005', cbs_booking_reference(5));
        $this->assertSame('CBS-123456', cbs_booking_reference(123456));
        $this->assertSame('CBS-1234567', cbs_booking_reference(1234567), 'Padding never truncates a bigger number.');
        $this->assertSame('GFT-000012', cbs_gift_reference(12));
    }
}
