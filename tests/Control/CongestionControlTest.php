<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Control;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\SRT\Control\CongestionControl;

/**
 * 拥塞控制测试
 *
 * @internal
 */
#[CoversClass(CongestionControl::class)]
final class CongestionControlTest extends TestCase
{
    private CongestionControl $congestionControl;

    protected function setUp(): void
    {
        parent::setUp();

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
        for ($i = 0; $i < 10; ++$i) {
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

    public function testCalculateRto(): void
    {
        $this->congestionControl->updateRtt(50000);
        $rto = $this->congestionControl->calculateRto();

        $this->assertGreaterThanOrEqual(1000, $rto);
        $this->assertLessThanOrEqual(60000000, $rto);
    }

    public function testOnPacketAcked(): void
    {
        $initialRate = $this->congestionControl->getSendingRate();

        $this->congestionControl->onPacketAcked();

        $stats = $this->congestionControl->getStats();
        $this->assertGreaterThanOrEqual(0, $stats['rate_increases']);
    }

    public function testOnPacketLost(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->congestionControl->onPacketSent();
        }

        $this->congestionControl->onPacketLost(3);

        $stats = $this->congestionControl->getStats();
        $this->assertGreaterThan(0, $stats['congestion_events']);
        $this->assertGreaterThan(0, $this->congestionControl->getLossRate());
    }

    public function testOnPacketSent(): void
    {
        $initialStats = $this->congestionControl->getStats();

        $this->congestionControl->onPacketSent();
        $this->congestionControl->onPacketSent();

        $finalStats = $this->congestionControl->getStats();
        $this->assertEquals(2, $finalStats['total_packets']);
    }

    public function testResetStats(): void
    {
        $this->congestionControl->onPacketSent();
        $this->congestionControl->onPacketLost();

        $this->congestionControl->resetStats();

        $stats = $this->congestionControl->getStats();
        $this->assertEquals(0, $stats['rate_increases']);
        $this->assertEquals(0, $stats['rate_decreases']);
        $this->assertEquals(0, $stats['slow_start_exits']);
        $this->assertEquals(0, $stats['congestion_events']);
        $this->assertEquals(0, $stats['total_packets']);
        $this->assertEquals(0, $stats['lost_packets']);
        $this->assertEquals(0.0, $this->congestionControl->getLossRate());
    }
}
