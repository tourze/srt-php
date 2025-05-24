<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Tourze\SRT\Protocol\PacketHeader;
use Tourze\SRT\Transport\UdpTransport;

echo "🚀 SRT-PHP Phase 1 基础功能演示\n";
echo "================================\n\n";

// 1. 演示 SRT 包头处理
echo "📦 1. SRT 包头处理演示\n";
echo "----------------------\n";

// 创建数据包头部
$dataHeader = PacketHeader::createDataPacket(
    sequenceNumber: 12345,
    packetPosition: PacketHeader::PACKET_POSITION_ONLY,
    isOrdered: true,
    encryptionFlags: PacketHeader::ENCRYPTION_ODD_KEY,
    isRetransmitted: false,
    messageNumber: 6789,
    timestamp: time(),
    destinationSocketId: 999
);

echo "✅ 创建数据包头部:\n";
echo "   - 序列号: {$dataHeader->getSequenceNumber()}\n";
echo "   - 消息号: {$dataHeader->getMessageNumber()}\n";
echo "   - 时间戳: {$dataHeader->getTimestamp()}\n";
echo "   - 目标ID: {$dataHeader->getDestinationSocketId()}\n";

// 序列化和反序列化
$binaryData = $dataHeader->toBinary();
$parsedHeader = PacketHeader::fromBinary($binaryData);

echo "✅ 包头序列化/反序列化成功 (16 字节)\n";
echo "   - 原始包头和解析包头一致: " . ($dataHeader->getSequenceNumber() === $parsedHeader->getSequenceNumber() ? '✅' : '❌') . "\n\n";

// 创建控制包头部
$controlHeader = PacketHeader::createControlPacket(
    controlType: PacketHeader::CONTROL_ACK,
    subtype: 0,
    typeSpecificInfo: 54321,
    timestamp: time(),
    destinationSocketId: 888
);

echo "✅ 创建控制包头部 (ACK):\n";
echo "   - 控制类型: {$controlHeader->getControlType()}\n";
echo "   - 子类型: {$controlHeader->getSubtype()}\n";
echo "   - 特定信息: {$controlHeader->getTypeSpecificInfo()}\n\n";

// 2. 演示 UDP 传输层
echo "🌐 2. UDP 传输层演示\n";
echo "-------------------\n";

try {
    // 创建服务端
    $server = new UdpTransport();
    $server->bind('127.0.0.1', 0);
    $serverAddr = $server->getLocalAddress();
    
    echo "✅ 服务端绑定成功: {$serverAddr['host']}:{$serverAddr['port']}\n";
    
    // 创建客户端
    $client = new UdpTransport();
    $client->connect($serverAddr['host'], $serverAddr['port']);
    
    echo "✅ 客户端连接成功\n";
    
    // 发送测试数据
    $testMessage = "Hello SRT! 这是一个测试消息。";
    $bytesSent = $client->send($testMessage);
    
    echo "✅ 发送数据: {$bytesSent} 字节\n";
    
    // 接收数据
    $receivedData = $server->receive(1024);
    
    if ($receivedData !== null) {
        echo "✅ 接收数据: {$receivedData['data']}\n";
        echo "   - 来源: {$receivedData['from']['host']}:{$receivedData['from']['port']}\n";
    }
    
    // 显示统计信息
    $clientStats = $client->getStatistics();
    $serverStats = $server->getStatistics();
    
    echo "\n📊 传输统计:\n";
    echo "   客户端 - 发送: {$clientStats['bytes_sent']} 字节, {$clientStats['packets_sent']} 包\n";
    echo "   服务端 - 接收: {$serverStats['bytes_received']} 字节, {$serverStats['packets_received']} 包\n";
    
    // 清理资源
    $client->close();
    $server->close();
    
    echo "✅ 连接已关闭\n\n";
    
} catch (Exception $e) {
    echo "❌ 传输层错误: {$e->getMessage()}\n\n";
}

// 3. 演示 SRT 包的完整流程
echo "🔄 3. SRT 包完整流程演示\n";
echo "------------------------\n";

try {
    // 创建一个完整的 SRT 数据包
    $payload = "SRT 协议测试数据";
    $srtHeader = PacketHeader::createDataPacket(
        sequenceNumber: 1,
        packetPosition: PacketHeader::PACKET_POSITION_ONLY,
        isOrdered: true,
        encryptionFlags: PacketHeader::ENCRYPTION_NONE,
        isRetransmitted: false,
        messageNumber: 1,
        timestamp: (int)(hrtime(true) / 1000000), // 毫秒时间戳
        destinationSocketId: 12345
    );
    
    // 构建完整包 (头部 + 载荷)
    $fullPacket = $srtHeader->toBinary() . $payload;
    
    echo "✅ 构建完整 SRT 包:\n";
    echo "   - 头部长度: 16 字节\n";
    echo "   - 载荷长度: " . strlen($payload) . " 字节\n";
    echo "   - 总长度: " . strlen($fullPacket) . " 字节\n";
    
    // 解析包
    $headerData = substr($fullPacket, 0, 16);
    $payloadData = substr($fullPacket, 16);
    
    $parsedHeader = PacketHeader::fromBinary($headerData);
    
    echo "✅ 解析 SRT 包成功:\n";
    echo "   - 序列号: {$parsedHeader->getSequenceNumber()}\n";
    echo "   - 载荷: {$payloadData}\n";
    echo "   - 包类型: " . ($parsedHeader->isControlPacket() ? '控制包' : '数据包') . "\n\n";
    
} catch (Exception $e) {
    echo "❌ 包处理错误: {$e->getMessage()}\n\n";
}

echo "🎉 Phase 1 基础功能演示完成！\n";
echo "==============================\n";
echo "已实现功能:\n";
echo "✅ SRT 包头解析和构建\n";
echo "✅ UDP 传输层封装\n";
echo "✅ 错误处理机制\n";
echo "✅ 统计信息收集\n";
echo "✅ 完整的单元测试覆盖\n\n";
echo "下一步: Phase 2 - 协议核心实现 (握手协议)\n"; 