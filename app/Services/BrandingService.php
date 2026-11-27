<?php

namespace App\Services;

use App\Libraries\Settings\SettingsManager;
use App\Models\SettingChangeModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Stores the station's logo and browser-tab icon. The files live in public/uploads/branding/ (served directly, so
 * every page can use them without a controller) and their paths are kept in the settings table like any other
 * admin-chosen value, so config('CbsRadio')->logoPath is all a view needs.
 *
 * Only raster images are accepted. SVG is refused on purpose: an SVG opened on its own can carry script, and the
 * uploaded file is publicly reachable. The type is taken from the file's contents, never from its name or the
 * browser's claim, and the stored name is generated, so an upload cannot choose where it lands or what it is called.
 */
class BrandingService
{
    public const DIR = 'uploads/branding';

    public const LOGO_MAX_KB    = 1024;
    public const FAVICON_MAX_KB = 256;

    private const LOGO_TYPES    = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    private const FAVICON_TYPES = ['image/png' => 'png', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico'];

    public const KINDS = [
        'logo'    => ['key' => 'branding.logoPath', 'label' => 'Logo'],
        'favicon' => ['key' => 'branding.faviconPath', 'label' => 'Browser tab icon'],
    ];

    private string $root;

    public function __construct(
        private ?SettingModel $settings = null,
        private ?SettingChangeModel $changes = null,
        ?string $root = null
    ) {
        $this->settings ??= new SettingModel();
        $this->changes  ??= new SettingChangeModel();
        $this->root = rtrim($root ?? FCPATH, '/\\');
    }

    /**
     * Saves an uploaded file as the logo or the tab icon, replacing the previous one.
     *
     * @return array{success: bool, error: ?string}
     */
    public function upload(string $kind, ?UploadedFile $file, ?int $userId): array
    {
        if (! isset(self::KINDS[$kind])) {
            return $this->fail('Unknown image.');
        }

        if ($file === null || ! $file->isValid() || $file->hasMoved()) {
            return $this->fail($file !== null && $file->getError() === UPLOAD_ERR_NO_FILE
                ? 'Choose an image to upload.'
                : 'The upload did not complete. Try again, with a smaller file if it is large.');
        }

        $maxKb = $kind === 'logo' ? self::LOGO_MAX_KB : self::FAVICON_MAX_KB;
        $types = $kind === 'logo' ? self::LOGO_TYPES : self::FAVICON_TYPES;

        if ($file->getSize() > $maxKb * 1024) {
            return $this->fail('That file is larger than ' . $maxKb . ' KB.');
        }

        $path = $file->getRealPath() ?: $file->getTempName();
        $mime = $this->detectMime($path);
        $ext  = $types[$mime] ?? null;

        if ($ext === null) {
            return $this->fail($kind === 'logo'
                ? 'The logo must be a PNG, JPG or WebP image.'
                : 'The tab icon must be a PNG or ICO file.');
        }

        if ($ext !== 'ico') {
            $size = @getimagesize($path);

            if ($size === false || $size[0] < 1 || $size[1] < 1) {
                return $this->fail('That file is not a readable image.');
            }

            if ($size[0] > 4000 || $size[1] > 4000) {
                return $this->fail('That image is larger than 4000 pixels on a side.');
            }
        }

        $dir = $this->root . '/' . self::DIR;

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return $this->fail('The upload folder could not be created. Check that public/uploads is writable.');
        }

        $name = $kind . '-' . bin2hex(random_bytes(6)) . '.' . $ext;

        if (! @move_uploaded_file($path, $dir . '/' . $name) && ! @copy($path, $dir . '/' . $name)) {
            return $this->fail('The file could not be saved. Check that public/uploads/branding is writable.');
        }

        $this->remember(self::KINDS[$kind]['key'], self::DIR . '/' . $name, $userId);

        return ['success' => true, 'error' => null];
    }

    /**
     * Goes back to the built-in brand for this image and deletes the uploaded file.
     */
    public function remove(string $kind, ?int $userId): bool
    {
        if (! isset(self::KINDS[$kind])) {
            return false;
        }

        $this->remember(self::KINDS[$kind]['key'], null, $userId);

        return true;
    }

    /**
     * Saves the text read out for the logo (its alt text). Empty goes back to "CBS Radio".
     */
    public function saveAlt(string $alt, ?int $userId): ?string
    {
        $alt = trim(preg_replace('/\s+/', ' ', strip_tags($alt)) ?? '');

        if (mb_strlen($alt) > 80) {
            return 'The logo description can be at most 80 characters.';
        }

        $this->remember('branding.logoAlt', $alt === '' ? null : $alt, $userId);

        return null;
    }

    /**
     * The public URL of an uploaded image, or '' when there is none, or when the file has gone missing from disk
     * (so a deleted file shows the built-in brand instead of a broken image).
     */
    public static function url(string $path): string
    {
        if ($path === '' || ! str_starts_with($path, self::DIR . '/') || str_contains($path, '..')) {
            return '';
        }

        return is_file(FCPATH . $path) ? base_url($path) : '';
    }

    /**
     * Writes (or, for null, clears) one branding value, records the change, and deletes a replaced file.
     */
    private function remember(string $key, ?string $value, ?int $userId): void
    {
        $before = (string) ($this->settings->allKeyed()[$key] ?? '');

        if ($value === null) {
            $this->settings->clearOverride($key);
        } else {
            $this->settings->setOverride($key, $value, $userId);
        }

        $this->changes->record($key, $value === null ? 'reset' : 'set', $before, (string) $value, $userId);

        if ($key !== 'branding.logoAlt' && $before !== '' && $before !== (string) $value) {
            $this->deleteFile($before);
        }

        SettingsManager::flush();
    }

    private function deleteFile(string $path): void
    {
        if (str_starts_with($path, self::DIR . '/') && ! str_contains($path, '..')) {
            @unlink($this->root . '/' . $path);
        }
    }

    private function detectMime(string $path): string
    {
        if (! function_exists('finfo_open')) {
            return '';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? (string) finfo_file($finfo, $path) : '';

        return $mime;
    }

    /**
     * @return array{success: bool, error: string}
     */
    private function fail(string $message): array
    {
        return ['success' => false, 'error' => $message];
    }
}
