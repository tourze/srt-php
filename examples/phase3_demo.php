<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Tourze\SRT\Control\CongestionControl;
use Tourze\SRT\Control\FlowControl;
use Tourze\SRT\Control\RttEstimator;
use Tourze\SRT\Control\TimerManager;
use Tourze\SRT\Crypto\EncryptionManager;
use Tourze\SRT\Live\TsbpdManager;

echo "🚀 SRT-PHP Phase 3 高级特性演示\n";
echo "================================\n\n";

// 1. 加密功能演示
echo "🔐 加密功能演示:\n";
$encryptionManager = new EncryptionManager(
    EncryptionManager::ALGO_AES_256,
    'my_secret_passphrase'
);

$testData = "Hello, SRT World!";
$sequenceNumber = 12345;

echo "原始数据: {$testData}\n";

$encrypted = $encryptionManager->encryptPacket($testData, $sequenceNumber);
echo "加密后长度: " . strlen($encrypted) . " bytes\n";

$decrypted = $encryptionManager->decryptPacket($encrypted, $sequenceNumber);
echo "解密后数据: {$decrypted}\n";

$stats = $encryptionManager->getStats();
echo "加密统计: 加密包数={$stats['encrypted_packets']}, 解密包数={$stats['decrypted_packets']}\n\n";

// 2. TSBPD Live 模式演示
echo "⏰ TSBPD Live 模式演示:\n";
$tsbpd = new TsbpdManager(120); // 120ms 播放延迟

// 模拟添加数据包
$currentTime = time() * 1000000; // 微秒
$tsbpd->addPacket("Live packet 1", $currentTime, 1);
$tsbpd->addPacket("Live packet 2", $currentTime + 50000, 2); // 50ms 后
$tsbpd->addPacket("Live packet 3", $currentTime + 100000, 3); // 100ms 后

echo "队列中包数量: " . $tsbpd->getQueueSize() . "\n";
echo "播放延迟: " . $tsbpd->getPlaybackDelay() . "ms\n";

$stats = $tsbpd->getStats();
echo "TSBPD 统计: 播放延迟={$stats['playback_delay_ms']}ms, 队列大小={$stats['queue_size']}\n\n";

// 3. RTT 估算器演示
echo "📊 RTT 估算器演示:\n";
$rttEstimator = new RttEstimator();

// 模拟 RTT 测量
$rttMeasurements = [25000, 30000, 28000, 35000, 32000]; // 微秒
foreach ($rttMeasurements as $rtt) {
    $rttEstimator->updateRtt($rtt);
}

echo "当前 RTT: " . ($rttEstimator->getCurrentRtt() / 1000) . "ms\n";
echo "平滑 RTT: " . round($rttEstimator->getSmoothedRtt() / 1000, 2) . "ms\n";
echo "网络条件: " . $rttEstimator->getNetworkCondition() . "\n";
echo "稳定性评分: " . $rttEstimator->getStabilityScore() . "/100\n";
echo "建议窗口大小: " . $rttEstimator->getSuggestedWindowSize(1000000) . " 包\n\n";

// 4. 拥塞控制演示
echo "🚦 拥塞控制演示:\n";
$congestionControl = new CongestionControl();

// 模拟网络事件
$congestionControl->updateRtt(30000); // 30ms RTT
$congestionControl->onPacketSent();
$congestionControl->onPacketAcked();

echo "发送速率: " . round($congestionControl->getSendingRate() / 1000000, 2) . " MB/s\n";
echo "拥塞窗口: " . round($congestionControl->getCongestionWindow(), 2) . " 包\n";
echo "网络状况: " . $congestionControl->getNetworkCondition() . "\n";
echo "是否慢启动: " . ($congestionControl->isInSlowStart() ? '是' : '否') . "\n\n";

// 1. 流量控制演示
echo "1. 流量控制演示\n";
echo "================\n";

$flowControl = new FlowControl(
    sendWindowSize: 1024,      // 1024包的发送窗口
    receiveWindowSize: 1024,   // 1024包的接收窗口
    maxSendRate: 5000000       // 5MB/s最大发送速率
);

echo "初始状态:\n";
$stats = $flowControl->getStats();
printf("- 发送窗口大小: %d\n", $stats['send_window_size']);
printf("- 当前发送速率: %d bytes/s (%.2f MB/s)\n", 
    $stats['current_send_rate'], 
    $stats['current_send_rate'] / 1024 / 1024
);
printf("- 窗口利用率: %.2f%%\n", $stats['window_utilization'] * 100);
printf("- 令牌桶利用率: %.2f%%\n", $stats['token_bucket_utilization'] * 100);

echo "\n模拟数据包发送:\n";
for ($i = 0; $i < 10; $i++) {
    $packetSize = 1500; // 标准以太网MTU
    
    if ($flowControl->canSend($packetSize)) {
        $flowControl->onPacketSent($packetSize);
        echo "✓ 包 #{$i} 发送成功 ({$packetSize} bytes)\n";
    } else {
        echo "✗ 包 #{$i} 被流量控制阻止\n";
    }
    
    // 模拟一些包的确认
    if ($i % 3 === 0 && $i > 0) {
        $flowControl->onPacketAcked(2);
        echo "  → 收到2个包的ACK确认\n";
    }
    
    usleep(1000); // 1ms延迟
}

echo "\n发送后统计:\n";
$stats = $flowControl->getStats();
printf("- 已发送包数: %d\n", $stats['packets_sent']);
printf("- 已发送字节: %d\n", $stats['bytes_sent']);
printf("- 窗口满次数: %d\n", $stats['window_full_count']);
printf("- 速率限制次数: %d\n", $stats['rate_limited_count']);

// 2. 拥塞控制演示
echo "\n\n2. 拥塞控制演示\n";
echo "================\n";

$congestionControl = new CongestionControl(
    initialRate: 1000000,  // 1MB/s初始速率
    maxRate: 10000000,     // 10MB/s最大速率
    minRate: 100000        // 100KB/s最小速率
);

echo "初始状态:\n";
$stats = $congestionControl->getStats();
printf("- 发送速率: %d bytes/s (%.2f MB/s)\n", 
    $stats['sending_rate'], 
    $stats['sending_rate'] / 1024 / 1024
);
printf("- 拥塞窗口: %.2f\n", $stats['congestion_window']);
printf("- 慢启动阶段: %s\n", $stats['in_slow_start'] ? '是' : '否');
printf("- 网络状况: %s\n", $stats['network_condition']);

echo "\n模拟网络传输:\n";

// 模拟正常传输
for ($i = 0; $i < 5; $i++) {
    $congestionControl->onPacketSent();
    $rtt = 50000 + rand(-10000, 10000); // 50ms ± 10ms RTT
    $congestionControl->updateRtt($rtt);
    $congestionControl->onPacketAcked();
    
    printf("包 #%d: RTT=%.1fms, 速率=%.2fMB/s, 窗口=%.2f\n",
        $i + 1,
        $rtt / 1000,
        $congestionControl->getSendingRate() / 1024 / 1024,
        $congestionControl->getCongestionWindow()
    );
}

// 模拟丢包事件
echo "\n模拟网络拥塞 (丢包):\n";
$congestionControl->onPacketLost(2);
$stats = $congestionControl->getStats();
printf("丢包后: 速率=%.2fMB/s, 窗口=%.2f, 慢启动=%s\n",
    $stats['sending_rate'] / 1024 / 1024,
    $stats['congestion_window'],
    $stats['in_slow_start'] ? '是' : '否'
);

// 模拟网络恢复
echo "\n模拟网络恢复:\n";
for ($i = 0; $i < 3; $i++) {
    $congestionControl->onPacketSent();
    $rtt = 45000 + rand(-5000, 5000); // 更好的RTT
    $congestionControl->updateRtt($rtt);
    $congestionControl->onPacketAcked();
    
    printf("恢复包 #%d: RTT=%.1fms, 速率=%.2fMB/s\n",
        $i + 1,
        $rtt / 1000,
        $congestionControl->getSendingRate() / 1024 / 1024
    );
}

echo "\n最终拥塞控制统计:\n";
$stats = $congestionControl->getStats();
printf("- 速率增加次数: %d\n", $stats['rate_increases']);
printf("- 速率减少次数: %d\n", $stats['rate_decreases']);
printf("- 拥塞事件次数: %d\n", $stats['congestion_events']);
printf("- 丢包率: %.2f%%\n", $stats['loss_rate'] * 100);
printf("- RTO: %.1fms\n", $stats['rto'] / 1000);

// 3. 定时器管理演示
echo "\n\n3. 定时器管理演示\n";
echo "================\n";

$timerManager = new TimerManager();

// 设置定时器回调
$timerManager->setCallback(TimerManager::TIMER_RETRANSMISSION, function($id, $type, $data) {
    echo "🔄 重传定时器触发: {$id}\n";
});

$timerManager->setCallback(TimerManager::TIMER_KEEPALIVE, function($id, $type, $data) {
    echo "💓 保活定时器触发: {$id}\n";
});

$timerManager->setCallback(TimerManager::TIMER_ACK, function($id, $type, $data) {
    echo "✅ ACK定时器触发: {$id}, 序列号: {$data['sequence_number']}\n";
});

$timerManager->setCallback(TimerManager::TIMER_NAK, function($id, $type, $data) {
    echo "❌ NAK定时器触发: {$id}, 丢失序列号: " . implode(',', $data['lost_sequences']) . "\n";
});

echo "设置各种定时器:\n";

// 设置重传定时器
$timerManager->setRetransmissionTimer('packet_001', 100000, ['data' => 'test_packet']);
echo "- 设置重传定时器 (100ms)\n";

// 设置保活定时器
$timerManager->setKeepaliveTimer(200000);
echo "- 设置保活定时器 (200ms)\n";

// 设置ACK定时器
$timerManager->setAckTimer(12345, 50000);
echo "- 设置ACK定时器 (50ms)\n";

// 设置NAK定时器
$timerManager->setNakTimer([100, 101, 102], 75000);
echo "- 设置NAK定时器 (75ms)\n";

echo "\n当前活跃定时器:\n";
$stats = $timerManager->getStats();
printf("- 总活跃定时器: %d\n", $stats['active_timers']);
printf("- 重传定时器: %d\n", $stats['active_retransmission_timers']);
printf("- 保活定时器: %d\n", $stats['active_keepalive_timers']);
printf("- ACK定时器: %d\n", $stats['active_ack_timers']);
printf("- NAK定时器: %d\n", $stats['active_nak_timers']);

if ($stats['time_to_next_expire'] !== null) {
    printf("- 下次过期时间: %.1fms\n", $stats['time_to_next_expire'] / 1000);
}

echo "\n等待定时器触发...\n";

// 模拟时间流逝，处理定时器
$startTime = microtime(true);
while (microtime(true) - $startTime < 0.3) { // 运行300ms
    $expiredTimers = $timerManager->processTick();
    
    if (!empty($expiredTimers)) {
        foreach ($expiredTimers as $timer) {
            // 定时器回调已经在processTick中执行
        }
    }
    
    usleep(10000); // 10ms检查间隔
}

echo "\n最终定时器统计:\n";
$stats = $timerManager->getStats();
printf("- 创建的定时器: %d\n", $stats['timers_created']);
printf("- 过期的定时器: %d\n", $stats['timers_expired']);
printf("- 取消的定时器: %d\n", $stats['timers_cancelled']);
printf("- 重传次数: %d\n", $stats['retransmissions']);
printf("- 保活次数: %d\n", $stats['keepalives_sent']);
printf("- ACK次数: %d\n", $stats['acks_sent']);

// 4. 综合演示
echo "\n\n4. 综合控制演示\n";
echo "================\n";

echo "模拟一个完整的SRT传输场景...\n";

// 重置所有组件
$flowControl->resetStats();
$congestionControl->resetStats();
$timerManager->resetStats();
$timerManager->clearAllTimers();

// 设置定时器回调来处理重传
$timerManager->setCallback(TimerManager::TIMER_RETRANSMISSION, function($id, $type, $data) use ($congestionControl) {
    echo "📦 重传包: {$id}\n";
    $congestionControl->onPacketLost(1);
});

echo "\n开始传输模拟:\n";
$packetId = 1;

for ($round = 0; $round < 5; $round++) {
    echo "\n--- 传输轮次 " . ($round + 1) . " ---\n";
    
    // 尝试发送多个包
    for ($i = 0; $i < 3; $i++) {
        $packetSize = 1500;
        
        if ($flowControl->canSend($packetSize)) {
            // 流量控制允许发送
            $flowControl->onPacketSent($packetSize);
            $congestionControl->onPacketSent();
            
            // 设置重传定时器
            $rto = $congestionControl->calculateRto();
            $timerManager->setRetransmissionTimer("pkt_{$packetId}", $rto);
            
            echo "📤 发送包 #{$packetId} (RTO: " . ($rto/1000) . "ms)\n";
            
            // 模拟网络延迟和可能的丢包
            if (rand(1, 10) <= 8) { // 80%成功率
                // 包成功到达，模拟ACK
                $rtt = 30000 + rand(-10000, 10000); // 30ms ± 10ms
                $congestionControl->updateRtt($rtt);
                $congestionControl->onPacketAcked();
                $flowControl->onPacketAcked(1);
                
                // 取消重传定时器
                $timerManager->cancelRetransmissionTimer("pkt_{$packetId}");
                
                echo "  ✅ 收到ACK (RTT: " . ($rtt/1000) . "ms)\n";
            } else {
                echo "  ❌ 包丢失，等待重传定时器\n";
            }
            
            $packetId++;
        } else {
            echo "🚫 流量控制阻止发送\n";
        }
        
        usleep(5000); // 5ms间隔
    }
    
    // 处理定时器
    $timerManager->processTick();
    
    // 显示当前状态
    $flowStats = $flowControl->getStats();
    $congStats = $congestionControl->getStats();
    $timerStats = $timerManager->getStats();
    
    printf("状态: 速率=%.1fMB/s, 窗口利用率=%.1f%%, 活跃定时器=%d, 丢包率=%.1f%%\n",
        $congStats['sending_rate'] / 1024 / 1024,
        $flowStats['window_utilization'] * 100,
        $timerStats['active_timers'],
        $congStats['loss_rate'] * 100
    );
    
    usleep(20000); // 20ms轮次间隔
}

echo "\n=== Phase 3 演示完成 ===\n";
echo "\n实现的高级特性:\n";
echo "✅ 流量控制 - 窗口管理和速率限制\n";
echo "✅ 拥塞控制 - AIMD算法和RTT估算\n";
echo "✅ 定时器管理 - 重传、保活、ACK/NAK定时器\n";
echo "✅ 统计监控 - 详细的性能和状态统计\n";
echo "✅ 自适应调节 - 基于网络状况的动态调整\n";

echo "✅ Phase 3 高级特性演示完成!\n";
echo "包含功能: 加密安全、Live 模式 TSBPD、RTT 估算、拥塞控制\n"; 