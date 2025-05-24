<?php

declare(strict_types=1);

namespace Tourze\SRT\Control;

/**
 * 定时器结构
 */
class Timer
{
    private $callback;
    
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly int $expireTime,
        callable $callback,
        public readonly array $data = []
    ) {
        $this->callback = $callback;
    }
    
    public function getCallback(): callable
    {
        return $this->callback;
    }
}

/**
 * SRT 定时器管理器
 * 
 * 管理SRT协议中的各种定时器：
 * - 重传定时器 (Retransmission Timer)
 * - 保活定时器 (Keepalive Timer)
 * - ACK定时器 (ACK Timer)
 * - NAK定时器 (NAK Timer)
 */
class TimerManager
{
    /**
     * 定时器类型常量
     */
    public const TIMER_RETRANSMISSION = 'retransmission';
    public const TIMER_KEEPALIVE = 'keepalive';
    public const TIMER_ACK = 'ack';
    public const TIMER_NAK = 'nak';
    public const TIMER_HANDSHAKE = 'handshake';

    /**
     * 活跃定时器列表
     */
    private array $timers = [];
    
    /**
     * 定时器回调函数
     */
    private array $callbacks = [];
    
    /**
     * 默认超时时间 (微秒)
     */
    private array $defaultTimeouts = [
        self::TIMER_RETRANSMISSION => 1000000, // 1秒
        self::TIMER_KEEPALIVE => 1000000,      // 1秒
        self::TIMER_ACK => 10000,              // 10毫秒
        self::TIMER_NAK => 20000,              // 20毫秒
        self::TIMER_HANDSHAKE => 5000000,      // 5秒
    ];
    
    /**
     * 定时器统计
     */
    private array $stats = [
        'timers_created' => 0,
        'timers_expired' => 0,
        'timers_cancelled' => 0,
        'retransmissions' => 0,
        'keepalives_sent' => 0,
        'acks_sent' => 0,
        'naks_sent' => 0,
    ];

    public function __construct()
    {
        // 初始化定时器管理器
    }

    /**
     * 设置定时器
     */
    public function setTimer(
        string $type,
        string $id,
        ?int $timeout = null,
        ?callable $callback = null,
        array $data = []
    ): void {
        $timeout = $timeout ?? $this->defaultTimeouts[$type] ?? 1000000;
        $expireTime = (int)(microtime(true) * 1000000) + $timeout;
        
        $callback = $callback ?? $this->callbacks[$type] ?? null;
        if ($callback === null) {
            throw new \InvalidArgumentException("No callback set for timer type: {$type}");
        }
        
        $this->timers[$id] = new Timer($id, $type, $expireTime, $callback, $data);
        $this->stats['timers_created']++;
    }

    /**
     * 取消定时器
     */
    public function cancelTimer(string $id): bool
    {
        if (isset($this->timers[$id])) {
            unset($this->timers[$id]);
            $this->stats['timers_cancelled']++;
            return true;
        }
        return false;
    }

    /**
     * 检查并处理过期的定时器
     */
    public function processTick(): array
    {
        $currentTime = (int)(microtime(true) * 1000000);
        $expiredTimers = [];
        
        foreach ($this->timers as $id => $timer) {
            if ($currentTime >= $timer->expireTime) {
                $expiredTimers[] = $timer;
                unset($this->timers[$id]);
                $this->stats['timers_expired']++;
                
                // 更新类型统计
                $this->updateTypeStats($timer->type);
            }
        }
        
        // 执行过期定时器的回调
        foreach ($expiredTimers as $timer) {
            try {
                call_user_func($timer->getCallback(), $timer->id, $timer->type, $timer->data);
            } catch (\Throwable $e) {
                // 记录错误但不中断处理
                error_log("Timer callback error: " . $e->getMessage());
            }
        }
        
        return array_map(fn($timer) => [
            'id' => $timer->id,
            'type' => $timer->type,
            'data' => $timer->data
        ], $expiredTimers);
    }

    /**
     * 设置定时器类型的默认回调
     */
    public function setCallback(string $type, callable $callback): void
    {
        $this->callbacks[$type] = $callback;
    }

    /**
     * 设置重传定时器
     */
    public function setRetransmissionTimer(
        string $packetId,
        int $timeout,
        array $packetData = []
    ): void {
        $this->setTimer(
            self::TIMER_RETRANSMISSION,
            "retx_{$packetId}",
            $timeout,
            null,
            $packetData
        );
    }

    /**
     * 设置保活定时器
     */
    public function setKeepaliveTimer(?int $timeout = null): void
    {
        $this->setTimer(
            self::TIMER_KEEPALIVE,
            'keepalive',
            $timeout
        );
    }

    /**
     * 设置ACK定时器
     */
    public function setAckTimer(int $sequenceNumber, ?int $timeout = null): void
    {
        $this->setTimer(
            self::TIMER_ACK,
            "ack_{$sequenceNumber}",
            $timeout,
            null,
            ['sequence_number' => $sequenceNumber]
        );
    }

    /**
     * 设置NAK定时器
     */
    public function setNakTimer(array $lostSequences, ?int $timeout = null): void
    {
        $this->setTimer(
            self::TIMER_NAK,
            'nak_' . implode('_', $lostSequences),
            $timeout,
            null,
            ['lost_sequences' => $lostSequences]
        );
    }

    /**
     * 设置握手定时器
     */
    public function setHandshakeTimer(string $stage, ?int $timeout = null): void
    {
        $this->setTimer(
            self::TIMER_HANDSHAKE,
            "handshake_{$stage}",
            $timeout,
            null,
            ['stage' => $stage]
        );
    }

    /**
     * 取消重传定时器
     */
    public function cancelRetransmissionTimer(string $packetId): bool
    {
        return $this->cancelTimer("retx_{$packetId}");
    }

    /**
     * 取消ACK定时器
     */
    public function cancelAckTimer(int $sequenceNumber): bool
    {
        return $this->cancelTimer("ack_{$sequenceNumber}");
    }

    /**
     * 获取活跃定时器数量
     */
    public function getActiveTimerCount(): int
    {
        return count($this->timers);
    }

    /**
     * 获取指定类型的活跃定时器数量
     */
    public function getActiveTimerCountByType(string $type): int
    {
        return count(array_filter($this->timers, fn($timer) => $timer->type === $type));
    }

    /**
     * 获取最近的定时器过期时间
     */
    public function getNextExpireTime(): ?int
    {
        if (empty($this->timers)) {
            return null;
        }
        
        $minExpireTime = min(array_map(fn($timer) => $timer->expireTime, $this->timers));
        return $minExpireTime;
    }

    /**
     * 获取距离下次定时器过期的时间 (微秒)
     */
    public function getTimeToNextExpire(): ?int
    {
        $nextExpire = $this->getNextExpireTime();
        if ($nextExpire === null) {
            return null;
        }
        
        $currentTime = (int)(microtime(true) * 1000000);
        return max(0, $nextExpire - $currentTime);
    }

    /**
     * 检查定时器是否存在
     */
    public function hasTimer(string $id): bool
    {
        return isset($this->timers[$id]);
    }

    /**
     * 获取定时器信息
     */
    public function getTimerInfo(string $id): ?array
    {
        if (!isset($this->timers[$id])) {
            return null;
        }
        
        $timer = $this->timers[$id];
        $currentTime = (int)(microtime(true) * 1000000);
        
        return [
            'id' => $timer->id,
            'type' => $timer->type,
            'expire_time' => $timer->expireTime,
            'remaining_time' => max(0, $timer->expireTime - $currentTime),
            'data' => $timer->data,
        ];
    }

    /**
     * 获取所有活跃定时器信息
     */
    public function getAllTimers(): array
    {
        $result = [];
        foreach ($this->timers as $timer) {
            $result[] = $this->getTimerInfo($timer->id);
        }
        return $result;
    }

    /**
     * 更新类型统计
     */
    private function updateTypeStats(string $type): void
    {
        switch ($type) {
            case self::TIMER_RETRANSMISSION:
                $this->stats['retransmissions']++;
                break;
            case self::TIMER_KEEPALIVE:
                $this->stats['keepalives_sent']++;
                break;
            case self::TIMER_ACK:
                $this->stats['acks_sent']++;
                break;
            case self::TIMER_NAK:
                $this->stats['naks_sent']++;
                break;
        }
    }

    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        return array_merge($this->stats, [
            'active_timers' => count($this->timers),
            'active_retransmission_timers' => $this->getActiveTimerCountByType(self::TIMER_RETRANSMISSION),
            'active_keepalive_timers' => $this->getActiveTimerCountByType(self::TIMER_KEEPALIVE),
            'active_ack_timers' => $this->getActiveTimerCountByType(self::TIMER_ACK),
            'active_nak_timers' => $this->getActiveTimerCountByType(self::TIMER_NAK),
            'active_handshake_timers' => $this->getActiveTimerCountByType(self::TIMER_HANDSHAKE),
            'next_expire_time' => $this->getNextExpireTime(),
            'time_to_next_expire' => $this->getTimeToNextExpire(),
        ]);
    }

    /**
     * 重置统计信息
     */
    public function resetStats(): void
    {
        $this->stats = [
            'timers_created' => 0,
            'timers_expired' => 0,
            'timers_cancelled' => 0,
            'retransmissions' => 0,
            'keepalives_sent' => 0,
            'acks_sent' => 0,
            'naks_sent' => 0,
        ];
    }

    /**
     * 清除所有定时器
     */
    public function clearAllTimers(): void
    {
        $this->timers = [];
    }
}
