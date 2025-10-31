<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Control;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\SRT\Control\RttEstimator;

/**
 * RTT估算器测试
 *
 * @internal
 */
#[CoversClass(RttEstimator::class)]
final class RttEstimatorTest extends TestCase
{
    private RttEstimator $rttEstimator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rttEstimator = new RttEstimator();
    }

    public function testInitialState(): void
    {
        $this->assertEquals(0, $this->rttEstimator->getCurrentRtt());
        $this->assertEquals(0.0, $this->rttEstimator->getSmoothedRtt());
        $this->assertEquals(0.0, $this->rttEstimator->getRttVariation());
        $this->assertEquals(0, $this->rttEstimator->getMinRtt());
        $this->assertEquals(0, $this->rttEstimator->getMaxRtt());
    }

    public function testFirstRttMeasurement(): void
    {
        $rtt = 50000; // 50ms
        $this->rttEstimator->updateRtt($rtt);

        $this->assertEquals($rtt, $this->rttEstimator->getCurrentRtt());
        $this->assertEquals($rtt, $this->rttEstimator->getSmoothedRtt());
        $this->assertEquals($rtt / 2.0, $this->rttEstimator->getRttVariation());
        $this->assertEquals($rtt, $this->rttEstimator->getMinRtt());
        $this->assertEquals($rtt, $this->rttEstimator->getMaxRtt());
    }

    public function testMultipleRttMeasurements(): void
    {
        $rtts = [50000, 60000, 45000, 55000]; // 50ms, 60ms, 45ms, 55ms

        foreach ($rtts as $rtt) {
            $this->rttEstimator->updateRtt($rtt);
        }

        $this->assertEquals(55000, $this->rttEstimator->getCurrentRtt());
        $this->assertGreaterThan(0, $this->rttEstimator->getSmoothedRtt());
        $this->assertEquals(45000, $this->rttEstimator->getMinRtt());
        $this->assertEquals(60000, $this->rttEstimator->getMaxRtt());
    }

    public function testRtoCalculation(): void
    {
        $this->rttEstimator->updateRtt(50000); // 50ms

        $rto = $this->rttEstimator->calculateRto();
        $this->assertGreaterThan(0, $rto);
        $this->assertGreaterThanOrEqual(50000, $rto); // RTO should be at least as much as RTT
    }

    public function testJitterCalculation(): void
    {
        // 添加一些RTT变化来测试抖动
        $this->rttEstimator->updateRtt(50000);
        $this->rttEstimator->updateRtt(70000);
        $this->rttEstimator->updateRtt(40000);

        $jitter = $this->rttEstimator->getJitter();
        $this->assertGreaterThan(0, $jitter);
    }

    public function testNetworkCondition(): void
    {
        $this->rttEstimator->updateRtt(50000);

        $condition = $this->rttEstimator->getNetworkCondition();
        $this->assertContains($condition, ['excellent', 'good', 'fair', 'poor', 'terrible', 'unknown']);
    }

    public function testStabilityScore(): void
    {
        $this->rttEstimator->updateRtt(50000);
        $this->rttEstimator->updateRtt(51000);
        $this->rttEstimator->updateRtt(49000);

        $score = $this->rttEstimator->getStabilityScore();
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function testSuggestedWindowSize(): void
    {
        $this->rttEstimator->updateRtt(50000);

        $bandwidth = 1000000; // 1MB/s
        $windowSize = $this->rttEstimator->getSuggestedWindowSize($bandwidth);
        $this->assertGreaterThan(0, $windowSize);
    }

    public function testRttHistory(): void
    {
        $this->rttEstimator->updateRtt(50000);
        $this->rttEstimator->updateRtt(60000);

        $history = $this->rttEstimator->getRttHistory();
        $this->assertCount(2, $history);
        $this->assertEquals(50000, $history[0]['rtt']);
        $this->assertEquals(60000, $history[1]['rtt']);
    }

    public function testJitterThresholdSetting(): void
    {
        $threshold = 10000; // 10ms
        $this->rttEstimator->setJitterThreshold($threshold);

        // 添加抖动测试
        $this->rttEstimator->updateRtt(50000);
        $this->rttEstimator->updateRtt(65000); // 15ms抖动，应该超过阈值

        $stats = $this->rttEstimator->getStats();
        $this->assertArrayHasKey('jitter_events', $stats);
    }

    public function testRtoRange(): void
    {
        $minRto = 5000;  // 5ms
        $maxRto = 120000000; // 120s

        $this->rttEstimator->setRtoRange($minRto, $maxRto);

        // 测试极小RTT
        $this->rttEstimator->updateRtt(1000); // 1ms
        $rto = $this->rttEstimator->calculateRto();
        $this->assertGreaterThanOrEqual($minRto, $rto);
    }

    public function testReset(): void
    {
        $this->rttEstimator->updateRtt(50000);
        $this->rttEstimator->updateRtt(60000);

        $this->rttEstimator->reset();

        $this->assertEquals(0, $this->rttEstimator->getCurrentRtt());
        $this->assertEquals(0.0, $this->rttEstimator->getSmoothedRtt());
        $this->assertEmpty($this->rttEstimator->getRttHistory());
    }

    public function testStatsTracking(): void
    {
        $stats = $this->rttEstimator->getStats();

        $this->assertArrayHasKey('total_measurements', $stats);
        $this->assertArrayHasKey('rto_timeouts', $stats);
        $this->assertArrayHasKey('jitter_events', $stats);
        $this->assertArrayHasKey('network_condition_changes', $stats);

        $this->assertEquals(0, $stats['total_measurements']);

        $this->rttEstimator->updateRtt(50000);

        $updatedStats = $this->rttEstimator->getStats();
        $this->assertEquals(1, $updatedStats['total_measurements']);
    }

    public function testResetStats(): void
    {
        $this->rttEstimator->updateRtt(50000);
        $this->rttEstimator->resetStats();

        $stats = $this->rttEstimator->getStats();
        $this->assertEquals(0, $stats['total_measurements']);
    }

    public function testCalculateRto(): void
    {
        $this->rttEstimator->updateRtt(50000);
        $rto = $this->rttEstimator->calculateRto();

        $this->assertGreaterThan(0, $rto);
        $this->assertGreaterThanOrEqual(50000, $rto);
    }

    public function testUpdateRtt(): void
    {
        $rtt = 75000;
        $this->rttEstimator->updateRtt($rtt);

        $this->assertEquals($rtt, $this->rttEstimator->getCurrentRtt());
        $this->assertGreaterThan(0, $this->rttEstimator->getSmoothedRtt());
    }
}
