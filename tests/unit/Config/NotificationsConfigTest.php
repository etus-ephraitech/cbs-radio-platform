<?php

namespace Tests\Unit\Config;

use Config\Notifications;
use Tests\Support\TestCase\UnitTestCase;

/**
 * @internal
 */
final class NotificationsConfigTest extends UnitTestCase
{
    public function testRenderReplacesEveryOccurrenceOfAToken(): void
    {
        $text = (new Notifications())->render('{sender_name} said hi. Bye, {sender_name}.', ['sender_name' => 'Grace']);

        $this->assertSame('Grace said hi. Bye, Grace.', $text);
    }

    public function testRenderFillsSeveralTokensAtOnce(): void
    {
        $text = (new Notifications())->render(
            'Ref {reference} for {recipient_name}',
            ['reference' => 'CBS-000007', 'recipient_name' => 'Mama Ruth']
        );

        $this->assertSame('Ref CBS-000007 for Mama Ruth', $text);
    }

    public function testAnUnknownTokenIsLeftAsItIs(): void
    {
        $this->assertSame('Hi {nobody}', (new Notifications())->render('Hi {nobody}', ['sender_name' => 'Grace']));
    }

    public function testAValueThatLooksLikeATokenIsNotExpandedAgain(): void
    {
        // A sender who types "{reference}" as their name must see exactly that, not an internal reference.
        $text = (new Notifications())->render('Hi {sender_name} ({reference})', ['sender_name' => '{reference}', 'reference' => 'CBS-000001']);

        $this->assertSame('Hi {reference} (CBS-000001)', $text);
    }

    public function testEveryEventHasAllThreeMessages(): void
    {
        $templates = (new Notifications())->templates;

        $this->assertSame(['booking_confirmation', 'moderation_approved', 'moderation_rejected', 'aired'], array_keys($templates));

        foreach ($templates as $event => $fields) {
            $this->assertSame(['sms', 'email_subject', 'email_body'], array_keys($fields), "{$event} is missing a message.");

            foreach ($fields as $field => $text) {
                $this->assertNotSame('', trim($text), "{$event}/{$field} is empty.");
            }
        }
    }

    public function testEveryShippedSmsFitsInThreeTextMessages(): void
    {
        foreach ((new Notifications())->templates as $event => $fields) {
            // The longest the placeholders can make it is still well inside what the settings screen allows (480).
            $this->assertLessThanOrEqual(160, mb_strlen($fields['sms']), "The {$event} SMS is longer than one text message before its placeholders are filled in.");
        }
    }
}
