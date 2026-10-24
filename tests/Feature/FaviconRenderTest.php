<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaviconRenderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::firstOrCreate(
            ['slug' => 'produtora-alpha'],
            [
                'name' => 'Produtora Alpha',
                'status' => 'active',
                'primary_contact_email' => 'contato@alpha.com',
                'subscription_plan' => 'enterprise',
            ]
        );
    }

    public function test_favicon_files_exist_in_public_directory(): void
    {
        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertGreaterThan(0, filesize(public_path('favicon.ico')));

        $this->assertFileExists(public_path('favicon-16x16.png'));
        $this->assertGreaterThan(0, filesize(public_path('favicon-16x16.png')));

        $this->assertFileExists(public_path('favicon-32x32.png'));
        $this->assertGreaterThan(0, filesize(public_path('favicon-32x32.png')));

        $this->assertFileExists(public_path('favicon-48x48.png'));
        $this->assertGreaterThan(0, filesize(public_path('favicon-48x48.png')));

        $this->assertFileExists(public_path('apple-touch-icon.png'));
        $this->assertGreaterThan(0, filesize(public_path('apple-touch-icon.png')));

        $this->assertFileExists(public_path('site.webmanifest'));
    }

    public function test_marketplace_home_renders_favicon_links(): void
    {
        $response = $this->get(route('marketplace.index'));

        $response->assertOk();
        $response->assertSee('<link rel="icon" type="image/x-icon"', false);
        $response->assertSee('<link rel="icon" type="image/png" sizes="32x32"', false);
        $response->assertSee('<link rel="apple-touch-icon"', false);
    }

    public function test_producer_login_renders_favicon_links(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('<link rel="icon" type="image/x-icon"', false);
        $response->assertSee('<link rel="icon" type="image/png" sizes="16x16"', false);
    }

    public function test_portal_login_renders_favicon_links(): void
    {
        $response = $this->get(route('portal.login'));

        $response->assertOk();
        $response->assertSee('<link rel="icon" type="image/x-icon"', false);
        $response->assertSee('<link rel="icon" type="image/png" sizes="32x32"', false);
    }

    public function test_storefront_vitrine_renders_favicon_links(): void
    {
        $response = $this->get(route('storefront.show', $this->tenant));

        $response->assertOk();
        $response->assertSee('<link rel="icon" type="image/x-icon"', false);
        $response->assertSee('<link rel="apple-touch-icon"', false);
    }
}
