<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 避免本機環境變數裡的真實金鑰讓測試呼叫到 Gemini。
        config(['services.gemini.key' => null]);
    }
}
