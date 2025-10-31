<?php

declare(strict_types=1);

namespace Tourze\SRT\Control;

/**
 * RTT (Round Trip Time) 估算器
 *
 * 实现精确的往返时间测量和网络性能分析，包括：
 * - RTT 平滑算法 (RFC 6298)
 * - 网络抖动测量
 * - 网络条件评估
 * - 自适应超时计算
 */
class RttEstimator
{
    /**
     * 平滑 RTT (微秒)
     */
    private float $smoothedRtt = 0.0;

    /**
     * RTT 变化量 (微秒)
     */
    private float $rttVariation = 0.0;

    /**
     * 当前 RTT (微秒)
     */
    private int $currentRtt = 0;

    /**
     * 最小 RTT (微秒)
     */
    private int $minRtt = 0;

    /**
     * 最大 RTT (微秒)
     */
    private int $maxRtt = 0;

    /**
     * RTT 历史记录
     * @var array<array{rtt: int, timestamp: int}>
     */
    private array $rttHistory = [];

    /**
     * RTO 乘数 K (RFC 6298)
     */
    private int $k = 4;

    /**
     * 最小 RTO (微秒)
     */
    private int $minRto = 1000; // 1ms

    /**
     * 最大 RTO (微秒)
     */
    private int $maxRto = 60000000; // 60s

    /**
     * 网络抖动阈值 (微秒)
     */
    private int $jitterThreshold = 5000; // 5ms

    /**
     * 统计信息
     * @var array{total_measurements: int, rto_timeouts: int, jitter_events: int, network_condition_changes: int}
     */
    private array $stats = [
        'total_measurements' => 0,
        'rto_timeouts' => 0,
        'jitter_events' => 0,
        'network_condition_changes' => 0,
    ];

    public function __construct(
        private readonly float $alpha = 0.125,
        private readonly float $beta = 0.25,
        private readonly int $maxHistorySize = 100,
    ) {
    }

    /**
     * 更新 RTT 测量
     */
    public function updateRtt(int $rtt): void
    {
        $this->currentRtt = $rtt;
        ++$this->stats['total_measurements'];

        // 初始化时的特殊处理
        if (0.0 === $this->smoothedRtt) {
            $this->smoothedRtt = $rtt;
            $this->rttVariation = $rtt / 2.0;
            $this->minRtt = $rtt;
            $this->maxRtt = $rtt;
        } else {
            // RFC 6298 算法
            $this->rttVariation = (1 - $this->beta) * $this->rttVariation +
                                  $this->beta * abs($this->smoothedRtt - $rtt);
            $this->smoothedRtt = (1 - $this->alpha) * $this->smoothedRtt + $this->alpha * $rtt;

            // 更新最小和最大 RTT
            $this->minRtt = min($this->minRtt, $rtt);
            $this->maxRtt = max($this->maxRtt, $rtt);
        }

        // 添加到历史记录
        $this->addToHistory($rtt);

        // 检测网络抖动
        $this->detectJitter($rtt);
    }

    /**
     * 计算 RTO (Retransmission Timeout)
     */
    public function calculateRto(): int
    {
        if (0.0 === $this->smoothedRtt) {
            return $this->minRto;
        }

        $rto = (int) ($this->smoothedRtt + max(1000, $this->k * $this->rttVariation));

        return max($this->minRto, min($this->maxRto, $rto));
    }

    /**
     * 添加到历史记录
     */
    private function addToHistory(int $rtt): void
    {
        $this->rttHistory[] = [
            'rtt' => $rtt,
            'timestamp' => hrtime(true),
        ];

        // 限制历史记录大小
        if (count($this->rttHistory) > $this->maxHistorySize) {
            array_shift($this->rttHistory);
        }
    }

    /**
     * 检测网络抖动
     */
    private function detectJitter(int $rtt): void
    {
        if (count($this->rttHistory) < 2) {
            return;
        }

        $previousRtt = $this->rttHistory[count($this->rttHistory) - 2]['rtt'];
        $jitter = abs($rtt - $previousRtt);

        if ($jitter > $this->jitterThreshold) {
            ++$this->stats['jitter_events'];
        }
    }

    /**
     * 获取网络抖动
     */
    public function getJitter(): float
    {
        if (count($this->rttHistory) < 2) {
            return 0.0;
        }

        $jitterSum = 0;
        $count = 0;

        for ($i = 1; $i < count($this->rttHistory); ++$i) {
            $jitter = abs($this->rttHistory[$i]['rtt'] - $this->rttHistory[$i - 1]['rtt']);
            $jitterSum += $jitter;
            ++$count;
        }

        return $count > 0 ? $jitterSum / $count : 0.0;
    }

    /**
     * 获取网络条件评估
     */
    public function getNetworkCondition(): string
    {
        if (0.0 === $this->smoothedRtt) {
            return 'unknown';
        }

        $jitter = $this->getJitter();
        $rttVariability = $this->rttVariation / $this->smoothedRtt;

        return $this->evaluateNetworkCondition($this->smoothedRtt, $jitter, $rttVariability);
    }

    /**
     * 评估网络条件
     */
    private function evaluateNetworkCondition(float $rtt, float $jitter, float $variability): string
    {
        if ($this->isExcellentCondition($rtt, $jitter, $variability)) {
            return 'excellent';
        }

        if ($this->isGoodCondition($rtt, $jitter, $variability)) {
            return 'good';
        }

        if ($this->isFairCondition($rtt, $jitter, $variability)) {
            return 'fair';
        }

        if ($this->isPoorCondition($rtt, $jitter, $variability)) {
            return 'poor';
        }

        return 'terrible';
    }

    /**
     * 检查是否为优秀网络条件
     */
    private function isExcellentCondition(float $rtt, float $jitter, float $variability): bool
    {
        return $rtt < 20000 && $jitter < 2000 && $variability < 0.1;
    }

    /**
     * 检查是否为良好网络条件
     */
    private function isGoodCondition(float $rtt, float $jitter, float $variability): bool
    {
        return $rtt < 50000 && $jitter < 5000 && $variability < 0.2;
    }

    /**
     * 检查是否为一般网络条件
     */
    private function isFairCondition(float $rtt, float $jitter, float $variability): bool
    {
        return $rtt < 100000 && $jitter < 10000 && $variability < 0.3;
    }

    /**
     * 检查是否为较差网络条件
     */
    private function isPoorCondition(float $rtt, float $jitter, float $variability): bool
    {
        return $rtt < 200000 && $jitter < 20000 && $variability < 0.5;
    }

    /**
     * 获取网络稳定性评分 (0-100)
     */
    public function getStabilityScore(): int
    {
        if (count($this->rttHistory) < 10) {
            return 50; // 默认中等稳定性
        }

        $jitter = $this->getJitter();
        $rttVariability = $this->rttVariation / $this->smoothedRtt;

        // 基于抖动和变化率计算稳定性评分
        $jitterScore = max(0, 100 - ($jitter / 1000) * 10); // 每1ms抖动扣10分
        $variabilityScore = max(0, 100 - $rttVariability * 200); // 变化率越大扣分越多

        return (int) (($jitterScore + $variabilityScore) / 2);
    }

    /**
     * 获取建议的发送窗口大小
     */
    public function getSuggestedWindowSize(int $bandwidth): int
    {
        if (0.0 === $this->smoothedRtt) {
            return 8192; // 默认窗口大小
        }

        // BDP (Bandwidth-Delay Product) 计算
        $bdp = ($bandwidth * $this->smoothedRtt) / 8000000; // 转换为包数 (假设1500字节/包)

        // 考虑网络条件调整
        $condition = $this->getNetworkCondition();
        $multiplier = match ($condition) {
            'excellent' => 1.5,
            'good' => 1.2,
            'fair' => 1.0,
            'poor' => 0.8,
            'terrible' => 0.5,
            default => 1.0,
        };

        $suggestedSize = (int) ($bdp * $multiplier);

        return max(1, min(65536, $suggestedSize));
    }

    /**
     * 重置 RTT 估算器
     */
    public function reset(): void
    {
        $this->smoothedRtt = 0.0;
        $this->rttVariation = 0.0;
        $this->currentRtt = 0;
        $this->minRtt = 0;
        $this->maxRtt = 0;
        $this->rttHistory = [];
    }

    /**
     * 获取当前 RTT
     */
    public function getCurrentRtt(): int
    {
        return $this->currentRtt;
    }

    /**
     * 获取平滑 RTT
     */
    public function getSmoothedRtt(): float
    {
        return $this->smoothedRtt;
    }

    /**
     * 获取 RTT 变化量
     */
    public function getRttVariation(): float
    {
        return $this->rttVariation;
    }

    /**
     * 获取最小 RTT
     */
    public function getMinRtt(): int
    {
        return $this->minRtt;
    }

    /**
     * 获取最大 RTT
     */
    public function getMaxRtt(): int
    {
        return $this->maxRtt;
    }

    /**
     * 获取 RTT 历史记录
     * @return array<array{rtt: int, timestamp: int}>
     */
    public function getRttHistory(): array
    {
        return $this->rttHistory;
    }

    /**
     * 设置抖动阈值
     */
    public function setJitterThreshold(int $threshold): void
    {
        $this->jitterThreshold = $threshold;
    }

    /**
     * 设置 RTO 范围
     */
    public function setRtoRange(int $minRto, int $maxRto): void
    {
        $this->minRto = $minRto;
        $this->maxRto = $maxRto;
    }

    /**
     * 获取详细统计信息
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return [
            'current_rtt_us' => $this->currentRtt,
            'smoothed_rtt_us' => $this->smoothedRtt,
            'rtt_variation_us' => $this->rttVariation,
            'min_rtt_us' => $this->minRtt,
            'max_rtt_us' => $this->maxRtt,
            'current_rto_us' => $this->calculateRto(),
            'jitter_us' => $this->getJitter(),
            'network_condition' => $this->getNetworkCondition(),
            'stability_score' => $this->getStabilityScore(),
            'history_size' => count($this->rttHistory),
            ...$this->stats,
        ];
    }

    /**
     * 重置统计信息
     */
    public function resetStats(): void
    {
        $this->stats = [
            'total_measurements' => 0,
            'rto_timeouts' => 0,
            'jitter_events' => 0,
            'network_condition_changes' => 0,
        ];
    }
}
