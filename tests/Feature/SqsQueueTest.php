<?php

namespace Tests\Feature;

use Aws\Sqs\SqsClient;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SqsQueueTest extends TestCase
{
    public function test_the_sqs_connection_is_built_with_the_aws_sdk(): void
    {
        config([
            'queue.connections.sqs.key' => 'test-key',
            'queue.connections.sqs.secret' => 'test-secret',
            'queue.connections.sqs.prefix' => 'https://sqs.ap-northeast-1.amazonaws.com/123456789012',
            'queue.connections.sqs.queue' => 'gatherconf',
            'queue.connections.sqs.region' => 'ap-northeast-1',
        ]);

        $queue = Queue::connection('sqs');

        $this->assertInstanceOf(SqsQueue::class, $queue);
        $this->assertInstanceOf(SqsClient::class, $queue->getSqs());
        $this->assertSame('ap-northeast-1', $queue->getSqs()->getRegion());
        $this->assertSame('https://sqs.ap-northeast-1.amazonaws.com/123456789012/gatherconf', $queue->getQueue(null));
    }
}
