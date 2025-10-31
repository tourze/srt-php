<?php

declare(strict_types=1);

namespace Tourze\SRT\Control;

/**
 * SRT 流量控制管理器
 *
 * 负责管理发送窗口大小、发送速率控制和流量调节
 * 实现基于窗口的流量控制算法
 */
class FlowControl
{
    /**
     * 当前发送窗口中的包数量
     */
    private int $packetsInFlight = 0;

    /**
     * 当前发送速率 (bytes/second)
     */
    private int $currentSendRate;

    /**
     * 发送令牌桶
     */
    private float $tokenBucket = 0.0;

    /**
     * 令牌桶容量
     */
    private float $bucketCapacity;

    /**
     * 令牌填充速率 (tokens/second)
     */
    private float $tokenFillRate;

    /**
     * 上次令牌更新时间
     */
    private float $lastTokenUpdate;

    /**
     * 统计信息
     * @var array{packets_sent: int, packets_dropped: int, bytes_sent: int, rate_limited_count: int, window_full_count: int}
     */
    private array $stats = [
        'packets_sent' => 0,
        'packets_dropped' => 0,
        'bytes_sent' => 0,
        'rate_limited_count' => 0,
        'window_full_count' => 0,
    ];

    public function __construct(
        private int $sendWindowSize = 8192,
        private int $receiveWindowSize = 8192,
        private readonly int $maxSendRate = 1000000, // 1MB/s 默认
    ) {
        $this->currentSendRate = $maxSendRate;

        // 初始化令牌桶
        $this->bucketCapacity = $maxSendRate / 8.0; // 1/8秒的容量
        $this->tokenFillRate = $maxSendRate;
        $this->tokenBucket = $this->bucketCapacity;
        $this->lastTokenUpdate = microtime(true);
    }

    /**
     * 检查是否可以发送数据包
     */
    public function canSend(int $packetSize): bool
    {
        // 更新令牌桶
        $this->updateTokenBucket();

        // 检查发送窗口
        if ($this->packetsInFlight >= $this->sendWindowSize) {
            ++$this->stats['window_full_count'];

            return false;
        }

        // 检查速率限制
        if ($this->tokenBucket < $packetSize) {
            ++$this->stats['rate_limited_count'];

            return false;
        }

        return true;
    }

    /**
     * 记录数据包发送
     */
    public function onPacketSent(int $packetSize): void
    {
        ++$this->packetsInFlight;
        $this->tokenBucket -= $packetSize;
        ++$this->stats['packets_sent'];
        $this->stats['bytes_sent'] += $packetSize;
    }

    /**
     * 记录数据包确认 (ACK)
     */
    public function onPacketAcked(int $count = 1): void
    {
        $this->packetsInFlight = max(0, $this->packetsInFlight - $count);
    }

    /**
     * 记录数据包丢失
     */
    public function onPacketLost(int $count = 1): void
    {
        $this->packetsInFlight = max(0, $this->packetsInFlight - $count);
        $this->stats['packets_dropped'] += $count;

        // 丢包时适当降低发送速率
        $this->adjustSendRate(0.875); // 降低12.5%
    }

    /**
     * 更新发送窗口大小
     */
    public function updateSendWindow(int $newSize): void
    {
        $this->sendWindowSize = max(1, min(65536, $newSize));
    }

    /**
     * 更新接收窗口大小
     */
    public function updateReceiveWindow(int $newSize): void
    {
        $this->receiveWindowSize = max(1, min(65536, $newSize));
    }

    /**
     * 调整发送速率
     */
    public function adjustSendRate(float $factor): void
    {
        $this->currentSendRate = (int) ($this->currentSendRate * $factor);
        $this->currentSendRate = max(1000, min($this->maxSendRate, $this->currentSendRate));

        // 更新令牌桶参数
        $this->tokenFillRate = $this->currentSendRate;
        $this->bucketCapacity = $this->currentSendRate / 8.0;
        $this->tokenBucket = min($this->tokenBucket, $this->bucketCapacity);
    }

    /**
     * 更新令牌桶
     */
    private function updateTokenBucket(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastTokenUpdate;

        if ($elapsed > 0) {
            $tokensToAdd = $elapsed * $this->tokenFillRate;
            $this->tokenBucket = min($this->bucketCapacity, $this->tokenBucket + $tokensToAdd);
            $this->lastTokenUpdate = $now;
        }
    }

    /**
     * 获取当前发送窗口大小
     */
    public function getSendWindowSize(): int
    {
        return $this->sendWindowSize;
    }

    /**
     * 获取当前接收窗口大小
     */
    public function getReceiveWindowSize(): int
    {
        return $this->receiveWindowSize;
    }

    /**
     * 获取当前发送速率
     */
    public function getCurrentSendRate(): int
    {
        return $this->currentSendRate;
    }

    /**
     * 获取当前窗口使用情况
     */
    public function getWindowUtilization(): float
    {
        return $this->sendWindowSize > 0 ? $this->packetsInFlight / $this->sendWindowSize : 0.0;
    }

    /**
     * 获取令牌桶使用情况
     */
    public function getTokenBucketUtilization(): float
    {
        $this->updateTokenBucket();

        return $this->bucketCapacity > 0 ? $this->tokenBucket / $this->bucketCapacity : 0.0;
    }

    /**
     * 获取统计信息
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return array_merge($this->stats, [
            'send_window_size' => $this->sendWindowSize,
            'receive_window_size' => $this->receiveWindowSize,
            'packets_in_flight' => $this->packetsInFlight,
            'current_send_rate' => $this->currentSendRate,
            'window_utilization' => $this->getWindowUtilization(),
            'token_bucket_utilization' => $this->getTokenBucketUtilization(),
        ]);
    }

    /**
     * 重置统计信息
     */
    public function resetStats(): void
    {
        $this->stats = [
            'packets_sent' => 0,
            'packets_dropped' => 0,
            'bytes_sent' => 0,
            'rate_limited_count' => 0,
            'window_full_count' => 0,
        ];
    }
}
