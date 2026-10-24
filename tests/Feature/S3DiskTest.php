<?php

namespace Tests\Feature;

use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class S3DiskTest extends TestCase
{
    public function test_the_s3_disk_is_built_with_the_flysystem_adapter(): void
    {
        config([
            'filesystems.disks.s3.key' => 'test-key',
            'filesystems.disks.s3.secret' => 'test-secret',
            'filesystems.disks.s3.region' => 'ap-northeast-1',
            'filesystems.disks.s3.bucket' => 'gatherconf',
        ]);

        $disk = Storage::disk('s3');

        $this->assertInstanceOf(AwsS3V3Adapter::class, $disk);
        $this->assertSame('https://gatherconf.s3.ap-northeast-1.amazonaws.com/unique-bunnies/a.webp', $disk->url('unique-bunnies/a.webp'));
    }
}
