<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Engine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\SRT\Engine\SendEngine;
use Tourze\SRT\Exception\SendException;
use Tourze\SRT\Protocol\ControlPacket;
use Tourze\SRT\Transport\TransportInterface;

/**
 * @internal
 */
#[CoversClass(SendEngine::class)]
final class SendEngineTest extends TestCase
{
    private SendEngine $engine;

    private TransportInterface&MockObject $transport;

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

        // 配置send方法返回数据长度
        $this->transport->method('send')
            ->willReturnCallback(fn (string $data): int => strlen($data))
        ;

        $this->engine = new SendEngine($this->transport);
        $this->engine->setDestinationSocketId(456);
    }

    public function testCleanup(): void
    {
        // 发送数据以填充未确认队列
        $this->engine->send('test data');

        // 确保有未确认的包
        $stats = $this->engine->getStatistics();
        $this->assertGreaterThan(0, $stats['unacknowledged_count']);

        // 清理资源
        $this->engine->cleanup();

        // 验证清理后的状态
        $stats = $this->engine->getStatistics();
        $this->assertSame(0, $stats['unacknowledged_count']);
        $this->assertSame(0, $stats['retransmission_queue_size']);
    }

    public function testHandleAck(): void
    {
        // 发送数据包
        $this->engine->send('test data');

        // 确保有未确认的包
        $stats = $this->engine->getStatistics();
        $this->assertGreaterThan(0, $stats['unacknowledged_count']);

        // 创建 ACK 包确认序列号1
        $ackPacket = ControlPacket::createAck(1, 456);

        // 处理 ACK
        $this->engine->handleAck($ackPacket);

        // 验证包已被确认
        $stats = $this->engine->getStatistics();
        $this->assertSame(0, $stats['unacknowledged_count']);
    }

    public function testHandleNak(): void
    {
        // 发送数据包
        $this->engine->send('test data');

        // 创建 NAK 包报告序列号1丢失
        $nakPacket = ControlPacket::createNak([1], 456);

        // 处理 NAK，应该安排重传
        $this->engine->handleNak($nakPacket);

        $stats = $this->engine->getStatistics();
        $this->assertGreaterThan(0, $stats['retransmission_queue_size']);
    }

    public function testProcessRetransmissions(): void
    {
        // 测试重传统计字段是否存在和可访问
        $stats = $this->engine->getStatistics();
        $this->assertArrayHasKey('total_retransmitted', $stats);
        $this->assertIsInt($stats['total_retransmitted']);
        $this->assertSame(0, $stats['total_retransmitted']);

        // 调用 processRetransmissions 方法不应该抛出异常
        $this->engine->processRetransmissions();

        // 验证处理后重传统计没有变化（因为没有重传包）
        $statsAfter = $this->engine->getStatistics();
        $this->assertSame(0, $statsAfter['total_retransmitted']);
    }

    public function testSendWithEmptyData(): void
    {
        // 发送空数据应该返回0
        $result = $this->engine->send('');
        $this->assertSame(0, $result);

        $stats = $this->engine->getStatistics();
        $this->assertSame(0, $stats['total_sent']);
    }

    public function testSendWithValidData(): void
    {
        $data = 'test message';
        $result = $this->engine->send($data);

        $this->assertSame(strlen($data), $result);

        $stats = $this->engine->getStatistics();
        $this->assertSame(1, $stats['total_sent']);
        $this->assertSame(strlen($data), $stats['total_bytes']);
        $this->assertSame(1, $stats['unacknowledged_count']);
    }

    public function testSendWithLargeData(): void
    {
        // 创建大于单个包载荷大小的数据
        $largeData = str_repeat('x', 3000); // 超过默认的1456字节

        $result = $this->engine->send($largeData);

        $this->assertSame(strlen($largeData), $result);

        $stats = $this->engine->getStatistics();
        $this->assertGreaterThan(1, $stats['total_sent']); // 应该发送多个包
        $this->assertGreaterThan(1, $stats['unacknowledged_count']);
    }

    public function testSendWindowFull(): void
    {
        // 设置很小的发送窗口
        $smallWindowEngine = new SendEngine($this->transport);
        $smallWindowEngine->setDestinationSocketId(456);

        // 通过反射设置小窗口大小
        $reflection = new \ReflectionClass($smallWindowEngine);
        $windowProperty = $reflection->getProperty('sendWindowSize');
        $windowProperty->setAccessible(true);
        $windowProperty->setValue($smallWindowEngine, 1);

        // 发送第一个包应该成功
        $smallWindowEngine->send('data1');

        // 发送第二个包应该失败，因为窗口已满
        $this->expectException(SendException::class);
        $this->expectExceptionMessage('Send window full');
        $smallWindowEngine->send('data2');
    }

    public function testConfigurationMethods(): void
    {
        $this->engine->setDestinationSocketId(789);
        $this->engine->setMaxPayloadSize(1200);
        $this->engine->setMaxBandwidth(500000);
        $this->engine->setRetransmissionTimeout(1000);

        // 验证配置设置成功（通过反射）
        $reflection = new \ReflectionClass($this->engine);

        $destSocketIdProp = $reflection->getProperty('destinationSocketId');
        $destSocketIdProp->setAccessible(true);
        $this->assertEquals(789, $destSocketIdProp->getValue($this->engine));

        $maxPayloadSizeProp = $reflection->getProperty('maxPayloadSize');
        $maxPayloadSizeProp->setAccessible(true);
        $this->assertEquals(1200, $maxPayloadSizeProp->getValue($this->engine));

        $maxBandwidthProp = $reflection->getProperty('maxBandwidth');
        $maxBandwidthProp->setAccessible(true);
        $this->assertEquals(500000, $maxBandwidthProp->getValue($this->engine));

        $retransmissionTimeoutProp = $reflection->getProperty('retransmissionTimeout');
        $retransmissionTimeoutProp->setAccessible(true);
        $this->assertEquals(1000, $retransmissionTimeoutProp->getValue($this->engine));
    }

    public function testSetMaxPayloadSizeWithBoundaryValues(): void
    {
        // 测试边界值
        $this->engine->setMaxPayloadSize(0); // 应该被设置为最小值1
        $this->engine->setMaxPayloadSize(100000); // 应该被限制为最大值65536

        // 验证边界值设置成功（通过反射）
        $reflection = new \ReflectionClass($this->engine);

        $maxPayloadSizeProp = $reflection->getProperty('maxPayloadSize');
        $maxPayloadSizeProp->setAccessible(true);
        $this->assertEquals(65536, $maxPayloadSizeProp->getValue($this->engine));
    }

    public function testSetMaxBandwidthWithMinimumValue(): void
    {
        // 测试最小带宽
        $this->engine->setMaxBandwidth(500); // 应该被设置为最小值1000

        // 验证最小值约束生效（通过反射）
        $reflection = new \ReflectionClass($this->engine);

        $maxBandwidthProp = $reflection->getProperty('maxBandwidth');
        $maxBandwidthProp->setAccessible(true);
        $this->assertEquals(1000, $maxBandwidthProp->getValue($this->engine));
    }

    public function testSetRetransmissionTimeoutWithMinimumValue(): void
    {
        // 测试最小重传超时
        $this->engine->setRetransmissionTimeout(5); // 应该被设置为最小值10

        // 验证超时最小值约束生效（通过反射）
        $reflection = new \ReflectionClass($this->engine);

        $retransmissionTimeoutProp = $reflection->getProperty('retransmissionTimeout');
        $retransmissionTimeoutProp->setAccessible(true);
        $this->assertEquals(10, $retransmissionTimeoutProp->getValue($this->engine));
    }

    public function testGetStatistics(): void
    {
        $stats = $this->engine->getStatistics();

        $expectedKeys = [
            'total_sent',
            'total_retransmitted',
            'total_bytes',
            'unacknowledged_count',
            'retransmission_queue_size',
            'send_window_size',
            'next_sequence_number',
            'next_message_number',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $stats);
            $this->assertIsInt($stats[$key]);
        }
    }

    public function testInitialStatistics(): void
    {
        $stats = $this->engine->getStatistics();

        $this->assertSame(0, $stats['total_sent']);
        $this->assertSame(0, $stats['total_retransmitted']);
        $this->assertSame(0, $stats['total_bytes']);
        $this->assertSame(0, $stats['unacknowledged_count']);
        $this->assertSame(0, $stats['retransmission_queue_size']);
        $this->assertSame(1, $stats['next_sequence_number']);
        $this->assertSame(1, $stats['next_message_number']);
    }

    public function testHandleAckWithMultiplePackets(): void
    {
        // 发送多个数据包
        $this->engine->send('data1');
        $this->engine->send('data2');
        $this->engine->send('data3');

        // 确认到序列号2的所有包
        $ackPacket = ControlPacket::createAck(2, 456);
        $this->engine->handleAck($ackPacket);

        $stats = $this->engine->getStatistics();
        // 应该只剩下序列号3的包未确认
        $this->assertSame(1, $stats['unacknowledged_count']);
    }

    public function testHandleNakWithMultipleLostPackets(): void
    {
        // 发送多个数据包
        $this->engine->send('data1');
        $this->engine->send('data2');
        $this->engine->send('data3');

        // 报告序列号1和3丢失
        $nakPacket = ControlPacket::createNak([1, 3], 456);
        $this->engine->handleNak($nakPacket);

        $stats = $this->engine->getStatistics();
        // 重传队列应该包含丢失的包
        $this->assertGreaterThan(0, $stats['retransmission_queue_size']);
    }
}
