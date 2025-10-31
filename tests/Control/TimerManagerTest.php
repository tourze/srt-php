<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Control;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\SRT\Control\TimerManager;
use Tourze\SRT\Exception\InvalidTimerException;

/**
 * 定时器管理器测试
 *
 * @internal
 */
#[CoversClass(TimerManager::class)]
final class TimerManagerTest extends TestCase
{
    private TimerManager $timerManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timerManager = new TimerManager();
    }

    public function testInitialState(): void
    {
        $this->assertEquals(0, $this->timerManager->getActiveTimerCount());
        $this->assertNull($this->timerManager->getNextExpireTime());
        $this->assertEmpty($this->timerManager->getAllTimers());
    }

    public function testSetCallback(): void
    {
        $callback = fn () => 'executed';
        $this->timerManager->setCallback(TimerManager::TIMER_ACK, $callback);

        // 设置了回调后应该能够创建定时器
        $this->timerManager->setTimer(
            TimerManager::TIMER_ACK,
            'test-timer',
            1000000
        );

        $this->assertEquals(1, $this->timerManager->getActiveTimerCount());
    }

    public function testSetTimerWithoutCallback(): void
    {
        $this->expectException(InvalidTimerException::class);
        $this->expectExceptionMessage('No callback set for timer type: ' . TimerManager::TIMER_ACK);

        $this->timerManager->setTimer(
            TimerManager::TIMER_ACK,
            'test-timer',
            1000000
        );
    }

    public function testSetTimerWithCustomCallback(): void
    {
        $callback = fn () => 'custom';

        $this->timerManager->setTimer(
            TimerManager::TIMER_ACK,
            'test-timer',
            1000000,
            $callback
        );

        $this->assertEquals(1, $this->timerManager->getActiveTimerCount());
    }

    public function testCancelTimer(): void
    {
        $this->timerManager->setCallback(TimerManager::TIMER_ACK, fn () => null);
        $this->timerManager->setTimer(TimerManager::TIMER_ACK, 'test-timer', 1000000);

        $this->assertEquals(1, $this->timerManager->getActiveTimerCount());

        $result = $this->timerManager->cancelTimer('test-timer');

        $this->assertTrue($result);
        $this->assertEquals(0, $this->timerManager->getActiveTimerCount());
    }

    public function testCancelNonExistentTimer(): void
    {
        $result = $this->timerManager->cancelTimer('non-existent');
        $this->assertFalse($result);
    }

    public function testHasTimer(): void
    {
        $this->assertFalse($this->timerManager->hasTimer('test-timer'));

        $this->timerManager->setCallback(TimerManager::TIMER_ACK, fn () => null);
        $this->timerManager->setTimer(TimerManager::TIMER_ACK, 'test-timer', 1000000);

        $this->assertTrue($this->timerManager->hasTimer('test-timer'));
    }

    public function testGetTimerInfo(): void
    {
        $this->assertNull($this->timerManager->getTimerInfo('non-existent'));

        $this->timerManager->setCallback(TimerManager::TIMER_ACK, fn () => null);
        $this->timerManager->setTimer(
            TimerManager::TIMER_ACK,
            'test-timer',
            1000000,
            null,
            ['key' => 'value']
        );

        $info = $this->timerManager->getTimerInfo('test-timer');

        $this->assertIsArray($info);
        $this->assertEquals('test-timer', $info['id']);
        $this->assertEquals(TimerManager::TIMER_ACK, $info['type']);
        $this->assertArrayHasKey('expire_time', $info);
        $this->assertEquals(['key' => 'value'], $info['data']);
    }

    public function testProcessTickWithExpiredTimers(): void
    {
        $executed = false;
        $callback = function () use (&$executed): void {
            $executed = true;
        };

        // 设置一个已经过期的定时器（负超时时间）
        $this->timerManager->setTimer(
            TimerManager::TIMER_ACK,
            'expired-timer',
            -1000000, // 负数表示已过期
            $callback
        );

        $expiredTimers = $this->timerManager->processTick();

        $this->assertCount(1, $expiredTimers);
        $this->assertTrue($executed);
        $this->assertEquals(0, $this->timerManager->getActiveTimerCount());
    }

    public function testGetActiveTimerCountByType(): void
    {
        $this->timerManager->setCallback(TimerManager::TIMER_ACK, fn () => null);
        $this->timerManager->setCallback(TimerManager::TIMER_NAK, fn () => null);

        $this->timerManager->setTimer(TimerManager::TIMER_ACK, 'ack1', 1000000);
        $this->timerManager->setTimer(TimerManager::TIMER_ACK, 'ack2', 1000000);
        $this->timerManager->setTimer(TimerManager::TIMER_NAK, 'nak1', 1000000);

        $this->assertEquals(2, $this->timerManager->getActiveTimerCountByType(TimerManager::TIMER_ACK));
        $this->assertEquals(1, $this->timerManager->getActiveTimerCountByType(TimerManager::TIMER_NAK));
        $this->assertEquals(0, $this->timerManager->getActiveTimerCountByType(TimerManager::TIMER_KEEPALIVE));
    }

    public function testSetRetransmissionTimer(): void
    {
        $this->timerManager->setCallback(TimerManager::TIMER_RETRANSMISSION, fn () => null);

        $this->timerManager->setRetransmissionTimer('packet-123', 500000, ['data' => 'test']);

        $this->assertTrue($this->timerManager->hasTimer('retx_packet-123'));
        $this->assertEquals(1, $this->timerManager->getActiveTimerCountByType(TimerManager::TIMER_RETRANSMISSION));
    }

    public function testSetKeepaliveTimer(): void
    {
        $this->timerManager->setCallback(TimerManager::TIMER_KEEPALIVE, fn () => null);

        $this->timerManager->setKeepaliveTimer();

        $this->assertTrue($this->timerManager->hasTimer('keepalive'));
    }

    public function testSetAckTimer(): void
    {
        $this->timerManager->setCallback(TimerManager::TIMER_ACK, fn () => null);

        $this->timerManager->setAckTimer(12345);

        $this->assertTrue($this->timerManager->hasTimer('ack_12345'));
    }

    public function testSetNakTimer(): void
    {
        $this->timerManager->setCallback(TimerManager::TIMER_NAK, fn () => null);

        $this->timerManager->setNakTimer([100, 101, 102]);

        $this->assertTrue($this->timerManager->hasTimer('nak_100_101_102'));
    }

    public function testSetHandshakeTimer(): void
    {
        $this->timerManager->setCallback(TimerManager::TIMER_HANDSHAKE, fn () => null);

        $this->timerManager->setHandshakeTimer('initial');

        $this->assertTrue($this->timerManager->hasTimer('handshake_initial'));
    }

    public function testCancelRetransmissionTimer(): void
    {
        $this->timerManager->setCallback(TimerManager::TIMER_RETRANSMISSION, fn () => null);
        $this->timerManager->setRetransmissionTimer('packet-123', 500000);

        $result = $this->timerManager->cancelRetransmissionTimer('packet-123');

        $this->assertTrue($result);
        $this->assertFalse($this->timerManager->hasTimer('retx_packet-123'));
    }

    public function testCancelAckTimer(): void
    {
        $this->timerManager->setCallback(TimerManager::TIMER_ACK, fn () => null);
        $this->timerManager->setAckTimer(12345);

        $result = $this->timerManager->cancelAckTimer(12345);

        $this->assertTrue($result);
        $this->assertFalse($this->timerManager->hasTimer('ack_12345'));
    }

    public function testGetTimeToNextExpire(): void
    {
        $this->assertNull($this->timerManager->getTimeToNextExpire());

        $this->timerManager->setCallback(TimerManager::TIMER_ACK, fn () => null);
        $this->timerManager->setTimer(TimerManager::TIMER_ACK, 'test', 1000000);

        $timeToExpire = $this->timerManager->getTimeToNextExpire();
        $this->assertIsInt($timeToExpire);
        $this->assertGreaterThan(0, $timeToExpire);
    }

    public function testGetStats(): void
    {
        $stats = $this->timerManager->getStats();

        $this->assertArrayHasKey('timers_created', $stats);
        $this->assertArrayHasKey('timers_expired', $stats);
        $this->assertArrayHasKey('timers_cancelled', $stats);
        $this->assertEquals(0, $stats['timers_created']);
    }

    public function testResetStats(): void
    {
        $this->timerManager->setCallback(TimerManager::TIMER_ACK, fn () => null);
        $this->timerManager->setTimer(TimerManager::TIMER_ACK, 'test', 1000000);

        $this->timerManager->resetStats();

        $stats = $this->timerManager->getStats();
        $this->assertEquals(0, $stats['timers_created']);
    }

    public function testClearAllTimers(): void
    {
        $this->timerManager->setCallback(TimerManager::TIMER_ACK, fn () => null);
        $this->timerManager->setTimer(TimerManager::TIMER_ACK, 'test1', 1000000);
        $this->timerManager->setTimer(TimerManager::TIMER_ACK, 'test2', 1000000);

        $this->assertEquals(2, $this->timerManager->getActiveTimerCount());

        $this->timerManager->clearAllTimers();

        $this->assertEquals(0, $this->timerManager->getActiveTimerCount());
    }
}
