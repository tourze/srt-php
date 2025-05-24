<?php

declare(strict_types=1);

namespace Tourze\SRT\Control;

/**
 * SRT 拥塞控制管理器
 * 
 * 实现基于丢包率和RTT的拥塞控制算法
 * 支持AIMD (Additive Increase Multiplicative Decrease) 算法
 */
class CongestionControl
{
    /**
     * 当前发送速率 (bytes/second)
     */
    private int $sendingRate;
    
    /**
     * 最大发送速率 (bytes/second)
     */
    private int $maxSendingRate;
    
    /**
     * 最小发送速率 (bytes/second)
     */
    private int $minSendingRate;
    
    /**
     * 当前RTT (微秒)
     */
    private int $currentRtt = 0;
    
    /**
     * 平滑RTT (微秒)
     */
    private float $smoothedRtt = 0.0;
    
    /**
     * RTT变化量 (微秒)
     */
    private float $rttVariation = 0.0;
    
    /**
     * 丢包率 (0.0 - 1.0)
     */
    private float $lossRate = 0.0;
    
    /**
     * 丢包率阈值
     */
    private float $lossThreshold = 0.05; // 5%
    
    /**
     * 拥塞窗口大小
     */
    private float $congestionWindow;
    
    /**
     * 慢启动阈值
     */
    private float $slowStartThreshold;
    
    /**
     * 是否处于慢启动阶段
     */
    private bool $inSlowStart = true;
    
    /**
     * 加性增加步长 (bytes/RTT)
     */
    private int $additiveIncrease = 1000;
    
    /**
     * 乘性减少因子
     */
    private float $multiplicativeDecrease = 0.875;
    
    /**
     * RTT测量历史
     */
    private array $rttHistory = [];
    
    /**
     * 丢包统计
     */
    private array $lossStats = [
        'total_packets' => 0,
        'lost_packets' => 0,
        'last_loss_time' => 0,
    ];
    
    /**
     * 拥塞控制统计
     */
    private array $stats = [
        'rate_increases' => 0,
        'rate_decreases' => 0,
        'slow_start_exits' => 0,
        'congestion_events' => 0,
    ];

    public function __construct(
        int $initialRate = 1000000, // 1MB/s
        int $maxRate = 100000000,   // 100MB/s
        int $minRate = 10000        // 10KB/s
    ) {
        $this->sendingRate = $initialRate;
        $this->maxSendingRate = $maxRate;
        $this->minSendingRate = $minRate;
        
        // 初始化拥塞窗口 (以包为单位)
        $this->congestionWindow = 2.0;
        $this->slowStartThreshold = 65536.0; // 64KB
    }

    /**
     * 更新RTT测量
     */
    public function updateRtt(int $rtt): void
    {
        $this->currentRtt = $rtt;
        
        // 平滑RTT计算 (RFC 6298)
        if ($this->smoothedRtt == 0.0) {
            $this->smoothedRtt = $rtt;
            $this->rttVariation = $rtt / 2.0;
        } else {
            $alpha = 0.125; // 1/8
            $beta = 0.25;   // 1/4
            
            $this->rttVariation = (1 - $beta) * $this->rttVariation + 
                                  $beta * abs($this->smoothedRtt - $rtt);
            $this->smoothedRtt = (1 - $alpha) * $this->smoothedRtt + $alpha * $rtt;
        }
        
        // 保存RTT历史
        $this->rttHistory[] = $rtt;
        if (count($this->rttHistory) > 100) {
            array_shift($this->rttHistory);
        }
    }

    /**
     * 报告数据包发送
     */
    public function onPacketSent(): void
    {
        $this->lossStats['total_packets']++;
    }

    /**
     * 报告数据包丢失
     */
    public function onPacketLost(int $count = 1): void
    {
        $this->lossStats['lost_packets'] += $count;
        $this->lossStats['last_loss_time'] = microtime(true);
        
        // 更新丢包率
        $this->updateLossRate();
        
        // 触发拥塞控制
        $this->onCongestionEvent();
    }

    /**
     * 报告数据包确认
     */
    public function onPacketAcked(): void
    {
        // 在拥塞避免阶段增加发送速率
        if (!$this->inSlowStart) {
            $this->additiveIncrease();
        } else {
            $this->slowStartIncrease();
        }
    }

    /**
     * 更新丢包率
     */
    private function updateLossRate(): void
    {
        if ($this->lossStats['total_packets'] > 0) {
            $this->lossRate = $this->lossStats['lost_packets'] / $this->lossStats['total_packets'];
        }
    }

    /**
     * 拥塞事件处理
     */
    private function onCongestionEvent(): void
    {
        $this->stats['congestion_events']++;
        
        // 退出慢启动
        if ($this->inSlowStart) {
            $this->inSlowStart = false;
            $this->slowStartThreshold = $this->congestionWindow / 2.0;
            $this->stats['slow_start_exits']++;
        }
        
        // 乘性减少
        $this->multiplicativeDecrease();
    }

    /**
     * 慢启动阶段的速率增加
     */
    private function slowStartIncrease(): void
    {
        $this->congestionWindow += 1.0;
        
        // 检查是否应该退出慢启动
        if ($this->congestionWindow >= $this->slowStartThreshold) {
            $this->inSlowStart = false;
        }
        
        $this->updateSendingRate();
        $this->stats['rate_increases']++;
    }

    /**
     * 加性增加
     */
    private function additiveIncrease(): void
    {
        // 每个RTT增加一个MSS
        $this->congestionWindow += 1.0 / $this->congestionWindow;
        $this->updateSendingRate();
        $this->stats['rate_increases']++;
    }

    /**
     * 乘性减少
     */
    private function multiplicativeDecrease(): void
    {
        $this->congestionWindow *= $this->multiplicativeDecrease;
        $this->congestionWindow = max(1.0, $this->congestionWindow);
        $this->updateSendingRate();
        $this->stats['rate_decreases']++;
    }

    /**
     * 根据拥塞窗口更新发送速率
     */
    private function updateSendingRate(): void
    {
        // 基于拥塞窗口和RTT计算发送速率
        $rtt = max(1000, $this->smoothedRtt); // 最小1ms RTT
        $mss = 1500; // 最大段大小 (bytes)
        
        $newRate = (int)(($this->congestionWindow * $mss * 1000000) / $rtt);
        $this->sendingRate = max($this->minSendingRate, min($this->maxSendingRate, $newRate));
    }

    /**
     * 获取当前发送速率
     */
    public function getSendingRate(): int
    {
        return $this->sendingRate;
    }

    /**
     * 获取当前RTT
     */
    public function getCurrentRtt(): int
    {
        return $this->currentRtt;
    }

    /**
     * 获取平滑RTT
     */
    public function getSmoothedRtt(): float
    {
        return $this->smoothedRtt;
    }

    /**
     * 获取RTT变化量
     */
    public function getRttVariation(): float
    {
        return $this->rttVariation;
    }

    /**
     * 获取当前丢包率
     */
    public function getLossRate(): float
    {
        return $this->lossRate;
    }

    /**
     * 获取拥塞窗口大小
     */
    public function getCongestionWindow(): float
    {
        return $this->congestionWindow;
    }

    /**
     * 是否处于慢启动阶段
     */
    public function isInSlowStart(): bool
    {
        return $this->inSlowStart;
    }

    /**
     * 计算重传超时时间 (RTO)
     */
    public function calculateRto(): int
    {
        // RFC 6298 RTO计算
        $rto = $this->smoothedRtt + max(1000, 4 * $this->rttVariation);
        return (int)max(1000, min(60000000, $rto)); // 1ms - 60s
    }

    /**
     * 获取网络状况评估
     */
    public function getNetworkCondition(): string
    {
        if ($this->lossRate > 0.1) {
            return 'poor';
        } elseif ($this->lossRate > 0.05) {
            return 'fair';
        } elseif ($this->lossRate > 0.01) {
            return 'good';
        } else {
            return 'excellent';
        }
    }

    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        return array_merge($this->stats, [
            'sending_rate' => $this->sendingRate,
            'current_rtt' => $this->currentRtt,
            'smoothed_rtt' => $this->smoothedRtt,
            'rtt_variation' => $this->rttVariation,
            'loss_rate' => $this->lossRate,
            'congestion_window' => $this->congestionWindow,
            'slow_start_threshold' => $this->slowStartThreshold,
            'in_slow_start' => $this->inSlowStart,
            'network_condition' => $this->getNetworkCondition(),
            'rto' => $this->calculateRto(),
            'total_packets' => $this->lossStats['total_packets'],
            'lost_packets' => $this->lossStats['lost_packets'],
        ]);
    }

    /**
     * 重置统计信息
     */
    public function resetStats(): void
    {
        $this->stats = [
            'rate_increases' => 0,
            'rate_decreases' => 0,
            'slow_start_exits' => 0,
            'congestion_events' => 0,
        ];
        
        $this->lossStats = [
            'total_packets' => 0,
            'lost_packets' => 0,
            'last_loss_time' => 0,
        ];
        
        $this->lossRate = 0.0;
        $this->rttHistory = [];
    }
} 