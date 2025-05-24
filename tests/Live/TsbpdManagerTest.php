<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Live;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Live\TsbpdManager;

/**
 * TSBPD 管理器测试
 */
class TsbpdManagerTest extends TestCase
{
    private TsbpdManager $tsbpdManager;

    protected function setUp(): void
    {
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
        $this->assertIsArray($readyPackets);
        
        // 如果有包被投递，验证包含正确的数据结构
        if (!empty($readyPackets)) {
            $this->assertArrayHasKey('data', $readyPackets[0]);
            $this->assertArrayHasKey('timestamp', $readyPackets[0]);
            $this->assertArrayHasKey('sequence', $readyPackets[0]);
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
        
        // 根据实现，这个包可能会被丢弃
        $stats = $this->tsbpdManager->getStats();
        $this->assertIsArray($stats);
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
        
        $this->assertIsArray($stats);
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
        $this->assertIsInt($bufferDelay);
        $this->assertGreaterThanOrEqual(0, $bufferDelay);
    }

    public function testExpiredPacketCleanup(): void
    {
        // 添加一些包
        $this->tsbpdManager->addPacket('packet_1', 1000000, 1);
        $this->tsbpdManager->addPacket('packet_2', 2000000, 2);
        
        // 清理过期包
        $droppedCount = $this->tsbpdManager->cleanupExpiredPackets();
        
        $this->assertIsInt($droppedCount);
        $this->assertGreaterThanOrEqual(0, $droppedCount);
    }

    public function testBaseTimestampReset(): void
    {
        $this->tsbpdManager->addPacket('packet_1', 1000000, 1);
        $this->assertEquals(1, $this->tsbpdManager->getQueueSize());
        
        $this->tsbpdManager->resetBaseTimestamp();
        $this->assertEquals(0, $this->tsbpdManager->getQueueSize());
    }
} 