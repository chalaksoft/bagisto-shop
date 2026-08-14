<?php

namespace Modules\Marketplace\Tests\Unit;

use Modules\Marketplace\Services\Package;
use Modules\Marketplace\Services\PackageException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * اعتبارسنجی بسته — همان لایه‌ای که تنها چیزی است بین «zip از اینترنت» و
 * «کد PHP روی سرور»، پس هر حالت رد‌شدن اینجا تست دارد.
 */
class PackageTest extends TestCase
{
    protected string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().'/marketplace-package-test-'.uniqid();

        mkdir($this->workspace, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_valid_package_exposes_name_and_manifest(): void
    {
        $package = Package::open($this->makeZip('Demo', [
            'Demo/module.json'               => json_encode(['name' => 'Demo', 'version' => '1.2.3']),
            'Demo/ModuleServiceProvider.php' => '<?php',
        ]));

        $this->assertSame('Demo', $package->name());
        $this->assertSame('1.2.3', $package->version());
        $this->assertSame(hash_file('sha256', $package->path()), $package->checksum());
    }

    public function test_entry_escaping_the_module_directory_is_rejected(): void
    {
        $archive = $this->makeZip('Demo', [
            'Demo/module.json'               => json_encode(['name' => 'Demo']),
            'Demo/ModuleServiceProvider.php' => '<?php',
            'Demo/../../../.env.pwned'       => 'PWNED=1',
        ]);

        $this->expectException(PackageException::class);
        $this->expectExceptionMessage('بیرون از پوشهٔ خودش');

        Package::open($archive)->name();
    }

    public function test_absolute_path_entry_is_rejected(): void
    {
        $archive = $this->makeZip('Demo', [
            'Demo/module.json' => json_encode(['name' => 'Demo']),
            '/etc/passwd'      => 'root',
        ]);

        $this->expectException(PackageException::class);

        Package::open($archive)->name();
    }

    /**
     * فایندر مک کنار هر فایل یک AppleDouble می‌گذارد و پوشهٔ `__MACOSX/` را در
     * ریشه اضافه می‌کند. بدون این تست، هر بسته‌ای که با «Compress» فایندر ساخته
     * شود با «بیش از یک پوشه در ریشه» رد می‌شود.
     */
    public function test_mac_metadata_does_not_count_as_a_second_root(): void
    {
        $package = Package::open($this->makeZip('Demo', [
            'Demo/module.json'               => json_encode(['name' => 'Demo', 'version' => '1.0.0']),
            'Demo/ModuleServiceProvider.php' => '<?php',
            '__MACOSX/._Demo'                => 'apple double',
            '__MACOSX/Demo/._module.json'    => 'apple double',
            'Demo/.DS_Store'                 => 'finder junk',
            'Demo/._helper.php'              => 'apple double',
        ]));

        $this->assertSame('Demo', $package->name());
    }

    public function test_mac_metadata_is_not_written_to_disk(): void
    {
        $package = Package::open($this->makeZip('Demo', [
            'Demo/module.json'               => json_encode(['name' => 'Demo', 'version' => '1.0.0']),
            'Demo/ModuleServiceProvider.php' => '<?php',
            'Demo/helper.php'                => '<?php',
            '__MACOSX/Demo/._module.json'    => 'apple double',
            'Demo/._helper.php'              => 'apple double',
            'Demo/.DS_Store'                 => 'finder junk',
        ]));

        $path = $package->extractTo($this->workspace.'/out');

        $this->assertFileExists($path.'/helper.php');
        $this->assertDirectoryDoesNotExist($this->workspace.'/out/__MACOSX');
        $this->assertFileDoesNotExist($path.'/._helper.php');
        $this->assertFileDoesNotExist($path.'/.DS_Store');
    }

    public function test_more_than_one_root_directory_is_rejected(): void
    {
        $archive = $this->makeZip('Demo', [
            'Demo/module.json'  => json_encode(['name' => 'Demo']),
            'Other/module.json' => json_encode(['name' => 'Other']),
        ]);

        $this->expectException(PackageException::class);
        $this->expectExceptionMessage('بیش از یک پوشه در ریشه');

        Package::open($archive)->name();
    }

    public function test_manifest_name_must_match_the_root_directory(): void
    {
        $archive = $this->makeZip('Demo', [
            'Demo/module.json'               => json_encode(['name' => 'SomethingElse']),
            'Demo/ModuleServiceProvider.php' => '<?php',
        ]);

        $this->expectException(PackageException::class);
        $this->expectExceptionMessage('یکی نیست');

        Package::open($archive)->manifest();
    }

    public function test_package_without_provider_file_is_rejected_on_extract(): void
    {
        $archive = $this->makeZip('Demo', [
            'Demo/module.json' => json_encode(['name' => 'Demo']),
        ]);

        $this->expectException(PackageException::class);
        $this->expectExceptionMessage('ModuleServiceProvider.php');

        Package::open($archive)->extractTo($this->workspace.'/out');
    }

    public function test_signature_is_verified_against_the_public_key(): void
    {
        $keys = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        openssl_pkey_export($keys, $privateKey);

        $publicKeyPath = $this->workspace.'/repository.pub';

        file_put_contents($publicKeyPath, openssl_pkey_get_details($keys)['key']);

        $package = Package::open($this->makeZip('Demo', [
            'Demo/module.json'               => json_encode(['name' => 'Demo']),
            'Demo/ModuleServiceProvider.php' => '<?php',
        ]));

        openssl_sign(file_get_contents($package->path()), $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $package->verifySignature(base64_encode($signature), $publicKeyPath);

        $this->addToAssertionCount(1);

        $this->expectException(PackageException::class);
        $this->expectExceptionMessage('امضای بسته معتبر نیست');

        $package->verifySignature(base64_encode('tampered'), $publicKeyPath);
    }

    public function test_missing_signature_is_rejected(): void
    {
        $package = Package::open($this->makeZip('Demo', [
            'Demo/module.json' => json_encode(['name' => 'Demo']),
        ]));

        $this->expectException(PackageException::class);
        $this->expectExceptionMessage('بسته امضا ندارد');

        $package->verifySignature(null, $this->workspace.'/whatever.pub');
    }

    public function test_checksum_mismatch_is_rejected(): void
    {
        $package = Package::open($this->makeZip('Demo', [
            'Demo/module.json' => json_encode(['name' => 'Demo']),
        ]));

        $package->verifyChecksum($package->checksum());

        $this->addToAssertionCount(1);

        $this->expectException(PackageException::class);

        $package->verifyChecksum(str_repeat('a', 64));
    }

    /* ---------------------------------------------------------------------
     | کمکی‌ها
     * -------------------------------------------------------------------*/

    /**
     * @param  array<string, string>  $entries
     */
    protected function makeZip(string $name, array $entries): string
    {
        $path = $this->workspace.'/'.$name.'-'.uniqid().'.zip';

        $zip = new ZipArchive;

        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }

        $zip->close();

        return $path;
    }

    protected function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            is_dir($path.'/'.$item)
                ? $this->deleteDirectory($path.'/'.$item)
                : @unlink($path.'/'.$item);
        }

        @rmdir($path);
    }
}
