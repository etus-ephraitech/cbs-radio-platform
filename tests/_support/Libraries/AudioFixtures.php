<?php

namespace Tests\Support\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

/**
 * The real audio files in tests/_support/Audio (made by scripts/make-test-audio.sh), handed to the code under test
 * the way a browser would send them.
 *
 * A test never touches a fixture itself: every call copies it to a temporary file first, because a successful
 * upload is *moved* into writable/ and would otherwise consume the original.
 */
final class AudioFixtures
{
    /** @var string[] temporary copies not yet moved or deleted */
    private static array $temporary = [];

    public static function path(string $name): string
    {
        $path = SUPPORTPATH . 'Audio/' . $name;

        if (! is_file($path)) {
            throw new RuntimeException("No audio fixture called {$name}. Run scripts/make-test-audio.sh.");
        }

        return $path;
    }

    /**
     * A temporary copy of a fixture.
     */
    public static function copy(string $name): string
    {
        $copy = tempnam(sys_get_temp_dir(), 'cbsaudio');
        copy(self::path($name), $copy);

        return self::$temporary[] = $copy;
    }

    /**
     * An uploaded file backed by a fixture. $clientName and $clientType are what the visitor's browser *claims*,
     * and may differ from the real content, which is the point of several tests (a text file called x.mp3).
     */
    public static function upload(string $name, ?string $clientName = null, ?string $clientType = null): UploadedFile
    {
        $copy = self::copy($name);

        return new UploadedFile($copy, $clientName ?? $name, $clientType, filesize($copy), UPLOAD_ERR_OK);
    }

    /**
     * An upload that PHP itself rejected, such as a file over upload_max_filesize.
     */
    public static function failedUpload(int $error): UploadedFile
    {
        return new UploadedFile('', 'voice-note.webm', 'audio/webm', 0, $error);
    }

    /**
     * Puts a fixture into $_FILES, so a request made in the same test carries it as a multipart upload.
     */
    public static function attachToRequest(string $field, string $name, ?string $clientName = null, ?string $clientType = null): void
    {
        $copy = self::copy($name);

        $files = service('superglobals')->getFilesArray();

        $files[$field] = [
            'name'     => $clientName ?? $name,
            'type'     => $clientType ?? '',
            'tmp_name' => $copy,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($copy),
        ];

        // CodeIgniter reads uploads through its superglobals service, not the raw $_FILES.
        service('superglobals')->setFilesArray($files);
        $_FILES = $files;
    }

    /**
     * Puts something that is not a normal upload into the request: PHP itself having refused the file, say.
     */
    public static function attachRawToRequest(string $field, array $entry): void
    {
        $files          = service('superglobals')->getFilesArray();
        $files[$field]  = $entry;

        service('superglobals')->setFilesArray($files);
        $_FILES = $files;
    }

    /**
     * Removes what a test left behind and empties $_FILES.
     */
    public static function cleanUp(): void
    {
        foreach (self::$temporary as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        self::$temporary = [];
        $_FILES          = [];

        service('superglobals')->setFilesArray([]);
    }
}
