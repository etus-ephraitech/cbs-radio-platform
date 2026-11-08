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
        $this->assertSame('/admin', $config->redirect('afterLogin'));
    }

    public function testNobodyCanRegisterAnAccountFromThePublicSite(): void
    {
        $this->assertNull(service('routes')->reverseRoute('auth.register') ?: null, 'The sign-up route is not even registered.');

    }
}
