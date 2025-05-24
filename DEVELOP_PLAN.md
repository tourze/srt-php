# SRT-PHP 开发计划

🚀 **项目概述**

`tourze/srt-php` 是一个纯 PHP 实现的 SRT（Secure Reliable Transport）协议库，旨在为 PHP 开发者提供低延迟、高可靠性的实时数据传输能力。

## 📋 目录

- [SRT 协议详细介绍](#-srt-协议详细介绍)
- [技术架构设计](#-技术架构设计)
- [开发阶段规划](#-开发阶段规划)
- [实现细节](#-实现细节)
- [测试策略](#-测试策略)
- [性能优化](#-性能优化)
- [部署与集成](#-部署与集成)

---

## 🎯 SRT 协议详细介绍

### 协议概述

**SRT (Secure Reliable Transport)** 是一个基于 UDP 的应用层协议，专为低延迟视频和音频流传输设计。

#### 核心特性

- ✅ **安全性**: AES-128/192/256 加密
- ✅ **可靠性**: 自动重传机制 (ARQ)
- ✅ **低延迟**: 毫秒级延迟控制
- ✅ **自适应**: 动态网络条件适应
- ✅ **内容无关**: 支持任意数据类型

### 协议架构

#### 数据包结构

```
UDP Header + SRT Header + Payload
```

**SRT 数据包头部结构** (16 bytes):

```
 0                   1                   2                   3
 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|F|           Packet Sequence Number / Control Type             |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|PP|O|KK|R|                Message Number                       |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|                           Timestamp                           |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|                     Destination Socket ID                     |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
```

#### 字段说明

- **F (1 bit)**: 包类型标志 (0=数据包, 1=控制包)
- **PP (2 bits)**: 包位置标志 (首包、中间包、末包)
- **O (1 bit)**: 有序标志
- **KK (2 bits)**: 加密密钥标志
- **R (1 bit)**: 重传包标志

### 控制包类型

| 类型 | 代码 | 用途 |
|------|------|------|
| HANDSHAKE | 0x0000 | 握手协商 |
| KEEPALIVE | 0x0001 | 保持连接 |
| ACK | 0x0002 | 确认应答 |
| NAK | 0x0003 | 丢包报告 |
| SHUTDOWN | 0x0005 | 连接关闭 |
| ACKACK | 0x0006 | ACK确认 |

### 传输模式

#### 1. Live Mode (直播模式)

- 🎯 **用途**: 实时音视频流传输
- ⏰ **延迟**: <120ms (默认)
- 🔧 **特性**: TSBPD + 丢包丢弃

#### 2. Message Mode (消息模式)

- 🎯 **用途**: 完整消息传输
- 📦 **特性**: 消息边界保护
- 🔧 **可靠性**: 完整性保证

#### 3. Buffer Mode (缓冲模式)

- 🎯 **用途**: 连续数据流
- 📊 **特性**: 类似 TCP 流式传输
- 🔧 **应用**: 文件传输

---

## 🏗 技术架构设计

### 核心组件架构

```ascii
┌─────────────────────────────────────────────────────────────┐
│                     SRT Library API                        │
├─────────────────────────────────────────────────────────────┤
│  Socket Management  │  Protocol Engine  │  Crypto Engine   │
├─────────────────────────────────────────────────────────────┤
│     Packet Parser   │   Flow Control    │   Congestion     │
│                     │                   │     Control      │
├─────────────────────────────────────────────────────────────┤
│        Buffer Management        │       Timer Management    │
├─────────────────────────────────────────────────────────────┤
│                      UDP Transport                          │
└─────────────────────────────────────────────────────────────┘
```

### 包结构设计

```
src/
├── Socket/
│   ├── SrtSocket.php           🔌 主要 Socket 接口
│   ├── SocketManager.php       📊 Socket 管理器
│   └── SocketState.php         📈 连接状态管理
├── Protocol/
│   ├── PacketHeader.php        📦 包头处理
│   ├── DataPacket.php          📨 数据包
│   ├── ControlPacket.php       🎛️ 控制包
│   └── HandshakeManager.php    🤝 握手管理
├── Transport/
│   ├── UdpTransport.php        🚚 UDP 传输层
│   └── NetworkManager.php      🌐 网络管理
├── Crypto/
│   ├── EncryptionManager.php   🔐 加密管理
│   ├── KeyManager.php          🔑 密钥管理
│   └── AesCrypto.php           🛡️ AES 加密
├── Buffer/
│   ├── SendBuffer.php          📤 发送缓冲区
│   ├── ReceiveBuffer.php       📥 接收缓冲区
│   └── PacketQueue.php         📋 包队列
├── Control/
│   ├── FlowControl.php         🌊 流量控制
│   ├── CongestionControl.php   🚦 拥塞控制
│   └── TimerManager.php        ⏰ 定时器管理
└── Utils/
    ├── Logger.php              📝 日志工具
    ├── Statistics.php          📊 统计信息
    └── ConfigManager.php       ⚙️ 配置管理
```

---

## 📅 开发阶段规划

### Phase 1: 基础框架 (4-6 周) ✅

#### Week 1-2: 项目基础设施 ✅

- [x] 🏗️ 项目结构搭建 ✅
- [x] 📝 基础类和接口定义 ✅
- [x] 🧪 单元测试框架搭建 ✅
- [x] 📚 文档框架建立 ✅
- [ ] 🔧 CI/CD 流水线配置

#### Week 3-4: UDP 传输层 ✅

- [x] 🚚 UDP Socket 包装类 ✅
- [x] 🌐 网络 I/O 管理 ✅
- [x] 📦 基础包解析器 ✅
- [x] 🔍 错误处理机制 ✅
- [x] 🧪 传输层单元测试 ✅

#### Week 5-6: 包结构实现 ✅

- [x] 📨 SRT 数据包结构 ✅
- [x] 🎛️ 控制包实现 ✅
- [x] 🔍 包验证机制 ✅
- [x] 📊 包序列号管理 ✅
- [x] 🧪 包处理测试 ✅

### Phase 2: 协议核心 (6-8 周)

#### Week 7-10: 握手协议

- [ ] 🤝 Caller-Listener 握手
- [ ] 🔄 Rendezvous 握手
- [ ] 🔐 加密协商
- [ ] 📋 能力交换
- [ ] 🧪 握手流程测试

#### Week 11-14: 数据传输

- [ ] 📤 发送引擎实现
- [ ] 📥 接收引擎实现
- [ ] 🔄 重传机制
- [ ] 📦 包确认 (ACK/NAK)
- [ ] 🧪 传输可靠性测试

### Phase 3: 高级特性 (8-10 周)

#### Week 15-18: 流量控制

- [ ] 🌊 发送速率控制
- [ ] 📊 窗口管理
- [ ] 🚦 拥塞控制算法
- [ ] ⏰ RTT 估算
- [ ] 🧪 性能优化测试

#### Week 19-22: 加密安全

- [ ] 🔐 AES 加密实现
- [ ] 🔑 密钥交换协议
- [ ] 🛡️ 密钥更新机制
- [ ] 🔒 安全性验证
- [ ] 🧪 安全性测试

#### Week 23-24: Live 模式特性

- [ ] ⏰ TSBPD 实现
- [ ] 🗑️ 延迟包丢弃
- [ ] 📈 时钟漂移管理
- [ ] 📊 延迟统计
- [ ] 🧪 实时性测试

### Phase 4: 性能优化 (4-6 周)

#### Week 25-28: 性能调优

- [ ] 🚀 内存优化
- [ ] ⚡ CPU 性能优化
- [ ] 📊 性能监控
- [ ] 🔧 配置调优
- [ ] 🧪 性能基准测试

#### Week 29-30: 兼容性测试

- [ ] 🔄 与官方实现互操作
- [ ] 🌐 多平台兼容性
- [ ] 📱 不同 PHP 版本支持
- [ ] 🧪 兼容性测试套件

### Phase 5: 生产就绪 (3-4 周)

#### Week 31-33: 生产化

- [ ] 📚 完整文档编写
- [ ] 🎯 示例应用开发
- [ ] 🔧 部署指南
- [ ] 📊 监控集成
- [ ] 🛡️ 安全审计

#### Week 34: 发布准备

- [ ] 🚀 版本发布流程
- [ ] 📦 包管理配置
- [ ] 🎉 社区推广
- [ ] 📞 技术支持准备

---

## 🔧 实现细节

### 核心类设计

#### SRT Socket 主类

```php
class SrtSocket
{
    // 连接管理
    public function connect(string $host, int $port): bool;
    public function bind(string $host, int $port): bool;
    public function listen(int $backlog = 5): bool;
    public function accept(): ?SrtSocket;
    
    // 数据传输
    public function send(string $data): int;
    public function recv(int $length): string;
    
    // 配置管理
    public function setOption(string $option, $value): bool;
    public function getOption(string $option);
    
    // 状态查询
    public function getStats(): array;
    public function isConnected(): bool;
}
```

#### 包处理器

```php
class PacketProcessor
{
    public function parsePacket(string $data): Packet;
    public function buildDataPacket(string $payload): DataPacket;
    public function buildControlPacket(int $type, array $data): ControlPacket;
    public function validatePacket(Packet $packet): bool;
}
```

### 关键算法实现

#### 1. 拥塞控制算法

```php
class CongestionControl
{
    private float $sendingRate;
    private float $rtt;
    private int $lossRate;
    
    public function updateRate(): void
    {
        // 基于 AIMD 算法的实现
        if ($this->lossRate > $this->threshold) {
            $this->sendingRate *= 0.875; // 乘性减少
        } else {
            $this->sendingRate += $this->increment; // 加性增加
        }
    }
}
```

#### 2. 时间戳同步

```php
class TimestampManager
{
    private int $baseTime;
    private float $driftRate = 0.0;
    
    public function getTimestamp(): int
    {
        $now = hrtime(true) / 1000; // 微秒
        return $now - $this->baseTime + $this->driftCorrection();
    }
    
    private function driftCorrection(): float
    {
        // 时钟漂移补偿算法
        return $this->driftRate * $this->getElapsedTime();
    }
}
```

---

## 🧪 测试策略

### 测试分层

#### 1. 单元测试 📋

- **覆盖率目标**: >90%
- **工具**: PHPUnit
- **重点**: 协议解析、加密解密、状态管理

#### 2. 集成测试 🔄

- **场景**: 端到端连接测试
- **工具**: 自定义测试框架
- **重点**: 握手流程、数据传输、错误恢复

#### 3. 性能测试 🚀

- **指标**: 吞吐量、延迟、CPU/内存使用
- **工具**: 自定义基准测试
- **目标**:
  - 延迟 <120ms
  - 吞吐量 >10Mbps
  - 内存使用 <100MB

#### 4. 互操作测试 🌐

- **对象**: 官方 C++ 实现
- **场景**: 各种网络条件下的互操作
- **工具**: Docker 容器测试环境

### 测试环境配置

```yaml
# docker-compose.test.yml
version: '3.8'
services:
  srt-php:
    build: .
    ports:
      - "8888:8888"
    environment:
      - SRT_LOG_LEVEL=debug

  srt-official:
    image: haivision/srt:latest
    ports:
      - "9999:9999"
    command: srt-live-transmit -v udp://:9999 srt://:8888
```

---

## ⚡ 性能优化

### 优化策略

#### 1. 内存管理 🧠

- **对象池**: 复用数据包对象
- **缓冲区管理**: 减少内存拷贝
- **垃圾回收**: 优化 GC 触发时机

#### 2. CPU 优化 💨

- **算法优化**: 使用更高效的排序和查找算法
- **批处理**: 批量处理网络 I/O
- **缓存**: 缓存计算结果

#### 3. 网络优化 🌐

- **零拷贝**: 减少数据拷贝次数
- **批量发送**: 聚合小包发送
- **优先级队列**: 控制包优先处理

### 性能监控

```php
class PerformanceMonitor
{
    public function recordMetrics(): array
    {
        return [
            'throughput' => $this->calculateThroughput(),
            'latency' => $this->measureLatency(),
            'packet_loss' => $this->getPacketLossRate(),
            'cpu_usage' => $this->getCpuUsage(),
            'memory_usage' => memory_get_usage(true)
        ];
    }
}
```

---

## 🚀 部署与集成

### 系统要求

#### 最低要求

- **PHP**: >= 8.1
- **扩展**: sockets, openssl, pcntl
- **内存**: >= 128MB
- **网络**: UDP 端口访问权限

#### 推荐配置

- **PHP**: >= 8.3
- **内存**: >= 512MB
- **CPU**: >= 2 核心
- **网络**: 低延迟网络环境

### 安装指南

```bash
# Composer 安装
composer require tourze/srt-php

# 系统权限配置
sudo setcap cap_net_raw+ep /usr/bin/php
```

### 配置示例

```php
// config/srt.php
return [
    'latency' => 120, // ms
    'max_bandwidth' => 10000000, // 10Mbps
    'encryption' => [
        'enabled' => true,
        'algorithm' => 'AES-256',
        'passphrase' => env('SRT_PASSPHRASE')
    ],
    'buffer_size' => 1048576, // 1MB
    'statistics_interval' => 1000 // ms
];
```

### 监控集成

```php
// 与 Prometheus 集成
class SrtMetricsExporter
{
    public function export(): void
    {
        $metrics = $this->srtSocket->getStats();
        
        $this->prometheus->gauge('srt_latency_ms')
            ->set($metrics['latency']);
            
        $this->prometheus->counter('srt_packets_sent_total')
            ->inc($metrics['packets_sent']);
    }
}
```

---

## 📊 里程碑 & 交付物

### 📈 发布版本规划

| 版本 | 功能范围 | 预计时间 | 状态 |
|------|----------|----------|------|
| v0.1.0 | 基础 UDP + 简单握手 | Week 6 | 🟡 计划中 |
| v0.2.0 | 数据传输 + ACK/NAK | Week 14 | 🟡 计划中 |
| v0.3.0 | 加密 + 流量控制 | Week 22 | 🟡 计划中 |
| v0.4.0 | Live 模式 + 性能优化 | Week 28 | 🟡 计划中 |
| v1.0.0 | 生产就绪版本 | Week 34 | 🟡 计划中 |

### 🎯 成功指标

- ✅ **互操作性**: 与官方实现 100% 兼容
- ✅ **性能**: 延迟 <120ms, 吞吐量 >10Mbps  
- ✅ **可靠性**: 99.9% 包传输成功率
- ✅ **安全性**: 通过安全审计
- ✅ **文档**: 100% API 文档覆盖率

---

## 👥 参与贡献

### 开发者指南

1. **Fork** 项目仓库
2. **创建** 功能分支: `git checkout -b feature/amazing-feature`
3. **提交** 更改: `git commit -m 'Add amazing feature'`
4. **推送** 分支: `git push origin feature/amazing-feature`
5. **创建** Pull Request

### 代码规范

- 遵循 PSR-12 编码标准
- 使用 PHPStan Level 8 静态分析
- 单元测试覆盖率 >90%
- 详细的 PHPDoc 注释

---

## 📞 技术支持

- **GitHub Issues**: [项目 Issues](https://github.com/tourze/srt-php/issues)
- **技术文档**: [Wiki 页面](https://github.com/tourze/srt-php/wiki)
- **SRT Alliance**: [官方社区](https://www.srtalliance.org/)

---

## 📚 参考资料

- [SRT 协议 RFC 草案](https://datatracker.ietf.org/doc/html/draft-sharabayko-srt-01)
- [SRT 官方 C++ 实现](https://github.com/Haivision/srt)
- [SRT Alliance 官网](https://www.srtalliance.org/)
- [FFmpeg SRT 集成](https://ffmpeg.org/ffmpeg-protocols.html#srt)

---

*最后更新: 2025-01-27*
*项目状态: 🟡 开发规划阶段*
