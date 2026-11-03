<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Concerns\SetsEnvironment;
use Tests\Support\TestCase\UnitTestCase;

/**
 * ui_version / ui_view_path / ui_layout / ui_view: a themed folder overrides only the views it has.
 *
 * The fixture is a throwaway version folder under app/Views, created for this class and removed after it, so the
 * tests check real files on disk rather than a mocked lookup.
 *
 * @internal
 */
final class UiVersionTest extends UnitTestCase
{
    use SetsEnvironment;

    private const VERSION = 'zz_ui_version_test';

    private static string $root;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        helper('ui');

        self::$root = APPPATH . 'Views/' . self::VERSION;

        self::write('layouts/admin.php', 'themed admin layout');
        self::write('admin/themed_screen.php', 'themed screen for <?= esc($who) ?>');
    }

    public static function tearDownAfterClass(): void
    {
        foreach (['admin/themed_screen.php', 'layouts/admin.php'] as $file) {
            @unlink(self::$root . '/' . $file);
        }

        foreach (['admin', 'layouts', ''] as $dir) {
            @rmdir(rtrim(self::$root . '/' . $dir, '/'));
        }

        parent::tearDownAfterClass();
    }

    public function testWithNoVersionEveryViewIsTheBaseView(): void
    {
        $this->setEnv('ui_version', null);

        $this->assertSame('', ui_version());
        $this->assertSame('admin/themed_screen', ui_view_path('admin/themed_screen'));
        $this->assertSame('layouts/admin', ui_layout('admin'));
    }

    public function testAViewTheVersionHasIsTakenFromTheVersionFolder(): void
    {
        $this->setEnv('ui_version', self::VERSION);

        $this->assertSame(self::VERSION . '/admin/themed_screen', ui_view_path('admin/themed_screen'));
        $this->assertSame(self::VERSION . '/layouts/admin', ui_layout('admin'));
    }

    public function testAViewTheVersionLacksFallsBackToTheBaseView(): void
    {
        $this->setEnv('ui_version', self::VERSION);

        $this->assertSame('admin/not_themed', ui_view_path('admin/not_themed'));
        $this->assertSame('layouts/public', ui_layout('public'), 'A theme without a public layout keeps the base one.');
    }

    public function testAVersionNamingAFolderThatDoesNotExistGivesTheBaseViews(): void
    {
        $this->setEnv('ui_version', 'no_such_version_folder');

        $this->assertSame('admin/themed_screen', ui_view_path('admin/themed_screen'));
    }

    public function testALeadingSlashIsIgnored(): void
    {
        $this->setEnv('ui_version', self::VERSION);

        $this->assertSame(self::VERSION . '/admin/themed_screen', ui_view_path('/admin/themed_screen'));
    }

    #[DataProvider('unsafeVersions')]
    public function testAVersionThatIsNotAPlainFolderNameIsIgnored(string $version): void
    {
        $this->setEnv('ui_version', $version);

        $this->assertSame('', ui_version(), 'The version ends up in a file path, so only a plain folder name counts.');
        $this->assertSame('admin/themed_screen', ui_view_path('admin/themed_screen'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafeVersions(): array
    {
        return [
            'climbing out'  => ['../' . self::VERSION],
            'a nested path' => [self::VERSION . '/admin'],
            'a dot'         => ['.'],
            'spaces inside' => ['v 1'],
        ];
    }

    public function testUiViewRendersTheResolvedView(): void
    {
        $this->setEnv('ui_version', self::VERSION);

        // assertStringContainsString: in development the toolbar wraps every view in DEBUG-VIEW comments.
        $this->assertStringContainsString(
            'themed screen for staff &amp; co',
            ui_view('admin/themed_screen', ['who' => 'staff & co'], ['saveData' => false])
        );
    }

    private static function write(string $relative, string $contents): void
    {
        $path = self::$root . '/' . $relative;

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }
}
