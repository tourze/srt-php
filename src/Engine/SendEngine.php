<?php

declare(strict_types=1);

namespace Tourze\SRT\Engine;

use Tourze\SRT\Exception\SendException;
use Tourze\SRT\Protocol\ControlPacket;
use Tourze\SRT\Protocol\DataPacket;
use Tourze\SRT\Transport\TransportInterface;

/**
 * SRT 发送引擎
 *
 * 负责数据包的发送、重传管理和流量控制
 *
 * 功能包括：
 * - 数据包分片和发送
 * - 重传队列管理
 * - 发送窗口控制
 * - 发送速率限制
 */
class SendEngine
{
    private int $nextSequenceNumber = 1;

    private int $nextMessageNumber = 1;

    private int $destinationSocketId = 0;

    private int $maxPayloadSize = 1456; // 默认载荷大小 (1500 - 44)

    private int $sendWindowSize = 8192; // 发送窗口大小

    private int $maxBandwidth = 1000000; // 1Mbps 默认带宽限制

    // 重传管理
    /** @var array<int, array<string, mixed>> */
    private array $unacknowledgedPackets = []; // 未确认的包

    /** @var array<int, array<string, mixed>> */
    private array $retransmissionQueue = []; // 重传队列

    private int $retransmissionTimeout = 500; // 重传超时 (ms)

    private int $maxRetransmissions = 5; // 最大重传次数

    // 统计信息
    private int $totalSent = 0;

    private int $totalRetransmitted = 0;

    private int $totalBytes = 0;

    private float $lastSendTime = 0;

    public function __construct(
        private readonly TransportInterface $transport,
    ) {
        $this->lastSendTime = microtime(true);
    }

    /**
     * 发送数据
     */
    public function send(string $data): int
    {
        if ('' === $data) {
            return 0;
        }

        $totalSent = 0;
        $chunks = $this->fragmentData($data);

        foreach ($chunks as $index => $chunk) {
            $packet = $this->createDataPacket($chunk, count($chunks), $index);
            $this->sendDataPacket($packet);
            $totalSent += strlen($chunk);
        }

        $this->totalBytes += $totalSent;

        return $totalSent;
    }

    /**
     * 处理 ACK 包
     */
    public function handleAck(ControlPacket $ackPacket): void
    {
        $sequenceNumber = $ackPacket->getAckSequenceNumber();

        // 确认所有序列号 <= sequenceNumber 的包
        foreach ($this->unacknowledgedPackets as $seq => $packetInfo) {
            if ($seq <= $sequenceNumber) {
                unset($this->unacknowledgedPackets[$seq]);

                // 从重传队列中移除
                if (isset($this->retransmissionQueue[$seq])) {
                    unset($this->retransmissionQueue[$seq]);
                }
            }
        }

        // 更新发送窗口
        $this->updateSendWindow();
    }

    /**
     * 处理 NAK 包
     */
    public function handleNak(ControlPacket $nakPacket): void
    {
        $lostSequences = $nakPacket->getNakLostSequences();

        foreach ($lostSequences as $seq) {
            if (!is_numeric($seq)) {
                continue;
            }
            $sequenceNumber = (int) $seq;
            if (isset($this->unacknowledgedPackets[$sequenceNumber])) {
                $this->scheduleRetransmission($sequenceNumber);
            }
        }
    }

    /**
     * 处理重传
     */
    public function processRetransmissions(): void
    {
        $now = microtime(true) * 1000; // 转换为毫秒

        foreach ($this->retransmissionQueue as $seq => $retransmissionInfo) {
            if ($now >= $retransmissionInfo['nextRetransmissionTime']) {
                $this->retransmitPacket($seq);
            }
        }
    }

    /**
     * 数据分片
     * @return array<int, string>
     */
    private function fragmentData(string $data): array
    {
        $chunks = [];
        $length = strlen($data);

        for ($offset = 0; $offset < $length; $offset += $this->maxPayloadSize) {
            $chunks[] = substr($data, $offset, $this->maxPayloadSize);
        }

        return $chunks;
    }

    /**
     * 创建数据包
     */
    private function createDataPacket(string $payload, int $totalChunks, int $chunkIndex): DataPacket
    {
        $packet = new DataPacket(
            $this->nextSequenceNumber++,
            $this->nextMessageNumber,
            $payload
        );

        $packet->setDestinationSocketId($this->destinationSocketId);

        // 设置包位置标志
        if (1 === $totalChunks) {
            $packet->setPacketPosition(DataPacket::PP_SINGLE);
        } elseif (0 === $chunkIndex) {
            $packet->setPacketPosition(DataPacket::PP_FIRST);
        } elseif ($chunkIndex === $totalChunks - 1) {
            $packet->setPacketPosition(DataPacket::PP_LAST);
        } else {
            $packet->setPacketPosition(DataPacket::PP_MIDDLE);
        }

        // 如果是最后一个包，递增消息号
        if ($chunkIndex === $totalChunks - 1) {
            ++$this->nextMessageNumber;
        }

        return $packet;
    }

    /**
     * 发送数据包
     */
    private function sendDataPacket(DataPacket $packet): void
    {
        // 检查发送窗口
        if (count($this->unacknowledgedPackets) >= $this->sendWindowSize) {
            throw new SendException('Send window full');
        }

        // 速率限制
        $this->enforceRateLimit($packet->getTotalSize());

        // 发送包
        $data = $packet->serialize();
        $this->transport->send($data);

        // 记录未确认的包
        $this->unacknowledgedPackets[$packet->getSequenceNumber()] = [
            'packet' => $packet,
            'sendTime' => microtime(true) * 1000,
            'retransmissionCount' => 0,
        ];

        // 安排重传
        $this->scheduleRetransmission($packet->getSequenceNumber());

        ++$this->totalSent;
    }

    /**
     * 安排重传
     */
    private function scheduleRetransmission(int $sequenceNumber): void
    {
        if (!isset($this->unacknowledgedPackets[$sequenceNumber])) {
            return;
        }

        $packetInfo = $this->unacknowledgedPackets[$sequenceNumber];
        $retransmissionCount = $packetInfo['retransmissionCount'];

        if ($retransmissionCount >= $this->maxRetransmissions) {
            // 超过最大重传次数，从队列中移除
            unset($this->unacknowledgedPackets[$sequenceNumber], $this->retransmissionQueue[$sequenceNumber]);

            return;
        }

        $this->retransmissionQueue[$sequenceNumber] = [
            'nextRetransmissionTime' => microtime(true) * 1000 + $this->retransmissionTimeout,
            'retransmissionCount' => $retransmissionCount,
        ];
    }

    /**
     * 重传包
     */
    private function retransmitPacket(int $sequenceNumber): void
    {
        if (!isset($this->unacknowledgedPackets[$sequenceNumber])) {
            return;
        }

        $packetInfo = $this->unacknowledgedPackets[$sequenceNumber];
        $packet = $packetInfo['packet'];

        if (!$packet instanceof DataPacket) {
            return;
        }

        // 设置重传标志
        $packet->setRetransmissionFlag(true);

        // 发送重传包
        $data = $packet->serialize();
        $this->transport->send($data);

        // 更新重传信息
        $currentCount = $this->unacknowledgedPackets[$sequenceNumber]['retransmissionCount'];
        $retransmissionCount = is_numeric($currentCount) ? (int) $currentCount : 0;
        $this->unacknowledgedPackets[$sequenceNumber]['retransmissionCount'] = $retransmissionCount + 1;
        ++$this->totalRetransmitted;

        // 重新安排下次重传
        $this->scheduleRetransmission($sequenceNumber);
    }

    /**
     * 速率限制
     */
    private function enforceRateLimit(int $packetSize): void
    {
        $now = microtime(true);
        $timeDiff = $now - $this->lastSendTime;

        // 计算应该等待的时间
        $requiredTime = ($packetSize * 8) / $this->maxBandwidth; // 秒

        if ($timeDiff < $requiredTime) {
            $sleepTime = $requiredTime - $timeDiff;
            usleep((int) ($sleepTime * 1000000)); // 转换为微秒
        }

        $this->lastSendTime = microtime(true);
    }

    /**
     * 更新发送窗口
     */
    private function updateSendWindow(): void
    {
        // 简单的窗口管理：根据未确认包的数量调整
        $unackedCount = count($this->unacknowledgedPackets);

        if ($unackedCount < $this->sendWindowSize / 2) {
            // 可以增加窗口大小
            $this->sendWindowSize = min($this->sendWindowSize + 1, 65536);
        } elseif ($unackedCount > $this->sendWindowSize * 0.8) {
            // 减少窗口大小
            $this->sendWindowSize = max($this->sendWindowSize - 1, 1);
        }
    }

    /**
     * 设置目标 Socket ID
     */
    public function setDestinationSocketId(int $socketId): void
    {
        $this->destinationSocketId = $socketId;
    }

    /**
     * 设置最大载荷大小
     */
    public function setMaxPayloadSize(int $size): void
    {
        $this->maxPayloadSize = max(1, min($size, 65536));
    }

    /**
     * 设置最大带宽
     */
    public function setMaxBandwidth(int $bandwidth): void
    {
        $this->maxBandwidth = max(1000, $bandwidth); // 最少 1Kbps
    }

    /**
     * 设置重传超时
     */
    public function setRetransmissionTimeout(int $timeoutMs): void
    {
        $this->retransmissionTimeout = max(10, $timeoutMs);
    }

    /**
     * 获取统计信息
     * @return array<string, int>
     */
    public function getStatistics(): array
    {
        return [
            'total_sent' => $this->totalSent,
            'total_retransmitted' => $this->totalRetransmitted,
            'total_bytes' => $this->totalBytes,
            'unacknowledged_count' => count($this->unacknowledgedPackets),
            'retransmission_queue_size' => count($this->retransmissionQueue),
            'send_window_size' => $this->sendWindowSize,
            'next_sequence_number' => $this->nextSequenceNumber,
            'next_message_number' => $this->nextMessageNumber,
        ];
    }

    /**
     * 清理资源
     */
    public function cleanup(): void
    {
        $this->unacknowledgedPackets = [];
        $this->retransmissionQueue = [];
    }
}
