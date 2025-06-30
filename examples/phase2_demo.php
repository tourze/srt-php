<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Tourze\SRT\Engine\SendEngine;
use Tourze\SRT\Protocol\ControlPacket;
use Tourze\SRT\Protocol\DataPacket;
use Tourze\SRT\Protocol\HandshakeManager;

/**
 * SRT Phase 2 功能演示
 *
 * 演示 SRT 协议的核心功能：
 * 1. 握手协议
 * 2. 数据传输
 * 3. ACK/NAK 处理
 */

echo "=== SRT Phase 2 功能演示 ===\n\n";

// 1. 演示握手协议
echo "1. 握手协议演示\n";
echo "----------------\n";

// 创建 Caller 端握手管理器
$callerHandshake = new HandshakeManager(HandshakeManager::TYPE_CALLER_LISTENER);
$callerHandshake->setLatency(150); // 150ms 延迟
$callerHandshake->enableEncryption('my-secure-passphrase-123');

// 创建 Listener 端握手管理器
$listenerHandshake = new HandshakeManager(HandshakeManager::TYPE_CALLER_LISTENER);
$listenerHandshake->setLatency(120); // 120ms 延迟
$listenerHandshake->enableEncryption('my-secure-passphrase-123');

$callerSocketId = 12345;
$listenerSocketId = 67890;

try {
    // Step 1: Caller 发送 Induction
    echo "Step 1: Caller 创建 Induction 握手包\n";
    $inductionPacket = $callerHandshake->createCallerInduction($callerSocketId);
    printf("- 版本: 0x%x\n", $inductionPacket->getVersion());
    printf("- 握手类型: %d (Induction)\n", $inductionPacket->getHandshakeType());
    printf("- Socket ID: %d\n", $inductionPacket->getSrtSocketId());
    echo "\n";
    
    // Step 2: Listener 处理 Induction 并响应
    echo "Step 2: Listener 处理 Induction 握手包\n";
    $inductionResponse = $listenerHandshake->processListenerHandshake($inductionPacket);
    printf("- 响应类型: %d (Response)\n", $inductionResponse->getHandshakeType());
    printf("- Listener Socket ID: %d\n", $inductionResponse->getSrtSocketId());
    echo "\n";
    
    // Step 3: Caller 发送 Conclusion
    echo "Step 3: Caller 创建 Conclusion 握手包\n";
    $conclusionPacket = $callerHandshake->createCallerConclusion($callerSocketId, $inductionResponse);
    printf("- 握手类型: %d (Conclusion)\n", $conclusionPacket->getHandshakeType());
    printf("- 扩展字段: 0x%x (SRT Magic)\n", $conclusionPacket->getExtensionField());
    printf("- 加密字段: %d (已启用)\n", $conclusionPacket->getEncryptionField());
    
    $extensions = $conclusionPacket->getSrtExtensions();
    printf("- SRT 扩展数量: %d\n", count($extensions));
    if (isset($extensions[HandshakeManager::HS_EXT_SRT_TSBPD_DELAY])) {
        printf("- TSBPD 延迟: %d 微秒\n", $extensions[HandshakeManager::HS_EXT_SRT_TSBPD_DELAY]);
    }
    echo "\n";
    
    // Step 4: Listener 处理 Conclusion
    echo "Step 4: Listener 处理 Conclusion 握手包\n";
    $conclusionResponse = $listenerHandshake->processListenerHandshake($conclusionPacket);
    printf("- 握手完成状态: %s\n", $listenerHandshake->isCompleted() ? '是' : '否');
    echo "\n";
    
} catch (Exception $e) {
    echo "握手错误: " . $e->getMessage() . "\n\n";
}

// 2. 演示数据包处理
echo "2. 数据包处理演示\n";
echo "----------------\n";

// 创建数据包
$testData = "Hello, SRT World! 这是一条测试消息。";
echo "原始数据: $testData\n";
echo "数据长度: " . strlen($testData) . " 字节\n\n";

// 创建数据包
$dataPacket = new DataPacket(1001, 1, $testData);
$dataPacket->setDestinationSocketId($listenerSocketId);
$dataPacket->setPacketPosition(DataPacket::PP_SINGLE);

echo "数据包信息:\n";
printf("- 序列号: %d\n", $dataPacket->getSequenceNumber());
printf("- 消息号: %d\n", $dataPacket->getMessageNumber());
printf("- 时间戳: %d\n", $dataPacket->getTimestamp());
printf("- 包位置: %d (单独包)\n", $dataPacket->getPacketPosition());
printf("- 载荷长度: %d 字节\n", $dataPacket->getPayloadLength());
echo "\n";

// 序列化和反序列化
$serializedData = $dataPacket->serialize();
echo "序列化后大小: " . strlen($serializedData) . " 字节\n";

$deserializedPacket = DataPacket::deserialize($serializedData);
echo "反序列化成功: " . ($deserializedPacket->getPayload() === $testData ? '是' : '否') . "\n\n";

// 3. 演示控制包
echo "3. 控制包演示\n";
echo "-------------\n";

// 创建 ACK 包
$ackPacket = ControlPacket::createAck(1001, $callerSocketId);
echo "ACK 控制包:\n";
printf("- 类型: %s\n", $ackPacket->getTypeName());
printf("- 确认序列号: %d\n", $ackPacket->getAckSequenceNumber());
printf("- 目标 Socket ID: %d\n", $ackPacket->getDestinationSocketId());
echo "\n";

// 创建 NAK 包
$lostSequences = [1002, 1003, 1005];
$nakPacket = ControlPacket::createNak($lostSequences, $callerSocketId);
echo "NAK 控制包:\n";
printf("- 类型: %s\n", $nakPacket->getTypeName());
printf("- 丢失序列号: [%s]\n", implode(', ', $nakPacket->getNakLostSequences()));
echo "\n";

// 创建保持连接包
$keepAlivePacket = ControlPacket::createKeepAlive($callerSocketId);
echo "KeepAlive 控制包:\n";
printf("- 类型: %s\n", $keepAlivePacket->getTypeName());
printf("- 时间戳: %d\n", $keepAlivePacket->getTimestamp());
echo "\n";

// 4. 演示发送和接收引擎的统计功能
echo "4. 引擎统计演示\n";
echo "---------------\n";

// 注意：这里只演示统计功能，实际的网络传输需要真实的 UDP socket
try {
    // 创建模拟的 UDP 传输（用于演示）
    $mockTransport = new class implements \Tourze\SRT\Transport\UdpTransport {
        private array $sentData = [];
        
        public function send(string $data): int {
            $this->sentData[] = $data;
            return strlen($data);
        }
        
        public function receive(): ?string {
            return null; // 模拟实现
        }
        
        public function bind(string $host, int $port): bool {
            return true; // 模拟实现
        }
        
        public function connect(string $host, int $port): bool {
            return true; // 模拟实现
        }
        
        public function close(): void {
            // 模拟实现
        }
        
        public function getSentData(): array {
            return $this->sentData;
        }
    };
    
    $sendEngine = new SendEngine($mockTransport);
    $sendEngine->setDestinationSocketId($listenerSocketId);
    $sendEngine->setMaxPayloadSize(1000);
    
    echo "发送引擎初始统计:\n";
    $stats = $sendEngine->getStatistics();
    foreach ($stats as $key => $value) {
        printf("- %s: %s\n", $key, $value);
    }
    echo "\n";
    
    // 模拟发送一些数据
    $longMessage = str_repeat("这是一条很长的测试消息。", 50);
    echo "模拟发送长消息 (" . strlen($longMessage) . " 字节)...\n";
    
    try {
        $bytesSent = $sendEngine->send($longMessage);
        echo "发送完成，字节数: $bytesSent\n\n";
        
        echo "发送引擎更新后统计:\n";
        $newStats = $sendEngine->getStatistics();
        foreach ($newStats as $key => $value) {
            printf("- %s: %s\n", $key, $value);
        }
        echo "\n";
        
        echo "实际发送的包数量: " . count($mockTransport->getSentData()) . "\n";
        
    } catch (Exception $e) {
        echo "发送模拟中断: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "引擎演示错误: " . $e->getMessage() . "\n";
}

echo "\n=== Phase 2 演示完成 ===\n";
echo "\n已实现的核心功能:\n";
echo "✅ SRT 握手协议 (Caller-Listener)\n";
echo "✅ 加密协商和能力交换\n";
echo "✅ 数据包序列化/反序列化\n";
echo "✅ 控制包 (ACK/NAK/KeepAlive)\n";
echo "✅ 发送引擎 (分片/重传/流控)\n";
echo "✅ 接收引擎 (排序/重组/丢包检测)\n";
echo "✅ 统计信息收集\n";
echo "\n下一步: Phase 3 - 高级特性开发\n";
echo "- 流量控制算法\n";
echo "- 拥塞控制\n";
echo "- Live 模式 TSBPD\n";
echo "- 性能优化\n"; 