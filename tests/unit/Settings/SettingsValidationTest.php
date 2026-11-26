<?php

namespace Tests\Unit\Settings;

use App\Libraries\Settings\SettingsManager;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Support\TestCase\UnitTestCase;

/**
 * What the settings screen accepts and refuses, one rule at a time. (Saving, auditing and the combinations of
 * settings are tested with the database in tests/database/SettingsManagerTest.php.)
 *
 * @internal
 */
final class SettingsValidationTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $def
     */
    private function validate(array $def, string $raw): ?string
    {
        $method = new ReflectionMethod(SettingsManager::class, 'validate');

        return $method->invoke(new SettingsManager(), $def, $raw);
    }

    // ------------------------------------------------------------------ formats

    #[DataProvider('formats')]
    public function testFormat(string $format, string $value, bool $accepted): void
    {
        $error = $this->validate(['type' => 'string', 'format' => $format, 'maxLength' => 255, 'label' => 'x'], $value);

        $accepted
            ? $this->assertNull($error, "'{$value}' should be a valid {$format}.")
            : $this->assertNotNull($error, "'{$value}' should not be a valid {$format}.");
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function formats(): array
    {
        return [
            'phone: local'                 => ['phone', '0772123456', true],
            'phone: international'         => ['phone', '+256 772 123 456', true],
            'phone: brackets after the first digit' => ['phone', '0414 (123) 456', true],
            'phone: hyphens'               => ['phone', '0414-123-456', true],
            // The rule is "an optional +, then a digit first". A number that STARTS with a bracket, as in
            // "(0414) 123-456" or "(+256) 414 123456", is therefore refused today. Pinned here so that
            // loosening it is a decision, not an accident.
            'phone: starting with a bracket' => ['phone', '(0414) 123-456', false],
            'phone: letters'               => ['phone', 'call me', false],
            'phone: too short'             => ['phone', '12', false],
            'phone: only a plus'           => ['phone', '+', false],
            'phone: far too long'          => ['phone', '123456789012345678901', false],

            'email: ordinary'              => ['email', 'info@cbs.example', true],
            'email: no domain'             => ['email', 'info@', false],
            'email: no at sign'            => ['email', 'info.cbs.example', false],
            'email: a space'               => ['email', 'in fo@cbs.example', false],

            'currency: UGX'                => ['currency', 'UGX', true],
            'currency: lower case'         => ['currency', 'ugx', false],
            'currency: four letters'       => ['currency', 'UGXX', false],
            'currency: digits'             => ['currency', 'U6X', false],

            'sender id: letters'           => ['senderid', 'CBSRADIO', true],
            'sender id: letters and digits' => ['senderid', 'Radio1', true],
            'sender id: eleven characters' => ['senderid', 'ABCDEFGHIJK', true],
            'sender id: twelve characters' => ['senderid', 'ABCDEFGHIJKL', false],
            'sender id: a space'           => ['senderid', 'CBS RADIO', false],
            'sender id: punctuation'       => ['senderid', 'CBS-RADIO', false],

            'url: https'                   => ['url', 'https://stream.example.com/cbs', true],
            'url: http'                    => ['url', 'http://stream.example.com:8000/live', true],
            'url: javascript'              => ['url', 'javascript:alert(1)', false],
            'url: data'                    => ['url', 'data:text/html,<script>alert(1)</script>', false],
            'url: ftp'                     => ['url', 'ftp://example.com/stream', false],
            'url: protocol-relative'       => ['url', '//example.com/x', false],
            'url: not a url'               => ['url', 'not a url', false],
            'url: no host'                 => ['url', 'https://', false],

            'datetime: ordinary'           => ['datetime', '2026-12-01 09:00', true],
            'datetime: midnight'           => ['datetime', '2026-12-01 00:00', true],
            'datetime: last minute of the year' => ['datetime', '2026-12-31 23:59', true],
            'datetime: a leap day'         => ['datetime', '2028-02-29 12:00', true],
            // A date that does not exist must be refused, not quietly rolled over to the next month.
            'datetime: not a leap year'    => ['datetime', '2026-02-29 12:00', false],
            'datetime: 30 February'        => ['datetime', '2026-02-30 10:00', false],
            'datetime: month 13'           => ['datetime', '2026-13-01 10:00', false],
            'datetime: hour 24'            => ['datetime', '2026-12-01 24:00', false],
            'datetime: minute 60'          => ['datetime', '2026-12-01 09:60', false],
            'datetime: one-digit hour'     => ['datetime', '2026-12-01 9:00', false],
            'datetime: no time'            => ['datetime', '2026-12-01', false],
            'datetime: seconds as well'    => ['datetime', '2026-12-01 09:00:00', false],
            'datetime: a words date'       => ['datetime', 'tomorrow', false],
            'datetime: day first'          => ['datetime', '01-12-2026 09:00', false],
            'datetime: raw picker value'   => ['datetime', '2026-12-01T09:00', false],
        ];
    }

    public function testNoFormatMeansAnythingGoes(): void
    {
        $this->assertNull($this->validate(['type' => 'string', 'maxLength' => 50, 'label' => 'x'], 'anything <at> all'));
    }

    // ------------------------------------------------------------------ required, length, whole numbers

    public function testARequiredSettingCannotBeEmpty(): void
    {
        $error = $this->validate(['type' => 'string', 'required' => true, 'maxLength' => 10, 'label' => 'x'], '');

        $this->assertSame('cannot be empty. Use "Reset to default" instead.', $error);
    }

    public function testAnOptionalSettingMayBeEmptyEvenWithAFormat(): void
    {
        $this->assertNull($this->validate(['type' => 'string', 'format' => 'email', 'maxLength' => 150, 'label' => 'x'], ''));
    }

    public function testTextHasAMaximumLength(): void
    {
        $def = ['type' => 'text', 'maxLength' => 10, 'label' => 'x'];

        $this->assertNull($this->validate($def, str_repeat('a', 10)));
        $this->assertSame('is too long (max 10 characters).', $this->validate($def, str_repeat('a', 11)));
    }

    public function testLengthIsCountedInCharacters(): void
    {
        $this->assertNull($this->validate(['type' => 'string', 'maxLength' => 5, 'label' => 'x'], 'ééééé'));
    }

    #[DataProvider('integers')]
    public function testInteger(string $raw, ?string $expectedError): void
    {
        $this->assertSame($expectedError, $this->validate(['type' => 'int', 'min' => 5, 'max' => 200, 'label' => 'x'], $raw));
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function integers(): array
    {
        return [
            'the minimum'        => ['5', null],
            'the maximum'        => ['200', null],
            'in the middle'      => ['45', null],
            'one below'          => ['4', 'must be between 5 and 200.'],
            'one above'          => ['201', 'must be between 5 and 200.'],
            'a decimal'          => ['4.5', 'must be a whole number.'],
            'negative'           => ['-3', 'must be a whole number.'],
            'exponent notation'  => ['1e3', 'must be a whole number.'],
            'with spaces'        => [' 5', 'must be a whole number.'],
            'words'              => ['ten', 'must be a whole number.'],
            'empty'              => ['', 'must be a whole number.'],
        ];
    }

    // ------------------------------------------------------------------ choices and lists

    public function testASelectOnlyAcceptsItsOptions(): void
    {
        $def = ['type' => 'select', 'options' => ['manual' => 'Manual', 'mobile_money' => 'Mobile Money'], 'label' => 'x'];

        $this->assertNull($this->validate($def, 'manual'));
        $this->assertSame('is not one of the available choices.', $this->validate($def, 'bitcoin'));
        $this->assertSame('is not one of the available choices.', $this->validate($def, ''));
    }

    public function testAListHasAMaximumNumberOfEntries(): void
    {
        $def = ['type' => 'list', 'maxItems' => 3, 'maxLength' => 10, 'label' => 'x'];

        $this->assertNull($this->validate($def, "a\nb\nc"));
        $this->assertSame('can have at most 3 entries.', $this->validate($def, "a\nb\nc\nd"));
    }

    public function testEachListEntryHasAMaximumLength(): void
    {
        $def   = ['type' => 'list', 'maxItems' => 10, 'maxLength' => 10, 'label' => 'x'];
        $error = $this->validate($def, "short\n" . str_repeat('x', 11));

        $this->assertStringStartsWith('entries can be at most 10 characters', (string) $error);
        $this->assertStringContainsString('…', (string) $error, 'The message shows which entry is too long.');
    }

    // ------------------------------------------------------------------ placeholders in message templates

    public function testKnownPlaceholdersAreAccepted(): void
    {
        $def = ['type' => 'text', 'maxLength' => 480, 'tokens' => true, 'label' => 'x'];

        $this->assertNull($this->validate($def, 'Hi {sender_name}, ref {reference} for {recipient_name}. Call {contact_phone}.'));
        $this->assertNull($this->validate($def, 'No placeholders at all.'));
    }

    public function testAnUnknownPlaceholderIsRefusedAndTheAllowedOnesListed(): void
    {
        $def   = ['type' => 'text', 'maxLength' => 480, 'tokens' => true, 'label' => 'x'];
        $error = $this->validate($def, 'Hi {sender}, ref {reference}');

        $this->assertSame(
            'uses {sender}, which is not a known placeholder. You can use: {reference}, {sender_name}, {recipient_name}, {contact_phone}.',
            $error
        );
    }

    public function testPlaceholdersAreOnlyCheckedWhereTheyAreMeaningful(): void
    {
        // A plain text setting (no 'tokens' flag) may contain braces freely.
        $this->assertNull($this->validate(['type' => 'text', 'maxLength' => 100, 'label' => 'x'], 'Use {curly} braces'));
    }
}
