<?php

namespace Tests\Unit\Libraries;

use App\Libraries\FileManager;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Response;
use Config\App;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Libraries\AudioFixtures;
use Tests\Support\TestCase\UnitTestCase;

/**
 * The one place that reads, serves and deletes files under writable/. Stored paths come from the database, so they
 * are treated as untrusted: nothing may climb out of writable/, and CodeIgniter's own folders there are off limits.
 *
 * @internal
 */
final class FileManagerTest extends UnitTestCase
{
    private const AREA = 'file-manager-test';

    private FileManager $files;

    /** @var string[] files this test wrote outside its own folder, removed afterwards */
    private array $strays = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new FileManager();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(WRITEPATH . self::AREA);

        foreach ($this->strays as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        AudioFixtures::cleanUp();

        parent::tearDown();
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                is_dir("{$path}/{$entry}") ? $this->removeDirectory("{$path}/{$entry}") : unlink("{$path}/{$entry}");
            }
        }

        rmdir($path);
    }

    /**
     * A file placed directly under writable/, returned as the relative path the database would hold.
     */
    private function place(string $relative, string $contents = 'hello'): string
    {
        $full = WRITEPATH . $relative;

        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0755, true);
        }

        file_put_contents($full, $contents);
        $this->strays[] = $full;

        return $relative;
    }

    // ------------------------------------------------------------------ storing

    public function testAnUploadIsMovedIntoTheGivenFolderUnderAGeneratedName(): void
    {
        $path = $this->files->store(AudioFixtures::upload('chrome-recording.webm', 'voice-note.webm'), self::AREA);

        $this->assertMatchesRegularExpression('#^' . self::AREA . '/\d+_[a-f0-9]+\.webm$#', $path);
        $this->assertFileExists(WRITEPATH . $path);
    }

    public function testTheFolderIsCreatedIfItDoesNotExistYet(): void
    {
        $this->assertDirectoryDoesNotExist(WRITEPATH . self::AREA . '/nested');

        $path = $this->files->store(AudioFixtures::upload('recording.mp3', 'a.mp3'), self::AREA . '/nested');

        $this->assertFileExists(WRITEPATH . $path);
    }

    public function testTwoUploadsNeverShareAName(): void
    {
        $a = $this->files->store(AudioFixtures::upload('recording.mp3', 'same.mp3'), self::AREA);
        $b = $this->files->store(AudioFixtures::upload('recording.mp3', 'same.mp3'), self::AREA);

        $this->assertNotSame($a, $b);
    }

    public function testSlashesAroundTheFolderNameAreTidied(): void
    {
        $path = $this->files->store(AudioFixtures::upload('recording.mp3', 'a.mp3'), '/' . self::AREA . '/');

        $this->assertStringStartsWith(self::AREA . '/', $path);
    }

    // ------------------------------------------------------------------ asking about a file

    public function testAStoredFileIsReportedAsPresentWithItsSize(): void
    {
        $path = $this->place(self::AREA . '/a.txt', str_repeat('x', 2048));

        $this->assertTrue($this->files->exists($path));
        $this->assertSame(2.0, $this->files->sizeKB($path));
    }

    public function testAMissingFileIsAbsentAndHasNoSize(): void
    {
        $this->assertFalse($this->files->exists(self::AREA . '/never-there.txt'));
        $this->assertSame(0.0, $this->files->sizeKB(self::AREA . '/never-there.txt'));
    }

    public function testADirectoryIsNotAFile(): void
    {
        $this->place(self::AREA . '/folder/a.txt');

        $this->assertFalse($this->files->exists(self::AREA . '/folder'));
    }

    public function testThePathIsRelativeToWritableWhateverTheLeadingSlashes(): void
    {
        $this->assertSame(rtrim(WRITEPATH, '/') . '/voice-notes/x.webm', $this->files->fullPath('voice-notes/x.webm'));
        $this->assertSame($this->files->fullPath('voice-notes/x.webm'), $this->files->fullPath('///voice-notes/x.webm'), 'A leading slash is not an escape to the filesystem root.');
    }

    // ------------------------------------------------------------------ deleting

    public function testAFileCanBeDeletedOnce(): void
    {
        $path = $this->place(self::AREA . '/a.txt');

        $this->assertTrue($this->files->delete($path));
        $this->assertFileDoesNotExist(WRITEPATH . $path);
        $this->assertFalse($this->files->delete($path), 'Deleting something already gone is a quiet false.');
    }

    // ------------------------------------------------------------------ serving

    public function testAFileIsServedInlineWithItsRealTypeAndContents(): void
    {
        $path = $this->files->store(AudioFixtures::upload('chrome-recording.webm', 'voice-note.webm'), self::AREA);

        $response = $this->files->streamInline(new Response(config(App::class)), $path);

        $this->assertSame('video/webm', $response->getHeaderLine('Content-Type'));
        $this->assertSame('inline; filename="' . basename($path) . '"', $response->getHeaderLine('Content-Disposition'));
        // The framework adds its own default Cache-Control to every response and ours is merged with it; what
        // matters is that a private recording is never marked as publicly cacheable.
        $this->assertStringContainsString('private', $response->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', $response->getHeaderLine('Cache-Control'));
        $this->assertStringNotContainsString('public', $response->getHeaderLine('Cache-Control'));
        $this->assertSame(file_get_contents(AudioFixtures::path('chrome-recording.webm')), $response->getBody());
    }

    public function testAMissingFileIsNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->files->streamInline(new Response(config(App::class)), self::AREA . '/never-there.webm');
    }

    // ------------------------------------------------------------------ untrusted paths

    /**
     * @return array<string, array{0: string}>
     */
    public static function pathsThatEscape(): array
    {
        return [
            'one level up'                  => ['../.env'],
            'up and into the app'           => [self::AREA . '/../../app/Config/App.php'],
            'up and out through a folder'   => [self::AREA . '/../../../etc/passwd'],
            'deep traversal'                => ['../../../../../../../../etc/passwd'],
            'an absolute path'              => ['/etc/passwd'],
        ];
    }

    #[DataProvider('pathsThatEscape')]
    public function testAPathThatClimbsOutOfWritableIsRefusedEverywhere(string $path): void
    {
        $this->place(self::AREA . '/decoy.txt');   // makes sure the folder exists, so ".." has something to climb from

        $this->assertFalse($this->files->exists($path));
        $this->assertSame(0.0, $this->files->sizeKB($path));
        $this->assertFalse($this->files->delete($path), 'Nothing outside writable/ may be deleted.');

        $this->expectException(PageNotFoundException::class);
        $this->files->streamInline(new Response(config(App::class)), $path);
    }

    public function testAFileOutsideWritableSurvivesAnAttemptToDeleteIt(): void
    {
        $outside = sys_get_temp_dir() . '/cbs-outside-' . bin2hex(random_bytes(4)) . '.txt';
        file_put_contents($outside, 'precious');
        $this->strays[] = $outside;

        $relative = str_repeat('../', 12) . ltrim($outside, '/');

        $this->assertFalse($this->files->delete($relative));
        $this->assertFileExists($outside);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function codeIgnitersOwnFolders(): array
    {
        return [
            'sessions' => ['session'],
            'logs'     => ['logs'],
            'cache'    => ['cache'],
            'debugbar' => ['debugbar'],
        ];
    }

    #[DataProvider('codeIgnitersOwnFolders')]
    public function testCodeIgnitersPrivateFoldersAreNeverServedOrDeleted(string $folder): void
    {
        $path = $this->place($folder . '/cbs-test-secret.txt', 'a session id or a stack trace');

        $this->assertFalse($this->files->exists($path));
        $this->assertFalse($this->files->delete($path));
        $this->assertFileExists(WRITEPATH . $path, 'The refused delete must really have left it alone.');

        $this->expectException(PageNotFoundException::class);
        $this->files->streamInline(new Response(config(App::class)), $path);
    }

    public function testAClimbThatLandsInAPrivateFolderIsRefusedToo(): void
    {
        $this->place('logs/cbs-test-secret.txt');
        $this->place(self::AREA . '/decoy.txt');

        $this->assertFalse($this->files->exists(self::AREA . '/../logs/cbs-test-secret.txt'));
    }
}
