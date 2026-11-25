<?php

namespace Tests\Feature;

use Tests\Support\Libraries\AudioFixtures;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * Guards the feature-test foundation: requests reach the application, the staff stand-in lets them through the
 * `auth` group, and a fixture counts as an upload.
 *
 * @internal
 */
final class TestEnvironmentTest extends AppFeatureTestCase
{
    public function testAPublicPageIsServed(): void
    {
        $result = $this->get('/');

        $result->assertOK();
        $result->assertSee('Say it on air.');
    }

    public function testStaffScreensAreReachableThroughTheStandInFilter(): void
    {
        $this->get('admin/message-templates')->assertOK();
    }

    public function testAFixtureBehavesLikeAnUpload(): void
    {
        $upload = AudioFixtures::upload('chrome-recording.webm');

        $this->assertTrue($upload->isValid(), 'The upload shim (tests/_support/overrides) is not loaded.');
        $this->assertSame('video/webm', $upload->getMimeType());
    }

    public function testAFixtureAttachedToARequestIsSeenByTheApplication(): void
    {
        AudioFixtures::attachToRequest('voice_note', 'chrome-recording.webm');

        // A request built now, as the feature helper builds one for every call.
        $file = service('incomingrequest', config(\Config\App::class), false)->getFile('voice_note');

        $this->assertNotNull($file);
        $this->assertTrue($file->isValid());
        $this->assertSame('video/webm', $file->getMimeType());
    }

    public function testAFlashMessageSetByARequestCanBeRead(): void
    {
        $category = $this->makeCategory();

        // An unknown category slug redirects back to the list with an error message.
        $this->get('booking/no-such-category')->assertRedirect();

        $this->assertSame('Category not found.', $this->flash('toastr_error'));
        $this->assertNotNull($category);
    }
}
