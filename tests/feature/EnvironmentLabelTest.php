<?php

namespace Tests\Feature;

use Tests\Support\Concerns\SetsEnvironment;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * `app.environmentLabel` marks a non-live copy (staging): a striped bar on every page and no search indexing.
 *
 * @internal
 */
final class EnvironmentLabelTest extends AppFeatureTestCase
{
    use SetsEnvironment;

    public function testTheLiveSiteShowsNoBarAndCanBeIndexed(): void
    {
        $this->setEnv('app.environmentLabel', null);

        $body = $this->get('/')->getBody();

        $this->assertStringNotContainsString('cbs-env-banner', $body);
        $this->assertStringNotContainsString('noindex', $body);
    }

    public function testAStagingCopySaysSoOnEveryKindOfPage(): void
    {
        $this->setEnv('app.environmentLabel', 'Staging');

        foreach (['/', 'booking', 'login', 'admin'] as $path) {
            $body = $this->get($path)->getBody();

            $this->assertStringContainsString('class="cbs-env-banner"', $body, $path);
            $this->assertStringContainsString('STAGING', $body, $path);
        }

        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $this->get('/')->getBody(), 'Search engines are told to stay away.');
    }

    public function testTheLabelIsShownAsTextNeverRun(): void
    {
        $this->setEnv('app.environmentLabel', '<script>alert(1)</script>');

        $body = $this->get('/')->getBody();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;SCRIPT&gt;', $body);
    }
}
