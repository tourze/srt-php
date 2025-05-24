<?php

declare(strict_types=1);

namespace Tourze\SRT\Protocol;

/**
 * SRT 控制包
 * 
 * 定义 SRT 控制包的结构和操作方法，包括 ACK/NAK/KEEPALIVE 等
 * 
 * @see https://datatracker.ietf.org/doc/html/draft-sharabayko-srt-01#section-3.2
 */
class ControlPacket
{
    // 控制包类型
    public const TYPE_HANDSHAKE = 0x0000;
    public const TYPE_KEEPALIVE = 0x0001;
    public const TYPE_ACK = 0x0002;
    public const TYPE_NAK = 0x0003;
    public const TYPE_CONGESTION_WARNING = 0x0004;
    public const TYPE_SHUTDOWN = 0x0005;
    public const TYPE_ACKACK = 0x0006;
    public const TYPE_DROP_REQUEST = 0x0007;
    public const TYPE_PEER_ERROR = 0x0008;
    public const TYPE_USER_DEFINED = 0x7FFF;
    
    private int $controlType = 0;
    private int $subType = 0;
    private int $additionalInfo = 0;
    private int $timestamp = 0;
    private int $destinationSocketId = 0;
    private string $controlInformation = '';
    
    public function __construct(int $controlType = 0, string $controlInformation = '')
    {
        $this->controlType = $controlType;
        $this->controlInformation = $controlInformation;
        $this->timestamp = $this->getCurrentTimestamp();
    }
    
    /**
     * 设置控制类型
     */
    public function setControlType(int $type): void
    {
        $this->controlType = $type & 0x7FFF; // 15 位
    }
    
    /**
     * 获取控制类型
     */
    public function getControlType(): int
    {
        return $this->controlType;
    }
    
    /**
     * 设置子类型
     */
    public function setSubType(int $subType): void
    {
        $this->subType = $subType & 0xFFFF; // 16 位
    }
    
    /**
     * 获取子类型
     */
    public function getSubType(): int
    {
        return $this->subType;
    }
    
    /**
     * 设置附加信息
     */
    public function setAdditionalInfo(int $info): void
    {
        $this->additionalInfo = $info;
    }
    
    /**
     * 获取附加信息
     */
    public function getAdditionalInfo(): int
    {
        return $this->additionalInfo;
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
     * 设置控制信息
     */
    public function setControlInformation(string $info): void
    {
        $this->controlInformation = $info;
    }
    
    /**
     * 获取控制信息
     */
    public function getControlInformation(): string
    {
        return $this->controlInformation;
    }
    
    /**
     * 创建 ACK 包
     */
    public static function createAck(int $sequenceNumber, int $destinationSocketId): self
    {
        $packet = new self(self::TYPE_ACK);
        $packet->setAdditionalInfo($sequenceNumber);
        $packet->setDestinationSocketId($destinationSocketId);
        
        // ACK 控制信息包含接收到的最高序列号
        $packet->setControlInformation(pack('N', $sequenceNumber));
        
        return $packet;
    }
    
    /**
     * 创建 NAK 包
     */
    public static function createNak(array $lostSequences, int $destinationSocketId): self
    {
        $packet = new self(self::TYPE_NAK);
        $packet->setDestinationSocketId($destinationSocketId);
        
        // NAK 控制信息包含丢失的序列号列表
        $nakInfo = '';
        foreach ($lostSequences as $seq) {
            $nakInfo .= pack('N', $seq);
        }
        $packet->setControlInformation($nakInfo);
        
        return $packet;
    }
    
    /**
     * 创建保持连接包
     */
    public static function createKeepAlive(int $destinationSocketId): self
    {
        $packet = new self(self::TYPE_KEEPALIVE);
        $packet->setDestinationSocketId($destinationSocketId);
        return $packet;
    }
    
    /**
     * 创建拥塞警告包
     */
    public static function createCongestionWarning(int $destinationSocketId): self
    {
        $packet = new self(self::TYPE_CONGESTION_WARNING);
        $packet->setDestinationSocketId($destinationSocketId);
        return $packet;
    }
    
    /**
     * 创建关闭连接包
     */
    public static function createShutdown(int $destinationSocketId): self
    {
        $packet = new self(self::TYPE_SHUTDOWN);
        $packet->setDestinationSocketId($destinationSocketId);
        return $packet;
    }
    
    /**
     * 创建 ACKACK 包
     */
    public static function createAckAck(int $ackNumber, int $destinationSocketId): self
    {
        $packet = new self(self::TYPE_ACKACK);
        $packet->setAdditionalInfo($ackNumber);
        $packet->setDestinationSocketId($destinationSocketId);
        return $packet;
    }
    
    /**
     * 获取 NAK 丢失序列号列表
     */
    public function getNakLostSequences(): array
    {
        if ($this->controlType !== self::TYPE_NAK) {
            return [];
        }
        
        $sequences = [];
        $data = $this->controlInformation;
        $len = strlen($data);
        
        for ($i = 0; $i < $len; $i += 4) {
            if ($i + 4 <= $len) {
                $sequences[] = unpack('N', substr($data, $i, 4))[1];
            }
        }
        
        return $sequences;
    }
    
    /**
     * 获取 ACK 序列号
     */
    public function getAckSequenceNumber(): int
    {
        if ($this->controlType !== self::TYPE_ACK) {
            return 0;
        }
        
        return $this->additionalInfo;
    }
    
    /**
     * 序列化控制包为二进制数据
     */
    public function serialize(): string
    {
        $header = '';
        
        // 第一个 32 位字段：F + 控制类型 + 子类型
        $field1 = 0x80000000; // F=1 表示控制包
        $field1 |= ($this->controlType & 0x7FFF) << 16;
        $field1 |= ($this->subType & 0xFFFF);
        $header .= pack('N', $field1);
        
        // 第二个 32 位字段：附加信息
        $header .= pack('N', $this->additionalInfo);
        
        // 第三个 32 位字段：时间戳
        $header .= pack('N', $this->timestamp);
        
        // 第四个 32 位字段：目标 Socket ID
        $header .= pack('N', $this->destinationSocketId);
        
        return $header . $this->controlInformation;
    }
    
    /**
     * 从二进制数据反序列化控制包
     */
    public static function deserialize(string $data): self
    {
        if (strlen($data) < 16) {
            throw new \InvalidArgumentException('Control packet too short');
        }
        
        $pos = 0;
        
        // 第一个 32 位字段
        $field1 = unpack('N', substr($data, $pos, 4))[1];
        $pos += 4;
        
        $f = ($field1 >> 31) & 1;
        if ($f !== 1) {
            throw new \InvalidArgumentException('Not a control packet');
        }
        
        $controlType = ($field1 >> 16) & 0x7FFF;
        $subType = $field1 & 0xFFFF;
        
        // 第二个 32 位字段：附加信息
        $additionalInfo = unpack('N', substr($data, $pos, 4))[1];
        $pos += 4;
        
        // 第三个 32 位字段：时间戳
        $timestamp = unpack('N', substr($data, $pos, 4))[1];
        $pos += 4;
        
        // 第四个 32 位字段：目标 Socket ID
        $destinationSocketId = unpack('N', substr($data, $pos, 4))[1];
        $pos += 4;
        
        // 控制信息
        $controlInformation = substr($data, $pos);
        
        $packet = new self($controlType, $controlInformation);
        $packet->setSubType($subType);
        $packet->setAdditionalInfo($additionalInfo);
        $packet->setTimestamp($timestamp);
        $packet->setDestinationSocketId($destinationSocketId);
        
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
     * 获取包类型名称
     */
    public function getTypeName(): string
    {
        return match ($this->controlType) {
            self::TYPE_HANDSHAKE => 'HANDSHAKE',
            self::TYPE_KEEPALIVE => 'KEEPALIVE',
            self::TYPE_ACK => 'ACK',
            self::TYPE_NAK => 'NAK',
            self::TYPE_CONGESTION_WARNING => 'CONGESTION_WARNING',
            self::TYPE_SHUTDOWN => 'SHUTDOWN',
            self::TYPE_ACKACK => 'ACKACK',
            self::TYPE_DROP_REQUEST => 'DROP_REQUEST',
            self::TYPE_PEER_ERROR => 'PEER_ERROR',
            default => 'UNKNOWN',
        };
    }
    
    /**
     * 计算包的总大小
     */
    public function getTotalSize(): int
    {
        return 16 + strlen($this->controlInformation); // 16 字节头部 + 控制信息
    }
}
