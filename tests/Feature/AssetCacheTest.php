<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AssetCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = public_path('build/assets');
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::delete([$this->directory.'/cache-test-abc123.css', $this->directory.'/cache-test-abc123.js']);

        parent::tearDown();
    }

    public function test_a_build_file_is_served_with_a_one_year_immutable_cache(): void
    {
        File::put($this->directory.'/cache-test-abc123.css', 'body{color:red}');

        $response = $this->get('/assets/cache-test-abc123.css')->assertOk();

        $this->assertStringContainsString('max-age=31536000', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertSame('text/css; charset=utf-8', $response->headers->get('Content-Type'));
    }

    public function test_the_response_has_no_cookie_so_the_edge_can_cache_it(): void
    {
        File::put($this->directory.'/cache-test-abc123.css', 'body{}');

        $response = $this->get('/assets/cache-test-abc123.css')->assertOk();

        $this->assertEmpty($response->headers->getCookies());
        $this->assertNull($response->headers->get('Set-Cookie'));
    }

    public function test_scripts_are_served_as_javascript_so_module_loading_works(): void
    {
        File::put($this->directory.'/cache-test-abc123.js', 'export default 1;');

        $this->get('/assets/cache-test-abc123.js')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/javascript; charset=utf-8');
    }

    public function test_a_repeat_request_with_the_same_etag_gets_a_304(): void
    {
        File::put($this->directory.'/cache-test-abc123.css', 'body{}');

        $etag = $this->get('/assets/cache-test-abc123.css')->headers->get('ETag');

        $this->get('/assets/cache-test-abc123.css', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_missing_files_and_path_tricks_are_a_404(): void
    {
        $this->get('/assets/does-not-exist.css')->assertNotFound();
        $this->get('/assets/..%2F..%2F.env')->assertNotFound();
        $this->get('/assets/%2E%2E%2Fmanifest.json')->assertNotFound();
        $this->get('/assets/sub/dir.css')->assertNotFound();
    }

    public function test_vite_build_paths_are_pointed_at_the_asset_route(): void
    {
        $this->assertSame(url('assets/app-abc.js'), AppServiceProvider::assetUrl('build/assets/app-abc.js'));
        $this->assertSame(url('favicon.ico'), AppServiceProvider::assetUrl('favicon.ico'));
        $this->assertSame(url('build/manifest.json'), AppServiceProvider::assetUrl('build/manifest.json'));
    }
}
