<?php

declare(strict_types=1);

namespace Tourze\SRT\Protocol;

use Tourze\SRT\Exception\InvalidPacketException;

/**
 * SRT 包头处理类
 *
 * 负责解析和构建 SRT 协议的包头部分（16字节固定长度）
 *
 * SRT 包头结构：
 * - 数据包：F(1) + SeqNum(31) + PP(2) + O(1) + KK(2) + R(1) + MsgNum(26) + Timestamp(32) + DestID(32)
 * - 控制包：F(1) + CtrlType(15) + Subtype(16) + TypeInfo(32) + Timestamp(32) + DestID(32)
 */
class PacketHeader
{
    // 控制包类型常量
    public const CONTROL_HANDSHAKE = 0x0000;
    public const CONTROL_KEEPALIVE = 0x0001;
    public const CONTROL_ACK = 0x0002;
    public const CONTROL_NAK = 0x0003;
    public const CONTROL_SHUTDOWN = 0x0005;
    public const CONTROL_ACKACK = 0x0006;

    // 包位置标志常量
    public const PACKET_POSITION_MIDDLE = 0b00;
    public const PACKET_POSITION_LAST = 0b01;
    public const PACKET_POSITION_FIRST = 0b10;
    public const PACKET_POSITION_ONLY = 0b11;

    // 加密标志常量
    public const ENCRYPTION_NONE = 0b00;
    public const ENCRYPTION_EVEN_KEY = 0b01;
    public const ENCRYPTION_ODD_KEY = 0b10;
    public const ENCRYPTION_BOTH_KEYS = 0b11;

    private function __construct(
        private readonly bool $isControlPacket,
        private readonly int $field1,           // 序列号 或 控制类型
        private readonly int $field2,           // 消息相关字段 或 类型特定信息
        private readonly int $timestamp,
        private readonly int $destinationSocketId,
        private readonly int $subtype = 0       // 仅控制包使用
    ) {}

    /**
     * 从二进制数据解析包头
     */
    public static function fromBinary(string $data): self
    {
        if (strlen($data) !== 16) {
            throw InvalidPacketException::invalidHeaderLength(strlen($data));
        }

        $fields = unpack('N4', $data);
        if ($fields === false) {
            throw new InvalidPacketException('Failed to unpack SRT header');
        }

        $field1 = $fields[1];
        $field2 = $fields[2];
        $timestamp = $fields[3];
        $destId = $fields[4];

        $isControlPacket = ($field1 & 0x80000000) !== 0;

        if ($isControlPacket) {
            // 控制包：F(1) + Subtype(16) + CtrlType(15)
            // F=1 在位31，Subtype 在位15-30，CtrlType 在位0-14
            $controlType = $field1 & 0x7FFF;              // 控制类型在位0-14
            $subtype = ($field1 >> 15) & 0xFFFF;          // 子类型在位15-30

            return new self(
                isControlPacket: true,
                field1: $controlType,
                field2: $field2, // 类型特定信息
                timestamp: $timestamp,
                destinationSocketId: $destId,
                subtype: $subtype
            );
        } else {
            // 数据包：保持原始字段值，在getter中解析
            return new self(
                isControlPacket: false,
                field1: $field1,
                field2: $field2,
                timestamp: $timestamp,
                destinationSocketId: $destId
            );
        }
    }

    /**
     * 创建数据包头部
     */
    public static function createDataPacket(
        int  $sequenceNumber,
        int  $packetPosition,
        bool $isOrdered,
        int  $encryptionFlags,
        bool $isRetransmitted,
        int  $messageNumber,
        int  $timestamp,
        int  $destinationSocketId
    ): self
    {
        self::validateDataPacketFields($sequenceNumber, $packetPosition, $encryptionFlags, $messageNumber);

        // 构建第一个字段：F(0) + 序列号(31位)
        $field1 = $sequenceNumber & 0x7FFFFFFF;

        // 构建第二个字段：PP(2) + O(1) + KK(2) + R(1) + 消息号(26位)
        $field2 = ($packetPosition & 0x3) << 30;
        $field2 |= ($isOrdered ? 1 : 0) << 29;
        $field2 |= ($encryptionFlags & 0x3) << 27;
        $field2 |= ($isRetransmitted ? 1 : 0) << 26;
        $field2 |= $messageNumber & 0x3FFFFFF;

        return new self(
            isControlPacket: false,
            field1: $field1,
            field2: $field2,
            timestamp: $timestamp,
            destinationSocketId: $destinationSocketId
        );
    }

    /**
     * 创建控制包头部
     */
    public static function createControlPacket(
        int $controlType,
        int $subtype,
        int $typeSpecificInfo,
        int $timestamp,
        int $destinationSocketId
    ): self
    {
        return new self(
            isControlPacket: true,
            field1: $controlType,      // 只存储控制类型
            field2: $typeSpecificInfo,
            timestamp: $timestamp,
            destinationSocketId: $destinationSocketId,
            subtype: $subtype         // 单独存储子类型
        );
    }

    /**
     * 转换为二进制格式
     */
    public function toBinary(): string
    {
        if ($this->isControlPacket) {
            // 重新构建控制包的 field1: F(1) + Subtype(16) + CtrlType(15)
            $field1 = 0x80000000;                          // F=1 在位31
            $field1 |= ($this->subtype & 0xFFFF) << 15;    // 子类型在位15-30
            $field1 |= ($this->field1 & 0x7FFF);           // 控制类型在位0-14
        } else {
            $field1 = $this->field1;
        }

        return pack('N4', $field1, $this->field2, $this->timestamp, $this->destinationSocketId);
    }

    // Getter 方法
    public function isControlPacket(): bool
    {
        return $this->isControlPacket;
    }

    public function getSequenceNumber(): int
    {
        if ($this->isControlPacket) {
            throw new InvalidPacketException('Control packets do not have sequence numbers');
        }
        return $this->field1 & 0x7FFFFFFF;
    }

    public function getControlType(): int
    {
        if (!$this->isControlPacket) {
            throw new InvalidPacketException('Data packets do not have control types');
        }
        return $this->field1;  // field1 直接存储控制类型
    }

    public function getSubtype(): int
    {
        if (!$this->isControlPacket) {
            throw new InvalidPacketException('Data packets do not have subtypes');
        }
        return $this->subtype;
    }

    public function getPacketPosition(): int
    {
        if ($this->isControlPacket) {
            throw new InvalidPacketException('Control packets do not have packet positions');
        }
        return ($this->field2 >> 30) & 0x3;
    }

    public function isOrdered(): bool
    {
        if ($this->isControlPacket) {
            throw new InvalidPacketException('Control packets do not have order flags');
        }
        return (($this->field2 >> 29) & 0x1) === 1;
    }

    public function getEncryptionFlags(): int
    {
        if ($this->isControlPacket) {
            throw new InvalidPacketException('Control packets do not have encryption flags');
        }
        return ($this->field2 >> 27) & 0x3;
    }

    public function isRetransmitted(): bool
    {
        if ($this->isControlPacket) {
            throw new InvalidPacketException('Control packets do not have retransmission flags');
        }
        return (($this->field2 >> 26) & 0x1) === 1;
    }

    public function getMessageNumber(): int
    {
        if ($this->isControlPacket) {
            throw new InvalidPacketException('Control packets do not have message numbers');
        }
        return $this->field2 & 0x3FFFFFF;
    }

    public function getTypeSpecificInfo(): int
    {
        if (!$this->isControlPacket) {
            throw new InvalidPacketException('Data packets do not have type-specific info');
        }
        return $this->field2;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getDestinationSocketId(): int
    {
        return $this->destinationSocketId;
    }

    /**
     * 验证数据包字段
     */
    private static function validateDataPacketFields(
        int $sequenceNumber,
        int $packetPosition,
        int $encryptionFlags,
        int $messageNumber
    ): void
    {
        if ($sequenceNumber < 0 || $sequenceNumber > 0x7FFFFFFF) {
            throw InvalidPacketException::invalidSequenceNumber($sequenceNumber);
        }

        if ($packetPosition < 0 || $packetPosition > 3) {
            throw InvalidPacketException::invalidPacketPosition($packetPosition);
        }

        if ($encryptionFlags < 0 || $encryptionFlags > 3) {
            throw InvalidPacketException::invalidEncryptionFlags($encryptionFlags);
        }

        if ($messageNumber < 0 || $messageNumber > 0x3FFFFFF) {
            throw InvalidPacketException::invalidMessageNumber($messageNumber);
        }
    }
}
