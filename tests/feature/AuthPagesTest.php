<?php

namespace Tests\Feature;

use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * The staff account pages: drawn by app/Views/auth in the station's style, and ruled by app/Config/Auth.php.
 *
 * @internal
 */
final class AuthPagesTest extends AppFeatureTestCase
{
    public function testThePackageReadsThisApplicationsAuthSettings(): void
    {
        $config = service('auth')->config();

        $this->assertInstanceOf(\Config\Auth::class, $config, 'Without the alias in Config/AuthConfigAlias.php, app/Config/Auth.php is ignored.');
        $this->assertFalse($config->allowRegistration);
        $this->assertSame('auth/layout', $config->viewLayout);
        $this->assertSame('/admin', $config->redirect('afterLogin'));
    }

    public function testNobodyCanRegisterAnAccountFromThePublicSite(): void
    {
        $this->assertNull(service('routes')->reverseRoute('auth.register') ?: null, 'The sign-up route is not even registered.');

        $this->assertStringNotContainsString('Create one', $this->get('login')->getBody(), 'No sign-up link on the sign-in page.');
    }

    public function testSignInIsTheStationsOwnPage(): void
    {
        $body = $this->get('login')->getBody();

        preg_match('#<title>([^<]*)</title>#', $body, $title);
        $this->assertSame('Sign in · CBS Radio staff', html_entity_decode($title[1] ?? '', ENT_QUOTES | ENT_HTML5));
        $this->assertStringContainsString('assets/css/auth.css', $body);
        $this->assertStringNotContainsString('bootstrap', $body, 'No framework download on the sign-in page.');
        $this->assertStringContainsString('name="login"', $body);
        $this->assertStringContainsString('name="password"', $body);
        $this->assertStringContainsString('autocomplete="current-password"', $body);
        $this->assertStringContainsString('Forgot password?', $body);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $body);
    }

    public function testAFailedSignInSaysSoAndKeepsTheEmail(): void
    {
        $this->post('login', ['login' => 'nobody@example.test', 'password' => 'wrong-password'])->assertRedirect();

        $body = $this->withSession($_SESSION)->get('login')->getBody();

        $this->assertStringContainsString('au-msg--error', $body);
        $this->assertStringContainsString('value="nobody@example.test"', $body);
    }

    public function testThePasswordPagesListThePasswordRules(): void
    {
        $body = $this->get('reset-password')->getBody();

        $this->assertStringContainsString('Reset your password', $body);
        $this->assertStringContainsString('id="password-rules"', $body);
        $this->assertStringContainsString('autocomplete="one-time-code"', $body);
        $this->assertStringContainsString('Forgot your password?', $this->get('forgot-password')->getBody());
    }
}
