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
        $full = $this->fullPath($relativePath);

        if (is_file($full)) {
            return unlink($full);
        }

        return false;
    }

    public function exists(string $relativePath): bool
    {
        return is_file($this->fullPath($relativePath));
    }

    public function sizeKB(string $relativePath): float
    {
        $full = $this->fullPath($relativePath);

        return is_file($full) ? filesize($full) / 1024 : 0.0;
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
        $full = $this->fullPath($relativePath);

        if (! is_file($full)) {
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
