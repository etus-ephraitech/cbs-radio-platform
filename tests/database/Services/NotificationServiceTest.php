<?php

namespace Tests\Database\Services;

use App\Services\NotificationService;
use Tests\Support\Concerns\SetsEnvironment;
use Tests\Support\Libraries\FakeHttpGateway;
use Tests\Support\TestCase\AppTestCase;

/**
 * The texts and emails sent to people who book. The real service runs here; the outside world (the SMS gateway,
 * the mail server) is replaced by fakes that record what would have been sent.
 *
 * @internal
 */
final class NotificationServiceTest extends AppTestCase
{
    use SetsEnvironment;

    private const GATEWAY = 'https://api.africastalking.com/version1/messaging';

    protected function setUp(): void
    {
        parent::setUp();

        helper('text');

        $this->setEnv('sms.apiKey', 'test-key');
        $this->setEnv('sms.username', 'cbsradio');
    }

    /**
     * @param array{sms?: bool, email?: bool} $channels
     */
    private function notifier(array $channels = ['sms' => true, 'email' => false]): NotificationService
    {
        $config                  = config('Notifications');
        $config->channelsEnabled = $channels + ['sms' => false, 'email' => false];
        $config->smsSenderId     = 'CBSTEST';

        return new NotificationService(null, $config);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loggedMessages(): array
    {
        return $this->db->table('notifications_log')->orderBy('id', 'ASC')->get()->getResultArray();
    }

    private function booking(array $overrides = []): array
    {
        return $this->makeBooking($overrides + ['sender_phone' => '0772123456', 'sender_email' => 'grace@example.test']);
    }

    // ------------------------------------------------------------------ texts

    public function testTheConfirmationTextGoesToTheGatewayWithTheRightDetails(): void
    {
        $http    = FakeHttpGateway::install();
        $booking = $this->booking();

        $this->notifier()->sendBookingConfirmation($booking);

        $this->assertCount(1, $http->calls);
        $this->assertSame(['POST', self::GATEWAY], [$http->calls[0]['method'], $http->calls[0]['url']]);
        $this->assertSame('test-key', $http->calls[0]['options']['headers']['apiKey']);

        $payload = $http->lastPayload();
        $this->assertSame('cbsradio', $payload['username']);
        $this->assertSame('0772123456', $payload['to']);
        $this->assertSame('CBSTEST', $payload['from'], 'The sender name comes from the configuration, not from .env.');
        $this->assertSame(
            'Thanks Grace Nakato! Your CBS Radio greeting (ref ' . cbs_booking_reference((int) $booking['id']) . ') was received and is being reviewed.',
            $payload['message']
        );
    }

    public function testASentTextIsLoggedAgainstTheBooking(): void
    {
        FakeHttpGateway::install();
        $booking = $this->booking();

        $this->notifier()->sendBookingConfirmation($booking);

        $log = $this->loggedMessages();
        $this->assertCount(1, $log);
        $this->assertSame(['sms', 'sent', '0772123456', (string) $booking['id']], [$log[0]['channel'], $log[0]['status'], $log[0]['recipient'], (string) $log[0]['booking_id']]);
    }

    public function testWithNoGatewayKeyTheTextIsNotSentAndTheFailureIsLogged(): void
    {
        $this->setEnv('sms.apiKey', null);
        $http = FakeHttpGateway::install();

        $sent = $this->notifier()->sendTest('sms', '0772123456');

        $this->assertFalse($sent);
        $this->assertSame([], $http->calls, 'No key, no call.');
        $this->assertSame('failed', $this->loggedMessages()[0]['status']);
    }

    public function testAGatewayThatRefusesTheTextIsLoggedAsFailed(): void
    {
        FakeHttpGateway::install(500);

        $this->assertFalse($this->notifier()->sendTest('sms', '0772123456'));
        $this->assertSame('failed', $this->loggedMessages()[0]['status']);
    }

    public function testANetworkErrorIsLoggedAsFailedAndNeverEscapesToTheVisitor(): void
    {
        FakeHttpGateway::install(failWith: 'Connection refused');

        $this->assertFalse($this->notifier()->sendTest('sms', '0772123456'));
        $this->assertSame('failed', $this->loggedMessages()[0]['status']);
    }

    public function testASuccessStatusFromTheGatewayCountsAsSent(): void
    {
        foreach ([200, 201, 202] as $status) {
            FakeHttpGateway::install($status);

            $this->assertTrue($this->notifier()->sendTest('sms', '0772123456'), "HTTP {$status}");
        }
    }

    // ------------------------------------------------------------------ emails

    public function testTheConfirmationEmailIsSentWithTheRenderedSubjectAndBody(): void
    {
        $booking = $this->booking();

        $this->notifier(['sms' => false, 'email' => true])->sendBookingConfirmation($booking);

        $email = service('email');
        $ref   = cbs_booking_reference((int) $booking['id']);

        $this->assertSame("We received your CBS Radio greeting — {$ref}", $email->archive['subject']);
        $this->assertStringContainsString('grace@example.test', json_encode($email->archive['recipients']));
        $this->assertStringContainsString("Your reference is {$ref}", $email->archive['body']);
        $this->assertStringContainsString('Mama Ruth', $email->archive['body']);
    }

    public function testASentEmailIsLoggedWithItsSubjectAndBody(): void
    {
        $booking = $this->booking();

        $this->notifier(['sms' => false, 'email' => true])->sendBookingConfirmation($booking);

        $log = $this->loggedMessages();
        $this->assertCount(1, $log);
        $this->assertSame(['email', 'sent', 'grace@example.test'], [$log[0]['channel'], $log[0]['status'], $log[0]['recipient']]);
        $this->assertStringStartsWith('We received your CBS Radio greeting', $log[0]['message']);
        $this->assertStringContainsString("\n\n", $log[0]['message'], 'Subject, a blank line, then the body.');
    }

    public function testAMailServerThatFailsIsLoggedAsFailed(): void
    {
        service('email')->returnValue = false;

        $this->assertFalse($this->notifier(['sms' => false, 'email' => true])->sendTest('email', 'grace@example.test'));
        $this->assertSame('failed', $this->loggedMessages()[0]['status']);
    }

    public function testTheFromAddressIsTakenFromTheConfigurationWhenSet(): void
    {
        $config                   = config('Notifications');
        $config->channelsEnabled  = ['sms' => false, 'email' => true];
        $config->emailFromAddress = 'greetings@cbs.example.test';
        $config->emailFromName    = 'CBS Radio';

        (new NotificationService(null, $config))->sendTest('email', 'grace@example.test');

        $this->assertSame('greetings@cbs.example.test', service('email')->archive['fromEmail']);
        $this->assertSame('CBS Radio', service('email')->archive['fromName']);
    }

    // ------------------------------------------------------------------ who gets what

    public function testBothChannelsAreUsedWhenBothAreOnAndTheSenderGaveBoth(): void
    {
        $http = FakeHttpGateway::install();

        $this->notifier(['sms' => true, 'email' => true])->sendBookingConfirmation($this->booking());

        $this->assertCount(1, $http->calls);
        $this->assertSame(['sms', 'email'], array_column($this->loggedMessages(), 'channel'));
    }

    public function testAChannelSwitchedOffIsNeverUsed(): void
    {
        $http = FakeHttpGateway::install();

        $this->notifier(['sms' => false, 'email' => false])->sendBookingConfirmation($this->booking());

        $this->assertSame([], $http->calls);
        $this->assertSame([], $this->loggedMessages());
    }

    public function testNoTextIsSentWithoutAPhoneNumberAndNoEmailWithoutAnAddress(): void
    {
        $http = FakeHttpGateway::install();

        $this->notifier(['sms' => true, 'email' => true])->sendBookingConfirmation($this->makeBooking(['sender_phone' => '0772123456', 'sender_email' => null]));

        $this->assertCount(1, $http->calls);
        $this->assertSame(['sms'], array_column($this->loggedMessages(), 'channel'), 'No address, so no email attempt at all.');
    }

    // ------------------------------------------------------------------ which message

    public function testTheModerationMessageDependsOnTheOutcome(): void
    {
        $http = FakeHttpGateway::install();
        $notifier = $this->notifier();

        $notifier->sendModerationResult($this->booking(['status' => 'approved']));
        $approved = $http->lastPayload()['message'];

        $notifier->sendModerationResult($this->booking(['status' => 'rejected']));
        $rejected = $http->lastPayload()['message'];

        $this->assertStringContainsString('was approved and will air soon', $approved);
        $this->assertStringContainsString('could not be approved', $rejected);
    }

    public function testTheAiredNoticeSaysItAired(): void
    {
        $http = FakeHttpGateway::install();

        $this->notifier()->sendAiredNotice($this->booking(['status' => 'aired']));

        $this->assertStringContainsString('just aired', $http->lastPayload()['message']);
    }

    // ------------------------------------------------------------------ the wording is the administrators'

    public function testTheMessagesUseTheTemplatesInForceAndFillEveryPlaceholder(): void
    {
        config('CbsRadio')->contactPhone = '0414 000 000';
        $config = config('Notifications');
        $config->templates['booking_confirmation']['sms'] = '{sender_name} / {recipient_name} / {reference} / {contact_phone}';
        $http    = FakeHttpGateway::install();
        $booking = $this->booking();

        (new NotificationService(null, $config))->sendBookingConfirmation($booking);

        $this->assertSame(
            'Grace Nakato / Mama Ruth / ' . cbs_booking_reference((int) $booking['id']) . ' / 0414 000 000',
            $http->lastPayload()['message']
        );
    }

    public function testASendersNameCannotBeUsedToPullInOtherPlaceholders(): void
    {
        $config = config('Notifications');
        $config->templates['booking_confirmation']['sms'] = 'Hi {sender_name}. Ref {reference}.';
        $http    = FakeHttpGateway::install();
        $booking = $this->booking(['sender_name' => '{reference} {contact_phone}']);

        (new NotificationService(null, $config))->sendBookingConfirmation($booking);

        $this->assertStringStartsWith('Hi {reference} {contact_phone}. Ref CBS-', $http->lastPayload()['message']);
    }

    // ------------------------------------------------------------------ resending

    public function testAFailedTextCanBeSentAgainWithItsOriginalWording(): void
    {
        $http = FakeHttpGateway::install(500);
        $this->notifier()->sendTest('sms', '0772123456');
        $failed = $this->loggedMessages()[0];

        $http = FakeHttpGateway::install(200);
        $this->assertTrue($this->notifier()->resend($failed));

        $this->assertSame($failed['message'], $http->lastPayload()['message']);
        $this->assertSame('0772123456', $http->lastPayload()['to']);
        $this->assertSame(['failed', 'sent'], array_column($this->loggedMessages(), 'status'), 'The attempt is added to the log; history is kept.');
    }

    public function testAFailedEmailCanBeSentAgainSplitIntoSubjectAndBody(): void
    {
        service('email')->returnValue = false;
        $this->notifier(['sms' => false, 'email' => true])->sendTest('email', 'grace@example.test');
        $failed = $this->loggedMessages()[0];

        service('email')->returnValue = true;
        $this->assertTrue($this->notifier(['sms' => false, 'email' => true])->resend($failed));

        $this->assertSame('CBS Radio test email', service('email')->archive['subject']);
        $this->assertStringContainsString('Your email settings are working.', service('email')->archive['body']);
    }

    // ------------------------------------------------------------------ the Settings screen's test buttons

    public function testTheTestButtonsSendTestMessagesAndAnUnknownChannelDoesNothing(): void
    {
        $http = FakeHttpGateway::install();
        $notifier = $this->notifier(['sms' => false, 'email' => false]);

        $this->assertTrue($notifier->sendTest('sms', '0772123456'), 'The test works even with the channel switched off.');
        $this->assertStringContainsString('SMS settings are working', $http->lastPayload()['message']);

        $this->assertTrue($notifier->sendTest('email', 'grace@example.test'));

        $this->assertFalse($notifier->sendTest('pigeon', 'anyone'));
        $this->assertCount(2, $this->loggedMessages(), 'An unknown channel logs nothing.');
    }
}
