<?php

declare(strict_types=1);

namespace Tourze\SRT\Live;

/**
 * TSBPD (Time Stamp Based Packet Delivery) 管理器
 * 
 * 实现基于时间戳的数据包投递，用于控制 SRT Live 模式的播放延迟
 * 确保数据包按照正确的时间顺序投递，维持恒定的播放延迟
 */
class TsbpdManager
{
    /**
     * 播放延迟 (微秒)
     */
    private int $playbackDelay;

    /**
     * 基准时间戳 (微秒)
     */
    private int $baseTimestamp = 0;

    /**
     * 本地时钟偏移 (微秒)
     */
    private int $clockOffset = 0;

    /**
     * 时钟漂移率 (ppm)
     */
    private float $clockDriftRate = 0.0;

    /**
     * 包投递队列
     */
    private array $deliveryQueue = [];

    /**
     * 最后投递的时间戳
     */
    private int $lastDeliveryTime = 0;

    /**
     * 丢包时间窗口 (微秒)
     */
    private int $dropThreshold;

    /**
     * 是否启用延迟包丢弃
     */
    private bool $enableTooLatePacketDrop = true;

    /**
     * 统计信息
     */
    private array $stats = [
        'packets_delivered' => 0,
        'packets_dropped_too_late' => 0,
        'packets_dropped_too_early' => 0,
        'average_delivery_delay' => 0.0,
        'max_delivery_delay' => 0,
        'clock_drift_corrections' => 0,
    ];

    public function __construct(int $playbackDelayMs = 120)
    {
        $this->playbackDelay = $playbackDelayMs * 1000; // 转换为微秒
        $this->dropThreshold = $this->playbackDelay * 2; // 默认为播放延迟的2倍
    }

    /**
     * 添加数据包到投递队列
     */
    public function addPacket(string $data, int $timestamp, int $sequenceNumber): bool
    {
        $currentTime = $this->getCurrentTime();

        // 初始化基准时间戳
        if ($this->baseTimestamp === 0) {
            $this->baseTimestamp = $timestamp;
            $this->lastDeliveryTime = $currentTime;
        }

        // 计算投递时间
        $deliveryTime = $this->calculateDeliveryTime($timestamp);

        // 检查是否太晚到达
        if ($this->enableTooLatePacketDrop && $deliveryTime < $currentTime) {
            $this->stats['packets_dropped_too_late']++;
            return false;
        }

        // 检查是否太早到达 (时间戳错误)
        if ($deliveryTime > $currentTime + $this->playbackDelay * 10) {
            $this->stats['packets_dropped_too_early']++;
            return false;
        }

        // 添加到投递队列
        $this->deliveryQueue[] = [
            'data' => $data,
            'timestamp' => $timestamp,
            'sequence' => $sequenceNumber,
            'delivery_time' => $deliveryTime,
            'arrival_time' => $currentTime,
        ];

        // 按投递时间排序
        usort($this->deliveryQueue, function ($a, $b) {
            return $a['delivery_time'] <=> $b['delivery_time'];
        });

        return true;
    }

    /**
     * 获取准备投递的数据包
     */
    public function getReadyPackets(): array
    {
        $currentTime = $this->getCurrentTime();
        $readyPackets = [];

        while (!empty($this->deliveryQueue)) {
            $packet = $this->deliveryQueue[0];

            if ($packet['delivery_time'] <= $currentTime) {
                $readyPackets[] = array_shift($this->deliveryQueue);
                $this->stats['packets_delivered']++;

                // 更新统计信息
                $deliveryDelay = $currentTime - $packet['arrival_time'];
                $this->updateDelayStats($deliveryDelay);
            } else {
                break;
            }
        }

        return $readyPackets;
    }

    /**
     * 计算数据包投递时间
     */
    private function calculateDeliveryTime(int $timestamp): int
    {
        // 应用时钟漂移补偿
        $compensatedTimestamp = $this->applyClockDriftCompensation($timestamp);

        // 计算相对于基准时间戳的偏移
        $relativeTimestamp = $compensatedTimestamp - $this->baseTimestamp;

        // 计算投递时间 = 到达时间 + 播放延迟
        return $this->lastDeliveryTime + $relativeTimestamp + $this->playbackDelay;
    }

    /**
     * 应用时钟漂移补偿
     */
    private function applyClockDriftCompensation(int $timestamp): int
    {
        if ($this->clockDriftRate === 0.0 && $this->clockOffset === 0) {
            return $timestamp;
        }

        $elapsedTime = $timestamp - $this->baseTimestamp;
        $driftCorrection = (int)($elapsedTime * $this->clockDriftRate / 1000000.0);

        return $timestamp + $driftCorrection + $this->clockOffset;
    }

    /**
     * 更新时钟漂移率
     */
    public function updateClockDrift(float $driftRatePpm): void
    {
        $this->clockDriftRate = $driftRatePpm;
        $this->stats['clock_drift_corrections']++;
    }

    /**
     * 设置播放延迟
     */
    public function setPlaybackDelay(int $delayMs): void
    {
        $this->playbackDelay = $delayMs * 1000;
        $this->dropThreshold = $this->playbackDelay * 2;
    }

    /**
     * 启用/禁用延迟包丢弃
     */
    public function setTooLatePacketDrop(bool $enable): void
    {
        $this->enableTooLatePacketDrop = $enable;
    }

    /**
     * 清理过期数据包
     */
    public function cleanupExpiredPackets(): int
    {
        $currentTime = $this->getCurrentTime();
        $droppedCount = 0;

        foreach ($this->deliveryQueue as $index => $packet) {
            if ($packet['delivery_time'] < $currentTime - $this->dropThreshold) {
                unset($this->deliveryQueue[$index]);
                $droppedCount++;
                $this->stats['packets_dropped_too_late']++;
            }
        }

        // 重新索引数组
        $this->deliveryQueue = array_values($this->deliveryQueue);

        return $droppedCount;
    }

    /**
     * 获取当前时间 (微秒)
     */
    private function getCurrentTime(): int
    {
        return (int)(hrtime(true) / 1000);
    }

    /**
     * 更新延迟统计
     */
    private function updateDelayStats(int $delay): void
    {
        $this->stats['max_delivery_delay'] = max($this->stats['max_delivery_delay'], $delay);

        // 计算平均延迟 (简单移动平均)
        $alpha = 0.1; // 平滑因子
        $this->stats['average_delivery_delay'] =
            $alpha * $delay + (1 - $alpha) * $this->stats['average_delivery_delay'];
    }

    /**
     * 获取播放延迟
     */
    public function getPlaybackDelay(): int
    {
        return (int)($this->playbackDelay / 1000); // 返回毫秒
    }

    /**
     * 获取队列中的数据包数量
     */
    public function getQueueSize(): int
    {
        return count($this->deliveryQueue);
    }

    /**
     * 获取下一个投递时间
     */
    public function getNextDeliveryTime(): ?int
    {
        return !empty($this->deliveryQueue) ? $this->deliveryQueue[0]['delivery_time'] : null;
    }

    /**
     * 获取时钟漂移率
     */
    public function getClockDriftRate(): float
    {
        return $this->clockDriftRate;
    }

    /**
     * 设置时钟偏移
     */
    public function setClockOffset(int $offsetMicroseconds): void
    {
        $this->clockOffset = $offsetMicroseconds;
    }

    /**
     * 获取时钟偏移
     */
    public function getClockOffset(): int
    {
        return $this->clockOffset;
    }

    /**
     * 重置基准时间戳
     */
    public function resetBaseTimestamp(): void
    {
        $this->baseTimestamp = 0;
        $this->deliveryQueue = [];
    }

    /**
     * 获取缓冲延迟 (当前时间到下一个投递时间的差值)
     */
    public function getBufferDelay(): int
    {
        $nextDeliveryTime = $this->getNextDeliveryTime();

        if ($nextDeliveryTime === null) {
            return 0;
        }

        return max(0, $nextDeliveryTime - $this->getCurrentTime());
    }

    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        return [
            'playback_delay_ms' => $this->getPlaybackDelay(),
            'queue_size' => $this->getQueueSize(),
            'buffer_delay_us' => $this->getBufferDelay(),
            'clock_drift_rate_ppm' => $this->clockDriftRate,
            'too_late_drop_enabled' => $this->enableTooLatePacketDrop,
            ...$this->stats,
        ];
    }

    /**
     * 重置统计信息
     */
    public function resetStats(): void
    {
        $this->stats = [
            'packets_delivered' => 0,
            'packets_dropped_too_late' => 0,
            'packets_dropped_too_early' => 0,
            'average_delivery_delay' => 0.0,
            'max_delivery_delay' => 0,
            'clock_drift_corrections' => 0,
        ];
    }
}
