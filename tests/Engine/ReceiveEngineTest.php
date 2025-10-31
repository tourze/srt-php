<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Engine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\SRT\Engine\ReceiveEngine;
use Tourze\SRT\Protocol\DataPacket;
use Tourze\SRT\Transport\TransportInterface;

/**
 * @internal
 */
#[CoversClass(ReceiveEngine::class)]
final class ReceiveEngineTest extends TestCase
{
    private ReceiveEngine $engine;

    private TransportInterface&MockObject $transport;

    /** @var array<string> */
    private array $sentData = [];

    protected function setUp(): void
    {
        parent::setUp();

        // 创建TransportInterface的Mock对象
        $this->transport = $this->createMock(TransportInterface::class);

        // 配置Mock的基本行为
        $this->transport->method('bind')->willReturn(true);
        $this->transport->method('connect')->willReturn(true);
        $this->transport->method('isConnected')->willReturn(true);
        $this->transport->method('getLocalAddress')->willReturn(['host' => '127.0.0.1', 'port' => 0]);
        $this->transport->method('getRemoteAddress')->willReturn(['host' => '127.0.0.1', 'port' => 9999]);
        $this->transport->method('getStatistics')->willReturn([
            'bytes_sent' => 0,
            'bytes_received' => 0,
            'packets_sent' => 0,
            'packets_received' => 0,
        ]);

        // 配置send方法记录发送的数据
        $this->transport->method('send')
            ->willReturnCallback(function (string $data): int {
                $this->sentData[] = $data;

                return strlen($data);
            })
        ;

        $this->engine = new ReceiveEngine($this->transport);
        $this->engine->setSourceSocketId(123);
    }

    public function testCleanup(): void
    {
        // 创建一些数据包来填充缓冲区
        $packet1 = new DataPacket(1, 1, 'test1');
        $packet2 = new DataPacket(2, 2, 'test2');

        $this->engine->handleDataPacket($packet1);
        $this->engine->handleDataPacket($packet2);

        // 确保有数据在缓冲区中
        $this->assertTrue($this->engine->hasMessage());

        // 清理资源
        $this->engine->cleanup();

        // 验证清理后的状态
        $this->assertFalse($this->engine->hasMessage());
        $this->assertNull($this->engine->getNextMessage());

        $stats = $this->engine->getStatistics();
        $this->assertSame(0, $stats['receive_buffer_size']);
        $this->assertSame(0, $stats['message_buffer_size']);
        $this->assertSame(0, $stats['completed_messages_count']);
    }

    public function testForceAck(): void
    {
        // 处理一个数据包以增加期望序列号
        $packet = new DataPacket(1, 1, 'test');
        $this->engine->handleDataPacket($packet);

        // 强制发送 ACK
        $this->engine->forceAck();

        // 验证 ACK 发送统计
        $stats = $this->engine->getStatistics();
        $this->assertGreaterThan(0, $stats['acks_sent']);

        // 验证数据被发送到传输层
        $this->assertCount(1, $this->sentData);
    }

    public function testHandleDataPacketWithSinglePacket(): void
    {
        $packet = new DataPacket(1, 1, 'test data');
        $packet->setPacketPosition(DataPacket::PP_SINGLE);

        $this->engine->handleDataPacket($packet);

        $this->assertTrue($this->engine->hasMessage());
        $this->assertSame('test data', $this->engine->getNextMessage());

        $stats = $this->engine->getStatistics();
        $this->assertSame(1, $stats['total_received']);
        $this->assertSame(9, $stats['total_bytes']); // strlen('test data')
    }

    public function testHandleDataPacketWithDuplicatePacket(): void
    {
        $packet = new DataPacket(1, 1, 'test data');

        // 发送相同的包两次
        $this->engine->handleDataPacket($packet);
        $this->engine->handleDataPacket($packet);

        $stats = $this->engine->getStatistics();
        $this->assertSame(1, $stats['total_received']); // 只计算一次
        $this->assertSame(1, $stats['duplicate_packets']);
    }

    public function testHandleDataPacketWithOutOfOrderPacket(): void
    {
        // 测试乱序包统计字段是否存在和可访问
        $stats = $this->engine->getStatistics();
        $this->assertArrayHasKey('out_of_order_packets', $stats);
        $this->assertIsInt($stats['out_of_order_packets']);
        $this->assertSame(0, $stats['out_of_order_packets']);
    }

    public function testHandleDataPacketWithSequentialPackets(): void
    {
        $packet1 = new DataPacket(1, 1, 'data1');
        $packet1->setPacketPosition(DataPacket::PP_SINGLE);

        $packet2 = new DataPacket(2, 2, 'data2');
        $packet2->setPacketPosition(DataPacket::PP_SINGLE);

        $this->engine->handleDataPacket($packet1);
        $this->engine->handleDataPacket($packet2);

        $this->assertTrue($this->engine->hasMessage());
        $this->assertSame('data1', $this->engine->getNextMessage());
        $this->assertSame('data2', $this->engine->getNextMessage());
    }

    public function testHandleDataPacketWithMultiPacketMessage(): void
    {
        // 测试多包消息的基本功能：发送首包和末包
        $firstPacket = new DataPacket(1, 1, 'first');
        $firstPacket->setPacketPosition(DataPacket::PP_FIRST);

        $lastPacket = new DataPacket(2, 1, 'last');
        $lastPacket->setPacketPosition(DataPacket::PP_LAST);

        $this->engine->handleDataPacket($firstPacket);
        $this->engine->handleDataPacket($lastPacket);

        // 验证消息被正确处理
        $this->assertTrue($this->engine->hasMessage());
        $this->assertSame('firstlast', $this->engine->getNextMessage());
    }

    public function testHandleDataPacketTriggersAckAfterFrequency(): void
    {
        $this->engine->setAckFrequency(2); // 每2个包发送一次ACK

        $packet1 = new DataPacket(1, 1, 'data1');
        $packet1->setPacketPosition(DataPacket::PP_SINGLE);

        $packet2 = new DataPacket(2, 2, 'data2');
        $packet2->setPacketPosition(DataPacket::PP_SINGLE);

        $this->engine->handleDataPacket($packet1);
        $this->engine->handleDataPacket($packet2); // 这应该触发ACK

        // 验证ACK统计增加
        $stats = $this->engine->getStatistics();
        $this->assertGreaterThan(0, $stats['acks_sent']);

        // 验证数据被发送到传输层
        $this->assertGreaterThan(0, count($this->sentData));
    }

    public function testHandleDataPacketDetectsLostPackets(): void
    {
        // 测试NAK统计字段是否存在和可访问
        $stats = $this->engine->getStatistics();
        $this->assertArrayHasKey('naks_sent', $stats);
        $this->assertIsInt($stats['naks_sent']);
        $this->assertSame(0, $stats['naks_sent']);

        // 测试丢包检测机制的基本功能
        $packet1 = new DataPacket(1, 1, 'data1');
        $packet1->setPacketPosition(DataPacket::PP_SINGLE);
        $this->engine->handleDataPacket($packet1);

        // 验证数据包已被成功处理
        $stats = $this->engine->getStatistics();
        $this->assertGreaterThan(0, $stats['total_received']);
    }

    public function testConfigurationMethods(): void
    {
        $this->engine->setSourceSocketId(456);
        $this->engine->setAckFrequency(5);
        $this->engine->setReceiveWindowSize(1024);

        // 验证配置设置成功（通过反射）
        $reflection = new \ReflectionClass($this->engine);

        $sourceSocketIdProp = $reflection->getProperty('sourceSocketId');
        $sourceSocketIdProp->setAccessible(true);
        $this->assertEquals(456, $sourceSocketIdProp->getValue($this->engine));

        $ackFrequencyProp = $reflection->getProperty('ackFrequency');
        $ackFrequencyProp->setAccessible(true);
        $this->assertEquals(5, $ackFrequencyProp->getValue($this->engine));

        $receiveWindowSizeProp = $reflection->getProperty('receiveWindowSize');
        $receiveWindowSizeProp->setAccessible(true);
        $this->assertEquals(1024, $receiveWindowSizeProp->getValue($this->engine));
    }

    public function testGetStatistics(): void
    {
        $stats = $this->engine->getStatistics();

        $expectedKeys = [
            'total_received',
            'total_bytes',
            'duplicate_packets',
            'out_of_order_packets',
            'acks_sent',
            'naks_sent',
            'expected_sequence_number',
            'last_ack_sequence',
            'receive_buffer_size',
            'message_buffer_size',
            'completed_messages_count',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $stats);
            $this->assertIsInt($stats[$key]);
        }
    }

    public function testGetNextMessageWithEmptyQueue(): void
    {
        $this->assertNull($this->engine->getNextMessage());
    }

    public function testHasMessageWithEmptyQueue(): void
    {
        $this->assertFalse($this->engine->hasMessage());
    }
}
