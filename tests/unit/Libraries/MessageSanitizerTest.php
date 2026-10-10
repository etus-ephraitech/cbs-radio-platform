<?php

namespace Tests\Unit\Libraries;

use App\Libraries\MessageSanitizer;
use Config\CbsRadio;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase\UnitTestCase;

/**
 * @internal
 */
final class MessageSanitizerTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $config properties to set on the CbsRadio config, e.g. ['messageMaxLength' => 50]
     */
    private function sanitizer(array $config = []): MessageSanitizer
    {
        $cbs = new CbsRadio();

        foreach ($config as $property => $value) {
            $cbs->{$property} = $value;
        }

        return new MessageSanitizer($cbs);
    }

    // ------------------------------------------------------------------ sanitize

    public function testMarkupIsStrippedAndWhitespaceCollapsed(): void
    {
        $this->assertSame(
            'Happy birthday Mama Ruth',
            $this->sanitizer()->sanitize("  <b>Happy</b>\n\n birthday\t Mama   Ruth  ")
        );
    }

    public function testAScriptTagCannotSurviveSanitising(): void
    {
        $clean = $this->sanitizer()->sanitize('<script>alert(1)</script>Hi <img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<', $clean);
        $this->assertStringNotContainsString('>', $clean);
    }

    public function testPlainPunctuationAndAccentsAreLeftAlone(): void
    {
        $this->assertSame("Congrats, José! It's 5 > 3 & we're proud.", $this->sanitizer()->sanitize("Congrats, José! It's 5 > 3 & we're proud."));
    }

    // ------------------------------------------------------------------ validateMessage

    #[DataProvider('emptyMessages')]
    public function testAnEmptyMessageIsRefused(string $input): void
    {
        $result = $this->sanitizer()->validateMessage($input);

        $this->assertFalse($result['valid']);
        $this->assertSame('Message cannot be empty.', $result['message']);
        $this->assertSame('', $result['sanitized']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function emptyMessages(): array
    {
        return [
            'nothing'          => [''],
            'only spaces'      => ['     '],
            'only line breaks' => ["\n\r\n\t"],
            'only markup'      => ['<b></b><i> </i>'],
        ];
    }

    public function testAMessageAtTheLimitIsAccepted(): void
    {
        $result = $this->sanitizer(['messageMaxLength' => 50])->validateMessage(str_repeat('a', 50));

        $this->assertTrue($result['valid']);
    }

    public function testAMessageOneOverTheLimitIsRefusedWithTheLimitInTheMessage(): void
    {
        $result = $this->sanitizer(['messageMaxLength' => 50])->validateMessage(str_repeat('a', 51));

        $this->assertFalse($result['valid']);
        $this->assertSame('Message is too long (max 50 characters).', $result['message']);
    }

    public function testLengthIsCountedInCharactersNotBytes(): void
    {
        // 50 two-byte characters is 100 bytes but 50 characters, and must be accepted.
        $this->assertTrue($this->sanitizer(['messageMaxLength' => 50])->validateMessage(str_repeat('é', 50))['valid']);
    }

    public function testLengthIsCountedAfterWhitespaceIsCollapsed(): void
    {
        $sanitizer = $this->sanitizer(['messageMaxLength' => 50]);

        // 42 characters as typed, but only "a b" once the run of spaces is collapsed: well within the limit.
        $this->assertTrue($sanitizer->validateMessage('a' . str_repeat(' ', 40) . 'b')['valid']);

        // 30 + 1 + 30 once collapsed: over the limit.
        $this->assertFalse($sanitizer->validateMessage(str_repeat('a', 30) . str_repeat(' ', 20) . str_repeat('b', 30))['valid']);
    }

    public function testAValidMessageComesBackSanitised(): void
    {
        $result = $this->sanitizer()->validateMessage("  <b>Happy</b>   birthday!\n");

        $this->assertSame(['valid' => true, 'message' => '', 'sanitized' => 'Happy birthday!'], $result);
    }

    // ------------------------------------------------------------------ unfilled example markers

    #[DataProvider('markers')]
    public function testAnUnfilledMarkerIsRefusedWhateverItsCase(string $typed, string $reported): void
    {
        $result = $this->sanitizer()->validateMessage("Happy birthday {$typed}, enjoy!");

        $this->assertFalse($result['valid']);
        $this->assertSame("Replace {$reported} with a name before sending.", $result['message']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function markers(): array
    {
        return [
            'recipient marker'          => ['[their name]', '[their name]'],
            'recipient, upper case'     => ['[THEIR NAME]', '[their name]'],
            'sender marker'             => ['[your name]', '[your name]'],
            'sender, mixed case'        => ['[Your Name]', '[your name]'],
        ];
    }

    public function testAMarkerIsReportedBeforeALengthProblem(): void
    {
        $result = $this->sanitizer(['messageMaxLength' => 50])->validateMessage(str_repeat('x', 60) . ' [their name]');

        $this->assertStringStartsWith('Replace [their name]', $result['message']);
    }

    public function testOrdinarySquareBracketsAreNotMarkers(): void
    {
        $this->assertTrue($this->sanitizer()->validateMessage('Hi [Ruth], enjoy (this [bracketed] bit)!')['valid']);
        $this->assertNull($this->sanitizer()->unfilledPlaceholder('Hi [Ruth]'));
    }

    public function testUnfilledPlaceholderReportsTheFirstMarkerOrNull(): void
    {
        $sanitizer = $this->sanitizer();

        $this->assertSame('[their name]', $sanitizer->unfilledPlaceholder('To [THEIR NAME] from [your name]'));
        $this->assertSame('[your name]', $sanitizer->unfilledPlaceholder('From [your name]'));
        $this->assertNull($sanitizer->unfilledPlaceholder('Nothing to fill in'));
    }

    // ------------------------------------------------------------------ validateRecipientName

    public function testARecipientNameIsRequired(): void
    {
        $result = $this->sanitizer()->validateRecipientName('  <i> </i> ');

        $this->assertFalse($result['valid']);
        $this->assertSame('Recipient name cannot be empty.', $result['message']);
    }

    public function testARecipientNameHasALimit(): void
    {
        $sanitizer = $this->sanitizer(['recipientNameMaxLength' => 20]);

        $this->assertTrue($sanitizer->validateRecipientName(str_repeat('a', 20))['valid']);

        $result = $sanitizer->validateRecipientName(str_repeat('a', 21));
        $this->assertFalse($result['valid']);
        $this->assertSame('Recipient name is too long (max 20 characters).', $result['message']);
    }

    public function testARecipientNameIsSanitised(): void
    {
        $this->assertSame('Mama Ruth', $this->sanitizer()->validateRecipientName(' <i>Mama</i>  Ruth ')['sanitized']);
    }

    // ------------------------------------------------------------------ flaggedTerms

    public function testNothingIsFlaggedWhenThereAreNoTerms(): void
    {
        $this->assertSame([], $this->sanitizer(['moderationFlagTerms' => []])->flaggedTerms('anything at all'));
    }

    public function testATermMatchesWhateverItsCase(): void
    {
        $this->assertSame(['scam'], $this->sanitizer(['moderationFlagTerms' => ['SCAM']])->flaggedTerms('This is a Scam!'));
    }

    public function testATermOnlyMatchesWholeWords(): void
    {
        $sanitizer = $this->sanitizer(['moderationFlagTerms' => ['ass']]);

        $this->assertSame([], $sanitizer->flaggedTerms('A glass of class'), 'A term inside another word is not a match.');
        $this->assertSame(['ass'], $sanitizer->flaggedTerms('what an ass'));
    }

    public function testAPhraseMatches(): void
    {
        $this->assertSame(['free money'], $this->sanitizer(['moderationFlagTerms' => ['free money']])->flaggedTerms('Get FREE MONEY now'));
    }

    public function testEachMatchingTermIsReportedOnceInConfiguredOrder(): void
    {
        $sanitizer = $this->sanitizer(['moderationFlagTerms' => ['scam', 'free money', 'lottery']]);

        $this->assertSame(['scam', 'lottery'], $sanitizer->flaggedTerms('lottery lottery scam lottery'));
    }

    public function testBlankTermsAreIgnored(): void
    {
        $sanitizer = $this->sanitizer(['moderationFlagTerms' => ['', '   ', 'scam']]);

        $this->assertSame(['scam'], $sanitizer->flaggedTerms('a scam'));
        $this->assertSame([], $sanitizer->flaggedTerms('perfectly fine'));
    }

    public function testRegexCharactersInATermAreTakenLiterally(): void
    {
        $sanitizer = $this->sanitizer(['moderationFlagTerms' => ['c.o.d']]);

        $this->assertSame(['c.o.d'], $sanitizer->flaggedTerms('pay c.o.d please'));
        $this->assertSame([], $sanitizer->flaggedTerms('cxoxd'), 'A "." in a term is a full stop, not "any character".');
    }

    public function testAccentedTermsMatchIgnoringCase(): void
    {
        $this->assertSame(['café'], $this->sanitizer(['moderationFlagTerms' => ['café']])->flaggedTerms('Meet at the CAFÉ'));
    }
}
