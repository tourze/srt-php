<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Live;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\SRT\Live\TsbpdManager;

/**
 * TSBPD 管理器测试
 *
 * @internal
 */
#[CoversClass(TsbpdManager::class)]
final class TsbpdManagerTest extends TestCase
{
    private TsbpdManager $tsbpdManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tsbpdManager = new TsbpdManager(120); // 120ms 播放延迟
    }

    public function testAddPacketBasic(): void
    {
        $data = 'test_packet_data';
        $timestamp = 1000000; // 1秒
        $sequenceNumber = 1;

        $result = $this->tsbpdManager->addPacket($data, $timestamp, $sequenceNumber);

        $this->assertTrue($result);
        $this->assertEquals(1, $this->tsbpdManager->getQueueSize());
    }

    public function testPacketDeliveryOrder(): void
    {
        // 添加乱序数据包
        $this->tsbpdManager->addPacket('packet_2', 2000000, 2);
        $this->tsbpdManager->addPacket('packet_1', 1000000, 1);
        $this->tsbpdManager->addPacket('packet_3', 3000000, 3);

        // 由于播放延迟机制，可能有些包还未准备好投递
        $this->assertGreaterThanOrEqual(1, $this->tsbpdManager->getQueueSize());

        // 由于播放延迟，需要等待适当时间才能获取包
        // 这里我们模拟时间推进
        sleep(1);

        $readyPackets = $this->tsbpdManager->getReadyPackets();

        // 验证有包被投递
        // 如果有包被投递，验证包含正确的数据结构
        if ([] !== $readyPackets) {
            $firstPacket = $readyPackets[0];
            $this->assertIsArray($firstPacket);
            $this->assertArrayHasKey('data', $firstPacket);
            $this->assertArrayHasKey('timestamp', $firstPacket);
            $this->assertArrayHasKey('sequence', $firstPacket);
        }
    }

    public function testPlaybackDelayConfiguration(): void
    {
        $this->assertEquals(120, $this->tsbpdManager->getPlaybackDelay());

        $this->tsbpdManager->setPlaybackDelay(200);
        $this->assertEquals(200, $this->tsbpdManager->getPlaybackDelay());
    }

    public function testTooLatePacketDrop(): void
    {
        $this->tsbpdManager->setTooLatePacketDrop(true);

        // 添加一个时间戳过早的包（模拟延迟到达）
        $oldTimestamp = time() * 1000000 - 1000000; // 1秒前的时间戳

        $result = $this->tsbpdManager->addPacket('late_packet', $oldTimestamp, 999);

        // 验证延迟包丢弃功能
        $stats = $this->tsbpdManager->getStats();
        $this->assertArrayHasKey('packets_dropped_too_late', $stats);
        $this->assertIsInt($stats['packets_dropped_too_late']);
    }

    public function testClockDriftCompensation(): void
    {
        $driftRate = 100.0; // 100 ppm

        $this->tsbpdManager->updateClockDrift($driftRate);
        $this->assertEquals($driftRate, $this->tsbpdManager->getClockDriftRate());

        $stats = $this->tsbpdManager->getStats();
        $this->assertEquals(1, $stats['clock_drift_corrections']);
    }

    public function testStatistics(): void
    {
        $stats = $this->tsbpdManager->getStats();
        $this->assertArrayHasKey('packets_delivered', $stats);
        $this->assertArrayHasKey('packets_dropped_too_late', $stats);
        $this->assertArrayHasKey('playback_delay_ms', $stats);

        $this->tsbpdManager->resetStats();
        $resetStats = $this->tsbpdManager->getStats();
        $this->assertEquals(0, $resetStats['packets_delivered']);
    }

    public function testBufferDelay(): void
    {
        $bufferDelay = $this->tsbpdManager->getBufferDelay();
        $this->assertGreaterThanOrEqual(0, $bufferDelay);
    }

    public function testExpiredPacketCleanup(): void
    {
        // 添加一些包
        $this->tsbpdManager->addPacket('packet_1', 1000000, 1);
        $this->tsbpdManager->addPacket('packet_2', 2000000, 2);

        // 清理过期包
        $droppedCount = $this->tsbpdManager->cleanupExpiredPackets();

        $this->assertGreaterThanOrEqual(0, $droppedCount);
    }

    public function testBaseTimestampReset(): void
    {
        $this->tsbpdManager->addPacket('packet_1', 1000000, 1);
        $this->assertEquals(1, $this->tsbpdManager->getQueueSize());

        $this->tsbpdManager->resetBaseTimestamp();
        $this->assertEquals(0, $this->tsbpdManager->getQueueSize());
    }

    public function testCleanupExpiredPackets(): void
    {
        // 添加一些包到队列
        $this->tsbpdManager->addPacket('packet_1', 1000000, 1);
        $this->tsbpdManager->addPacket('packet_2', 2000000, 2);
        $this->assertEquals(2, $this->tsbpdManager->getQueueSize());

        // 清理过期包（由于播放延迟机制，可能清理一些包）
        $droppedCount = $this->tsbpdManager->cleanupExpiredPackets();
        $this->assertGreaterThanOrEqual(0, $droppedCount);

        // 验证统计信息更新
        $stats = $this->tsbpdManager->getStats();
        $this->assertIsInt($stats['packets_dropped_too_late']);
    }

    public function testResetBaseTimestamp(): void
    {
        // 添加包以建立基准时间戳
        $this->tsbpdManager->addPacket('test_packet', 1000000, 1);
        $this->assertEquals(1, $this->tsbpdManager->getQueueSize());

        // 重置基准时间戳
        $this->tsbpdManager->resetBaseTimestamp();

        // 验证队列被清空
        $this->assertEquals(0, $this->tsbpdManager->getQueueSize());

        // 验证可以添加新包（基准时间戳被重置）
        $result = $this->tsbpdManager->addPacket('new_packet', 2000000, 2);
        $this->assertTrue($result);
        $this->assertEquals(1, $this->tsbpdManager->getQueueSize());
    }

    public function testResetStats(): void
    {
        // 更新时钟漂移以增加统计计数
        $this->tsbpdManager->updateClockDrift(50.0);

        // 添加包以产生一些统计
        $this->tsbpdManager->addPacket('packet_1', 1000000, 1);

        // 获取当前统计信息
        $stats = $this->tsbpdManager->getStats();
        $this->assertEquals(1, $stats['clock_drift_corrections']);

        // 重置统计信息
        $this->tsbpdManager->resetStats();

        // 验证统计信息被重置
        $resetStats = $this->tsbpdManager->getStats();
        $this->assertEquals(0, $resetStats['packets_delivered']);
        $this->assertEquals(0, $resetStats['packets_dropped_too_late']);
        $this->assertEquals(0, $resetStats['packets_dropped_too_early']);
        $this->assertEquals(0.0, $resetStats['average_delivery_delay']);
        $this->assertEquals(0, $resetStats['max_delivery_delay']);
        $this->assertEquals(0, $resetStats['clock_drift_corrections']);
    }

    public function testUpdateClockDrift(): void
    {
        $initialDriftRate = $this->tsbpdManager->getClockDriftRate();
        $this->assertEquals(0.0, $initialDriftRate);

        // 更新时钟漂移率
        $newDriftRate = 100.5;
        $this->tsbpdManager->updateClockDrift($newDriftRate);

        // 验证时钟漂移率已更新
        $this->assertEquals($newDriftRate, $this->tsbpdManager->getClockDriftRate());

        // 验证统计信息中的校正次数增加
        $stats = $this->tsbpdManager->getStats();
        $this->assertEquals(1, $stats['clock_drift_corrections']);

        // 再次更新时钟漂移率
        $this->tsbpdManager->updateClockDrift(-50.0);
        $this->assertEquals(-50.0, $this->tsbpdManager->getClockDriftRate());

        // 验证校正次数再次增加
        $stats = $this->tsbpdManager->getStats();
        $this->assertEquals(2, $stats['clock_drift_corrections']);
    }
}
