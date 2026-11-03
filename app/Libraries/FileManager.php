<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Generic file storage under writable/, used by any upload type (voice
 * notes today; gift e-card assets, attachments, etc. later) so storage
 * logic lives in one place instead of being copy-pasted per feature.
 *
 * IMPORTANT: writable/ is deliberately not web-accessible (CI4 convention,
 * and correct for us — voice notes include personal content and shouldn't
 * be guessable URLs). To let the browser play/download a file, route a
 * controller action through streamInline() below rather than linking to
 * the file directly.
 */
class FileManager
{
    /**
     * Moves a validated upload into writable/{subdirectory}/ under a
     * randomly generated name, creating the directory if needed. Returns
     * the path relative to writable/, which is what should be stored on
     * the owning DB row — never the absolute filesystem path.
     */
    public function store(UploadedFile $file, string $subdirectory): string
    {
        $targetDir = $this->fullPath($subdirectory);

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $newName = $file->getRandomName();
        $file->move($targetDir, $newName);

        return trim($subdirectory, '/') . '/' . $newName;
    }

    public function delete(string $relativePath): bool
    {
        $full = $this->safeExistingFile($relativePath);

        return $full !== null && unlink($full);
    }

    public function exists(string $relativePath): bool
    {
        return $this->safeExistingFile($relativePath) !== null;
    }

    public function sizeKB(string $relativePath): float
    {
        $full = $this->safeExistingFile($relativePath);

        return $full !== null ? filesize($full) / 1024 : 0.0;
    }

    /**
     * CodeIgniter's own working folders inside writable/. Nothing in them is ever something to serve or delete.
     */
    private const PRIVATE_DIRECTORIES = ['cache', 'database', 'debugbar', 'logs', 'session', 'sessions'];

    /**
     * The real path of an existing file this class may touch, or null.
     *
     * A stored path comes from the database, so it is not trusted: "../" must not climb out of writable/ (nor a
     * symbolic link lead out of it), and the session and log folders there must not be served as a "voice note".
     * Everything a caller asks for goes through here.
     */
    protected function safeExistingFile(string $relativePath): ?string
    {
        $root = realpath(WRITEPATH);
        $real = realpath($this->fullPath($relativePath));

        if ($root === false || $real === false || ! is_file($real)) {
            return null;
        }

        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (! str_starts_with($real, $prefix)) {
            return null;
        }

        $topLevel = explode(DIRECTORY_SEPARATOR, substr($real, strlen($prefix)))[0];

        return in_array($topLevel, self::PRIVATE_DIRECTORIES, true) ? null : $real;
    }

    /**
     * Absolute filesystem path for a path relative to writable/.
     */
    public function fullPath(string $relativePath): string
    {
        return rtrim(WRITEPATH, '/') . '/' . ltrim($relativePath, '/');
    }

    /**
     * Builds a Response that streams a stored file inline (e.g. in-browser
     * audio playback of a voice note in the presenter portal), to be
     * returned directly from a controller action — CI4 then sends it the
     * normal way, rather than this library writing raw output itself.
     *
     * Usage in a controller:
     *   public function playVoiceNote(int $bookingId)
     *   {
     *       $booking = $this->bookingModel->find($bookingId);
     *       return (new FileManager())->streamInline($this->response, $booking['voice_note_path']);
     *   }
     */
    public function streamInline(ResponseInterface $response, string $relativePath): ResponseInterface
    {
        $full = $this->safeExistingFile($relativePath);

        if ($full === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $mime = mime_content_type($full) ?: 'application/octet-stream';

        return $response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'inline; filename="' . basename($full) . '"')
            ->setHeader('Cache-Control', 'private, max-age=0, must-revalidate')
            ->setBody(file_get_contents($full));
    }
}
