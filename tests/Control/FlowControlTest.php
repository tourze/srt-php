<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Control;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\SRT\Control\FlowControl;

/**
 * @internal
 */
#[CoversClass(FlowControl::class)]
final class FlowControlTest extends TestCase
{
    private FlowControl $flowControl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flowControl = new FlowControl(
            sendWindowSize: 100,
            receiveWindowSize: 100,
            maxSendRate: 1000000 // 1MB/s
        );
    }

    public function testInitialState(): void
    {
        $stats = $this->flowControl->getStats();

        $this->assertEquals(100, $stats['send_window_size']);
        $this->assertEquals(100, $stats['receive_window_size']);
        $this->assertEquals(1000000, $stats['current_send_rate']);
        $this->assertEquals(0, $stats['packets_in_flight']);
        $this->assertEquals(0.0, $stats['window_utilization']);
    }

    public function testCanSendWithinWindow(): void
    {
        $this->assertTrue($this->flowControl->canSend(1500));
    }

    public function testPacketSending(): void
    {
        $packetSize = 1500;

        $this->assertTrue($this->flowControl->canSend($packetSize));
        $this->flowControl->onPacketSent($packetSize);

        $stats = $this->flowControl->getStats();
        $this->assertEquals(1, $stats['packets_sent']);
        $this->assertEquals($packetSize, $stats['bytes_sent']);
    }

    public function testWindowFull(): void
    {
        // 填满发送窗口
        for ($i = 0; $i < 100; ++$i) {
            $this->flowControl->onPacketSent(1500);
        }

        // 窗口应该满了
        $this->assertFalse($this->flowControl->canSend(1500));

        $stats = $this->flowControl->getStats();
        $this->assertEquals(1.0, $stats['window_utilization']);
    }

    public function testAckProcessing(): void
    {
        // 发送一些包
        for ($i = 0; $i < 10; ++$i) {
            $this->flowControl->onPacketSent(1500);
        }

        // 确认5个包
        $this->flowControl->onPacketAcked(5);

        $stats = $this->flowControl->getStats();
        $this->assertEquals(0.05, $stats['window_utilization']); // (10-5)/100
    }

    public function testLossHandling(): void
    {
        // 发送一些包
        for ($i = 0; $i < 10; ++$i) {
            $this->flowControl->onPacketSent(1500);
        }

        $initialRate = $this->flowControl->getCurrentSendRate();

        // 报告丢包
        $this->flowControl->onPacketLost(2);

        $newRate = $this->flowControl->getCurrentSendRate();
        $this->assertLessThan($initialRate, $newRate);

        $stats = $this->flowControl->getStats();
        $this->assertEquals(2, $stats['packets_dropped']);
    }

    public function testRateAdjustment(): void
    {
        $initialRate = $this->flowControl->getCurrentSendRate();

        // 降低速率
        $this->flowControl->adjustSendRate(0.5);
        $this->assertEquals($initialRate * 0.5, $this->flowControl->getCurrentSendRate());

        // 提高速率
        $this->flowControl->adjustSendRate(2.0);
        $this->assertEquals($initialRate, $this->flowControl->getCurrentSendRate());
    }

    public function testWindowSizeUpdate(): void
    {
        $this->flowControl->updateSendWindow(200);
        $this->assertEquals(200, $this->flowControl->getSendWindowSize());

        $this->flowControl->updateReceiveWindow(150);
        $this->assertEquals(150, $this->flowControl->getReceiveWindowSize());
    }

    public function testTokenBucketRateLimit(): void
    {
        // 创建一个低速率的流量控制器
        $lowRateControl = new FlowControl(
            sendWindowSize: 1000,
            receiveWindowSize: 1000,
            maxSendRate: 1000 // 1KB/s
        );

        // 快速发送大包应该被速率限制
        $largePacket = 2000; // 2KB

        $canSendFirst = $lowRateControl->canSend($largePacket);
        if ($canSendFirst) {
            $lowRateControl->onPacketSent($largePacket);
        }

        // 立即尝试发送另一个大包应该被阻止
        $canSendSecond = $lowRateControl->canSend($largePacket);
        $this->assertFalse($canSendSecond);

        $stats = $lowRateControl->getStats();
        $this->assertGreaterThan(0, $stats['rate_limited_count']);
    }

    public function testStatsReset(): void
    {
        // 发送一些包
        $this->flowControl->onPacketSent(1500);
        $this->flowControl->onPacketLost(1);

        $stats = $this->flowControl->getStats();
        $this->assertGreaterThan(0, $stats['packets_sent']);
        $this->assertGreaterThan(0, $stats['packets_dropped']);

        // 重置统计
        $this->flowControl->resetStats();

        $stats = $this->flowControl->getStats();
        $this->assertEquals(0, $stats['packets_sent']);
        $this->assertEquals(0, $stats['packets_dropped']);
        $this->assertEquals(0, $stats['bytes_sent']);
    }

    public function testTokenBucketUtilization(): void
    {
        $utilization = $this->flowControl->getTokenBucketUtilization();
        $this->assertGreaterThanOrEqual(0.0, $utilization);
        $this->assertLessThanOrEqual(1.0, $utilization);
    }

    public function testAdjustSendRate(): void
    {
        $initialRate = $this->flowControl->getCurrentSendRate();

        $this->flowControl->adjustSendRate(0.5);
        $this->assertEquals($initialRate * 0.5, $this->flowControl->getCurrentSendRate());

        $this->flowControl->adjustSendRate(2.0);
        $this->assertEquals($initialRate, $this->flowControl->getCurrentSendRate());
    }

    public function testOnPacketAcked(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->flowControl->onPacketSent(1500);
        }

        $this->flowControl->onPacketAcked(3);

        $stats = $this->flowControl->getStats();
        $this->assertEquals(2, $stats['packets_in_flight']);
    }

    public function testOnPacketLost(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->flowControl->onPacketSent(1500);
        }

        $initialRate = $this->flowControl->getCurrentSendRate();
        $this->flowControl->onPacketLost(2);

        $stats = $this->flowControl->getStats();
        $this->assertEquals(2, $stats['packets_dropped']);
        $this->assertLessThan($initialRate, $this->flowControl->getCurrentSendRate());
    }

    public function testOnPacketSent(): void
    {
        $packetSize = 1500;
        $this->flowControl->onPacketSent($packetSize);

        $stats = $this->flowControl->getStats();
        $this->assertEquals(1, $stats['packets_sent']);
        $this->assertEquals($packetSize, $stats['bytes_sent']);
        $this->assertEquals(1, $stats['packets_in_flight']);
    }

    public function testUpdateSendWindow(): void
    {
        $this->flowControl->updateSendWindow(200);
        $this->assertEquals(200, $this->flowControl->getSendWindowSize());
    }

    public function testUpdateReceiveWindow(): void
    {
        $this->flowControl->updateReceiveWindow(150);
        $this->assertEquals(150, $this->flowControl->getReceiveWindowSize());
    }

    public function testResetStats(): void
    {
        $this->flowControl->onPacketSent(1500);
        $this->flowControl->onPacketLost(1);

        $this->flowControl->resetStats();

        $stats = $this->flowControl->getStats();
        $this->assertEquals(0, $stats['packets_sent']);
        $this->assertEquals(0, $stats['packets_dropped']);
        $this->assertEquals(0, $stats['bytes_sent']);
    }
}
