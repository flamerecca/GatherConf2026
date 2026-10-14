<?php

namespace Tests\Feature\Friends;

use App\View\Components\QrCode;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class QrCodeComponentTest extends TestCase
{
    public function test_it_renders_an_inline_svg_data_uri_with_the_label(): void
    {
        $html = Blade::render('<x-qr-code value="https://example.test/friends/add/abc" label="我的 QR Code" />');

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('alt="我的 QR Code"', $html);
        $this->assertStringContainsString('src="data:image/svg+xml;base64,', $html);
    }

    public function test_the_payload_is_a_valid_svg_and_makes_no_external_request(): void
    {
        $uri = (new QrCode('https://example.test/friends/add/abc'))->dataUri();

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);

        $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString('http://', str_replace('http://www.w3.org/2000/svg', '', $svg));
    }

    public function test_different_values_produce_different_codes(): void
    {
        $this->assertNotSame(
            (new QrCode('https://example.test/a'))->dataUri(),
            (new QrCode('https://example.test/b'))->dataUri(),
        );
    }
}
