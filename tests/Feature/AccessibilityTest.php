<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function publicPages(): array
    {
        return [
            'landing zh' => ['/', '跳到主要內容'],
            'landing en' => ['/en', 'Skip to main content'],
            'conduct zh' => ['/code-of-conduct', '跳到主要內容'],
            'communities en' => ['/en/communities', 'Skip to main content'],
            'call for sponsor zh' => ['/call-for-sponsor', '跳到主要內容'],
            'member login zh' => ['/login', '跳到主要內容'],
            'member login en' => ['/en/login', 'Skip to main content'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_every_page_has_a_skip_link_and_exactly_one_main_landmark(string $path, string $skipLabel): void
    {
        $response = $this->get($path)->assertOk();
        $html = $response->getContent();

        $response->assertSee('href="#main"', false)->assertSee($skipLabel);
        $this->assertSame(1, substr_count($html, '<main'));
        $this->assertSame(1, substr_count($html, 'id="main"'));
        $this->assertLessThan(strpos($html, '<main'), strpos($html, 'href="#main"'));
    }

    #[DataProvider('publicPages')]
    public function test_pages_declare_the_language_and_one_heading_level_one(string $path, string $skipLabel): void
    {
        $html = $this->get($path)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertMatchesRegularExpression('/<html lang="(zh-Hant-TW|en)"/', $html);
    }

    public function test_form_fields_keep_a_visible_focus_style_and_border(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('outline-none', $html);
        $this->assertStringContainsString('border-ink/60', $html);
    }

    public function test_the_stylesheet_has_focus_and_reduced_motion_rules(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
    }
}
