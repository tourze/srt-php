<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Control;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Control\CongestionControl;

/**
 * 拥塞控制测试
 */
class CongestionControlTest extends TestCase
{
    private CongestionControl $congestionControl;

    protected function setUp(): void
    {
        $this->congestionControl = new CongestionControl();
    }

    public function testInitialState(): void
    {
        $this->assertGreaterThan(0, $this->congestionControl->getSendingRate());
        $this->assertEquals(0, $this->congestionControl->getCurrentRtt());
        $this->assertEquals(0.0, $this->congestionControl->getLossRate());
        $this->assertTrue($this->congestionControl->isInSlowStart());
    }

    public function testUpdateRtt(): void
    {
        $rtt = 50000; // 50ms
        $this->congestionControl->updateRtt($rtt);
        
        $this->assertEquals($rtt, $this->congestionControl->getCurrentRtt());
        $this->assertGreaterThan(0, $this->congestionControl->getSmoothedRtt());
    }

    public function testPacketSentTracking(): void
    {
        $this->congestionControl->onPacketSent();
        $this->congestionControl->onPacketSent();
        
        // 验证包发送被跟踪（通过丢包率验证）
        $this->assertEquals(0.0, $this->congestionControl->getLossRate());
    }

    public function testPacketLoss(): void
    {
        // 发送一些包
        for ($i = 0; $i < 10; $i++) {
            $this->congestionControl->onPacketSent();
        }
        
        // 模拟丢包
        $this->congestionControl->onPacketLost(2);
        
        $this->assertGreaterThan(0, $this->congestionControl->getLossRate());
    }

    public function testStatsTracking(): void
    {
        $stats = $this->congestionControl->getStats();
        
        $this->assertArrayHasKey('rate_increases', $stats);
        $this->assertArrayHasKey('rate_decreases', $stats);
        $this->assertArrayHasKey('slow_start_exits', $stats);
        $this->assertArrayHasKey('congestion_events', $stats);
    }
} 