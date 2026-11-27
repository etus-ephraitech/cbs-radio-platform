<?php

namespace Tests\Database\Services;

use App\Libraries\Settings\SettingsManager;
use App\Models\SettingModel;
use App\Services\BrandingService;
use CodeIgniter\HTTP\Files\UploadedFile;
use Tests\Support\TestCase\AppTestCase;

/**
 * Uploading, replacing and removing the logo and tab icon: what is accepted, where it lands, and that a replaced
 * or removed file does not linger on disk.
 *
 * @internal
 */
final class BrandingServiceTest extends AppTestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private string $root;
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/cbs-branding-' . bin2hex(random_bytes(4));
        $this->tmp  = $this->root . '-in';
        mkdir($this->tmp, 0755, true);
        SettingsManager::flush();
    }

    protected function tearDown(): void
    {
        foreach ([$this->root . '/' . BrandingService::DIR, $this->root, $this->tmp] as $dir) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                is_file($f) && unlink($f);
            }

            is_dir($dir) && @rmdir($dir);
        }

        SettingsManager::flush();
        parent::tearDown();
    }

    private function service(): BrandingService
    {
        return new BrandingService(root: $this->root);
    }

    private function upload(string $name, string $bytes): UploadedFile
    {
        $path = $this->tmp . '/' . $name;
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, filesize($path), UPLOAD_ERR_OK);
    }

    private function stored(string $key): ?string
    {
        return (new SettingModel())->allKeyed()[$key] ?? null;
    }

    public function testAPngLogoIsStoredAndItsPathSaved(): void
    {
        $result = $this->service()->upload('logo', $this->upload('mine.png', base64_decode(self::PNG)), 1);

        $this->assertTrue($result['success'], (string) $result['error']);

        $path = $this->stored('branding.logoPath');
        $this->assertMatchesRegularExpression('#^uploads/branding/logo-[0-9a-f]{12}\.png$#', $path);
        $this->assertFileExists($this->root . '/' . $path);
    }

    public function testReplacingTheLogoDeletesTheOldFile(): void
    {
        $this->service()->upload('logo', $this->upload('a.png', base64_decode(self::PNG)), 1);
        $first = $this->stored('branding.logoPath');

        $this->service()->upload('logo', $this->upload('b.png', base64_decode(self::PNG)), 1);
        $second = $this->stored('branding.logoPath');

        $this->assertNotSame($first, $second);
        $this->assertFileDoesNotExist($this->root . '/' . $first);
        $this->assertFileExists($this->root . '/' . $second);
    }

    public function testRemovingTheLogoClearsTheSettingAndTheFile(): void
    {
        $this->service()->upload('logo', $this->upload('a.png', base64_decode(self::PNG)), 1);
        $path = $this->stored('branding.logoPath');

        $this->assertTrue($this->service()->remove('logo', 1));

        $this->assertNull($this->stored('branding.logoPath'));
        $this->assertFileDoesNotExist($this->root . '/' . $path);
    }

    public function testAnSvgIsRefusedBecauseItCanCarryScript(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $result = $this->service()->upload('logo', $this->upload('x.svg', $svg), 1);

        $this->assertFalse($result['success']);
        $this->assertNull($this->stored('branding.logoPath'));
    }

    public function testTheTypeComesFromTheContentsNotTheFileName(): void
    {
        $result = $this->service()->upload('logo', $this->upload('innocent.png', '<?php echo 1;'), 1);

        $this->assertFalse($result['success']);
        $this->assertNull($this->stored('branding.logoPath'));
    }

    public function testAnOversizedLogoIsRefused(): void
    {
        $big = base64_decode(self::PNG) . str_repeat('0', BrandingService::LOGO_MAX_KB * 1024);

        $result = $this->service()->upload('logo', $this->upload('big.png', $big), 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('larger than', (string) $result['error']);
    }

    public function testAnUnknownKindIsRefused(): void
    {
        $result = $this->service()->upload('banner', $this->upload('a.png', base64_decode(self::PNG)), 1);

        $this->assertFalse($result['success']);
        $this->assertFalse($this->service()->remove('banner', 1));
    }

    public function testTheLogoDescriptionIsCleanedAndLimited(): void
    {
        $this->assertNull($this->service()->saveAlt('  <b>CBS</b>   Radio  Uganda ', 1));
        $this->assertSame('CBS Radio Uganda', $this->stored('branding.logoAlt'));

        $this->assertNotNull($this->service()->saveAlt(str_repeat('a', 81), 1));
    }

    public function testUrlOnlyServesFilesInTheBrandingFolder(): void
    {
        $this->assertSame('', BrandingService::url(''));
        $this->assertSame('', BrandingService::url('uploads/branding/../../.env'));
        $this->assertSame('', BrandingService::url('index.php'));
        $this->assertSame('', BrandingService::url('uploads/branding/missing.png'));
    }

    public function testTheSavedPathReachesTheConfig(): void
    {
        $this->service()->upload('logo', $this->upload('a.png', base64_decode(self::PNG)), 1);
        SettingsManager::flush();

        $this->assertSame($this->stored('branding.logoPath'), (new \Config\CbsRadio())->logoPath);
    }
}
