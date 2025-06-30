<?php

declare(strict_types=1);

namespace Tourze\SRT\Protocol;

use Tourze\SRT\Exception\InvalidPacketException;

/**
 * SRT 数据包
 *
 * 定义 SRT 数据传输包的结构和操作方法
 *
 * @see https://datatracker.ietf.org/doc/html/draft-sharabayko-srt-01#section-3.1
 */
class DataPacket
{
    // 包位置标志
    public const PP_SINGLE = 0b00;      // 单独包
    public const PP_FIRST = 0b10;       // 首包
    public const PP_MIDDLE = 0b00;      // 中间包
    public const PP_LAST = 0b01;        // 末包

    private int $sequenceNumber = 0;
    private int $messageNumber = 0;
    private int $timestamp = 0;
    private int $destinationSocketId = 0;
    private int $packetPosition = self::PP_SINGLE;
    private bool $orderFlag = false;
    private int $keyBasedEncryption = 0;
    private bool $retransmissionFlag = false;
    private string $payload = '';

    public function __construct(
        int $sequenceNumber = 0,
        int $messageNumber = 0,
        string $payload = ''
    ) {
        $this->sequenceNumber = $sequenceNumber;
        $this->messageNumber = $messageNumber;
        $this->payload = $payload;
        $this->timestamp = $this->getCurrentTimestamp();
    }

    /**
     * 设置序列号
     */
    public function setSequenceNumber(int $sequenceNumber): void
    {
        $this->sequenceNumber = $sequenceNumber & 0x7FFFFFFF; // 31 位
    }

    /**
     * 获取序列号
     */
    public function getSequenceNumber(): int
    {
        return $this->sequenceNumber;
    }

    /**
     * 设置消息号
     */
    public function setMessageNumber(int $messageNumber): void
    {
        $this->messageNumber = $messageNumber & 0x1FFFFFF; // 25 位
    }

    /**
     * 获取消息号
     */
    public function getMessageNumber(): int
    {
        return $this->messageNumber;
    }

    /**
     * 设置时间戳
     */
    public function setTimestamp(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    /**
     * 获取时间戳
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * 设置目标 Socket ID
     */
    public function setDestinationSocketId(int $socketId): void
    {
        $this->destinationSocketId = $socketId;
    }

    /**
     * 获取目标 Socket ID
     */
    public function getDestinationSocketId(): int
    {
        return $this->destinationSocketId;
    }

    /**
     * 设置包位置标志
     */
    public function setPacketPosition(int $position): void
    {
        $this->packetPosition = $position & 0b11;
    }

    /**
     * 获取包位置标志
     */
    public function getPacketPosition(): int
    {
        return $this->packetPosition;
    }

    /**
     * 设置有序标志
     */
    public function setOrderFlag(bool $ordered): void
    {
        $this->orderFlag = $ordered;
    }

    /**
     * 获取有序标志
     */
    public function getOrderFlag(): bool
    {
        return $this->orderFlag;
    }

    /**
     * 设置密钥加密标志
     */
    public function setKeyBasedEncryption(int $kek): void
    {
        $this->keyBasedEncryption = $kek & 0b11;
    }

    /**
     * 获取密钥加密标志
     */
    public function getKeyBasedEncryption(): int
    {
        return $this->keyBasedEncryption;
    }

    /**
     * 设置重传标志
     */
    public function setRetransmissionFlag(bool $retransmit): void
    {
        $this->retransmissionFlag = $retransmit;
    }

    /**
     * 获取重传标志
     */
    public function getRetransmissionFlag(): bool
    {
        return $this->retransmissionFlag;
    }

    /**
     * 设置载荷数据
     */
    public function setPayload(string $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * 获取载荷数据
     */
    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * 获取载荷长度
     */
    public function getPayloadLength(): int
    {
        return strlen($this->payload);
    }

    /**
     * 是否为单独包
     */
    public function isSinglePacket(): bool
    {
        return $this->packetPosition === self::PP_SINGLE;
    }

    /**
     * 是否为首包
     */
    public function isFirstPacket(): bool
    {
        return $this->packetPosition === self::PP_FIRST;
    }

    /**
     * 是否为末包
     */
    public function isLastPacket(): bool
    {
        return $this->packetPosition === self::PP_LAST;
    }

    /**
     * 序列化数据包为二进制数据
     */
    public function serialize(): string
    {
        $header = '';

        // 第一个 32 位字段：F + 序列号
        $field1 = 0; // F=0 表示数据包
        $field1 |= ($this->sequenceNumber & 0x7FFFFFFF);
        $header .= pack('N', $field1);

        // 第二个 32 位字段：PP + O + KK + R + 消息号
        $field2 = 0;
        $field2 |= ($this->packetPosition & 0b11) << 30;
        $field2 |= ($this->orderFlag ? 1 : 0) << 29;
        $field2 |= ($this->keyBasedEncryption & 0b11) << 27;
        $field2 |= ($this->retransmissionFlag ? 1 : 0) << 26;
        $field2 |= ($this->messageNumber & 0x1FFFFFF);
        $header .= pack('N', $field2);

        // 第三个 32 位字段：时间戳
        $header .= pack('N', $this->timestamp);

        // 第四个 32 位字段：目标 Socket ID
        $header .= pack('N', $this->destinationSocketId);

        return $header . $this->payload;
    }

    /**
     * 从二进制数据反序列化数据包
     */
    public static function deserialize(string $data): self
    {
        if (strlen($data) < 16) {
            throw InvalidPacketException::invalidHeaderLength(strlen($data));
        }

        $pos = 0;

        // 第一个 32 位字段
        $field1 = unpack('N', substr($data, $pos, 4))[1];
        $pos += 4;

        $f = ($field1 >> 31) & 1;
        if ($f !== 0) {
            throw InvalidPacketException::invalidControlType($field1);
        }

        $sequenceNumber = $field1 & 0x7FFFFFFF;

        // 第二个 32 位字段
        $field2 = unpack('N', substr($data, $pos, 4))[1];
        $pos += 4;

        $packetPosition = ($field2 >> 30) & 0b11;
        $orderFlag = (($field2 >> 29) & 1) === 1;
        $keyBasedEncryption = ($field2 >> 27) & 0b11;
        $retransmissionFlag = (($field2 >> 26) & 1) === 1;
        $messageNumber = $field2 & 0x1FFFFFF;

        // 第三个 32 位字段：时间戳
        $timestamp = unpack('N', substr($data, $pos, 4))[1];
        $pos += 4;

        // 第四个 32 位字段：目标 Socket ID
        $destinationSocketId = unpack('N', substr($data, $pos, 4))[1];
        $pos += 4;

        // 载荷数据
        $payload = substr($data, $pos);

        $packet = new self($sequenceNumber, $messageNumber, $payload);
        $packet->setTimestamp($timestamp);
        $packet->setDestinationSocketId($destinationSocketId);
        $packet->setPacketPosition($packetPosition);
        $packet->setOrderFlag($orderFlag);
        $packet->setKeyBasedEncryption($keyBasedEncryption);
        $packet->setRetransmissionFlag($retransmissionFlag);

        return $packet;
    }

    /**
     * 获取当前时间戳（微秒）
     */
    private function getCurrentTimestamp(): int
    {
        return (int) (hrtime(true) / 1000); // 转换为微秒
    }

    /**
     * 计算包的总大小
     */
    public function getTotalSize(): int
    {
        return 16 + strlen($this->payload); // 16 字节头部 + 载荷
    }
}
