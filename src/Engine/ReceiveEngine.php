<?php

declare(strict_types=1);

namespace Tourze\SRT\Engine;

use Tourze\SRT\Protocol\ControlPacket;
use Tourze\SRT\Protocol\DataPacket;
use Tourze\SRT\Transport\TransportInterface;

/**
 * SRT 接收引擎
 *
 * 负责数据包的接收、排序、重组和丢包检测
 *
 * 功能包括：
 * - 数据包接收和验证
 * - 序列号排序
 * - 消息重组
 * - 丢包检测和 NAK 发送
 * - ACK 确认发送
 */
class ReceiveEngine
{
    private int $expectedSequenceNumber = 1;

    private int $sourceSocketId = 0;

    private int $receiveWindowSize = 8192; // 接收窗口大小

    private int $ackFrequency = 10; // 每接收多少包发送一次 ACK

    // 接收缓冲区管理
    /** @var array<int, DataPacket> */
    private array $receiveBuffer = []; // 按序列号索引的包缓冲区

    /** @var array<int, array{packets: array<DataPacket>, totalPackets: int, receivedPackets: int}> */
    private array $messageBuffer = []; // 按消息号索引的消息缓冲区

    /** @var array<int, string> */
    private array $completedMessages = []; // 完整消息队列

    // 丢包检测
    /** @var array<int, bool> */
    private array $receivedSequences = []; // 已接收的序列号

    private int $lastAckSequence = 0; // 最后确认的序列号

    private int $packetsReceived = 0; // 接收到的包计数

    // 统计信息
    private int $totalReceived = 0;

    private int $totalBytes = 0;

    private int $duplicatePackets = 0;

    private int $outOfOrderPackets = 0;

    private int $acksSent = 0;

    private int $naksSent = 0;

    public function __construct(private readonly TransportInterface $transport)
    {
    }

    /**
     * 处理接收到的数据包
     */
    public function handleDataPacket(DataPacket $packet): void
    {
        $sequenceNumber = $packet->getSequenceNumber();

        // 检查是否重复包
        if (isset($this->receivedSequences[$sequenceNumber])) {
            ++$this->duplicatePackets;

            return;
        }

        // 记录接收到的序列号
        $this->receivedSequences[$sequenceNumber] = true;
        ++$this->totalReceived;
        $this->totalBytes += $packet->getPayloadLength();

        // 检查是否乱序
        if ($sequenceNumber < $this->expectedSequenceNumber) {
            // 这是一个延迟到达的包，已经被认为丢失过
            ++$this->outOfOrderPackets;
        }

        // 将包添加到接收缓冲区
        $this->receiveBuffer[$sequenceNumber] = $packet;

        // 处理连续的包
        $this->processSequentialPackets();

        // 检测丢包并发送 NAK
        $this->detectLostPackets();

        // 定期发送 ACK
        ++$this->packetsReceived;
        if (0 === $this->packetsReceived % $this->ackFrequency) {
            $this->sendAck();
        }
    }

    /**
     * 获取完整消息
     */
    public function getNextMessage(): ?string
    {
        if ([] === $this->completedMessages) {
            return null;
        }

        return array_shift($this->completedMessages);
    }

    /**
     * 检查是否有可用消息
     */
    public function hasMessage(): bool
    {
        return [] !== $this->completedMessages;
    }

    /**
     * 处理连续的包
     */
    private function processSequentialPackets(): void
    {
        while (isset($this->receiveBuffer[$this->expectedSequenceNumber])) {
            $packet = $this->receiveBuffer[$this->expectedSequenceNumber];
            unset($this->receiveBuffer[$this->expectedSequenceNumber]);

            $this->processPacket($packet);
            ++$this->expectedSequenceNumber;
        }
    }

    /**
     * 处理单个包
     */
    private function processPacket(DataPacket $packet): void
    {
        $messageNumber = $packet->getMessageNumber();

        // 初始化消息缓冲区
        if (!isset($this->messageBuffer[$messageNumber])) {
            $this->messageBuffer[$messageNumber] = [
                'packets' => [],
                'totalPackets' => 0,
                'receivedPackets' => 0,
            ];
        }

        if (!is_array($this->messageBuffer[$messageNumber]['packets'])) {
            $this->messageBuffer[$messageNumber]['packets'] = [];
        }
        $this->messageBuffer[$messageNumber]['packets'][] = $packet;
        ++$this->messageBuffer[$messageNumber]['receivedPackets'];

        // 检查是否收到完整消息
        if ($packet->isSinglePacket()) {
            // 单个包消息
            $this->completedMessages[] = $packet->getPayload();
            unset($this->messageBuffer[$messageNumber]);
        } elseif ($packet->isFirstPacket()) {
            // 首包，标记消息开始
            $this->messageBuffer[$messageNumber]['totalPackets'] = $this->estimateMessagePackets($packet);
        } elseif ($packet->isLastPacket()) {
            // 末包，检查消息是否完整
            $this->checkMessageComplete($messageNumber);
        }
    }

    /**
     * 检查消息是否完整
     */
    private function checkMessageComplete(int $messageNumber): void
    {
        if (!isset($this->messageBuffer[$messageNumber])) {
            return;
        }

        $messageInfo = $this->messageBuffer[$messageNumber];
        /** @var array<int, DataPacket> $packets */
        $packets = $messageInfo['packets'];

        // 按序列号排序包
        usort($packets, fn (DataPacket $a, DataPacket $b): int => $a->getSequenceNumber() <=> $b->getSequenceNumber());

        // 检查包的连续性
        if ([] === $packets) {
            return;
        }

        $expectedSequence = $packets[0]->getSequenceNumber();
        $isComplete = true;

        foreach ($packets as $packet) {
            if ($packet->getSequenceNumber() !== $expectedSequence) {
                $isComplete = false;
                break;
            }
            ++$expectedSequence;
        }

        $lastPacket = $packets[count($packets) - 1];
        if ($isComplete && $lastPacket->isLastPacket()) {
            // 消息完整，重组数据
            $messageData = '';
            foreach ($packets as $packet) {
                $messageData .= $packet->getPayload();
            }

            $this->completedMessages[] = $messageData;
            unset($this->messageBuffer[$messageNumber]);
        }
    }

    /**
     * 估算消息包数
     */
    private function estimateMessagePackets(DataPacket $firstPacket): int
    {
        // 简单估算：基于首包的载荷大小
        // 实际实现中可能需要更复杂的逻辑
        return 1; // 占位符实现
    }

    /**
     * 检测丢包
     */
    private function detectLostPackets(): void
    {
        $lostSequences = [];

        // 检查期望序列号之前的缺失包
        for ($seq = $this->lastAckSequence + 1; $seq < $this->expectedSequenceNumber; ++$seq) {
            if (!isset($this->receivedSequences[$seq])) {
                $lostSequences[] = $seq;
            }
        }

        // 检查接收缓冲区中的间隙，限制检查范围到接收窗口大小
        $maxSeq = max(array_keys($this->receiveBuffer + [$this->expectedSequenceNumber => true]));
        $windowEndSeq = $this->expectedSequenceNumber + $this->receiveWindowSize;
        $checkUntil = min($maxSeq, $windowEndSeq);

        for ($seq = $this->expectedSequenceNumber + 1; $seq <= $checkUntil; ++$seq) {
            if (!isset($this->receivedSequences[$seq]) && !isset($this->receiveBuffer[$seq])) {
                $lostSequences[] = $seq;
            }
        }

        // 发送 NAK
        if ([] !== $lostSequences) {
            $this->sendNak($lostSequences);
        }
    }

    /**
     * 发送 ACK
     */
    private function sendAck(): void
    {
        // 找到最高连续确认的序列号
        $ackSequence = $this->expectedSequenceNumber - 1;

        if ($ackSequence > $this->lastAckSequence) {
            $ackPacket = ControlPacket::createAck($ackSequence, $this->sourceSocketId);
            $this->transport->send($ackPacket->serialize());

            $this->lastAckSequence = $ackSequence;
            ++$this->acksSent;
        }
    }

    /**
     * 发送 NAK
     * @param array<int, int> $lostSequences
     */
    private function sendNak(array $lostSequences): void
    {
        // 限制 NAK 包大小，避免一次发送太多丢失序列号
        $maxNakSize = 100; // 最多报告 100 个丢失包
        $chunks = array_chunk($lostSequences, $maxNakSize);

        foreach ($chunks as $chunk) {
            $nakPacket = ControlPacket::createNak($chunk, $this->sourceSocketId);
            $this->transport->send($nakPacket->serialize());
            ++$this->naksSent;
        }
    }

    /**
     * 强制发送 ACK
     */
    public function forceAck(): void
    {
        $this->sendAck();
    }

    /**
     * 设置源 Socket ID
     */
    public function setSourceSocketId(int $socketId): void
    {
        $this->sourceSocketId = $socketId;
    }

    /**
     * 设置 ACK 频率
     */
    public function setAckFrequency(int $frequency): void
    {
        $this->ackFrequency = max(1, $frequency);
    }

    /**
     * 设置接收窗口大小
     */
    public function setReceiveWindowSize(int $size): void
    {
        $this->receiveWindowSize = max(1, $size);
    }

    /**
     * 获取统计信息
     * @return array<string, int>
     */
    public function getStatistics(): array
    {
        return [
            'total_received' => $this->totalReceived,
            'total_bytes' => $this->totalBytes,
            'duplicate_packets' => $this->duplicatePackets,
            'out_of_order_packets' => $this->outOfOrderPackets,
            'acks_sent' => $this->acksSent,
            'naks_sent' => $this->naksSent,
            'expected_sequence_number' => $this->expectedSequenceNumber,
            'last_ack_sequence' => $this->lastAckSequence,
            'receive_buffer_size' => count($this->receiveBuffer),
            'message_buffer_size' => count($this->messageBuffer),
            'completed_messages_count' => count($this->completedMessages),
        ];
    }

    /**
     * 清理资源
     */
    public function cleanup(): void
    {
        $this->receiveBuffer = [];
        $this->messageBuffer = [];
        $this->completedMessages = [];
        $this->receivedSequences = [];
    }
}
