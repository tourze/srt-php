<?php

declare(strict_types=1);

namespace Tourze\SRT\Protocol;

/**
 * SRT 握手包
 *
 * 定义 SRT 握手数据包的结构和操作方法
 *
 * @see https://datatracker.ietf.org/doc/html/draft-sharabayko-srt-01#section-3.2.1
 */
class HandshakePacket
{
    private int $version = 0;
    private int $encryptionField = 0;
    private int $extensionField = 0;
    private int $initialSequenceNumber = 0;
    private int $maxTransmissionUnitSize = 1500;
    private int $maxFlowWinSize = 8192;
    private int $handshakeType = 0;
    private int $srtSocketId = 0;
    private string $peerIpAddress = '';
    private array $srtExtensions = [];

    /**
     * 设置版本号
     */
    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    /**
     * 获取版本号
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * 设置加密字段
     */
    public function setEncryptionField(int $encryptionField): void
    {
        $this->encryptionField = $encryptionField;
    }

    /**
     * 获取加密字段
     */
    public function getEncryptionField(): int
    {
        return $this->encryptionField;
    }

    /**
     * 设置扩展字段
     */
    public function setExtensionField(int $extensionField): void
    {
        $this->extensionField = $extensionField;
    }

    /**
     * 获取扩展字段
     */
    public function getExtensionField(): int
    {
        return $this->extensionField;
    }

    /**
     * 设置初始序列号
     */
    public function setInitialSequenceNumber(int $sequenceNumber): void
    {
        $this->initialSequenceNumber = $sequenceNumber;
    }

    /**
     * 获取初始序列号
     */
    public function getInitialSequenceNumber(): int
    {
        return $this->initialSequenceNumber;
    }

    /**
     * 设置最大传输单元大小
     */
    public function setMaxTransmissionUnitSize(int $mtu): void
    {
        $this->maxTransmissionUnitSize = $mtu;
    }

    /**
     * 获取最大传输单元大小
     */
    public function getMaxTransmissionUnitSize(): int
    {
        return $this->maxTransmissionUnitSize;
    }

    /**
     * 设置最大流窗口大小
     */
    public function setMaxFlowWinSize(int $winSize): void
    {
        $this->maxFlowWinSize = $winSize;
    }

    /**
     * 获取最大流窗口大小
     */
    public function getMaxFlowWinSize(): int
    {
        return $this->maxFlowWinSize;
    }

    /**
     * 设置握手类型
     */
    public function setHandshakeType(int $type): void
    {
        $this->handshakeType = $type;
    }

    /**
     * 获取握手类型
     */
    public function getHandshakeType(): int
    {
        return $this->handshakeType;
    }

    /**
     * 设置 SRT Socket ID
     */
    public function setSrtSocketId(int $socketId): void
    {
        $this->srtSocketId = $socketId;
    }

    /**
     * 获取 SRT Socket ID
     */
    public function getSrtSocketId(): int
    {
        return $this->srtSocketId;
    }

    /**
     * 设置对端 IP 地址
     */
    public function setPeerIpAddress(string $ipAddress): void
    {
        $this->peerIpAddress = $ipAddress;
    }

    /**
     * 获取对端 IP 地址
     */
    public function getPeerIpAddress(): string
    {
        return $this->peerIpAddress;
    }

    /**
     * 设置 SRT 扩展
     */
    public function setSrtExtensions(array $extensions): void
    {
        $this->srtExtensions = $extensions;
    }

    /**
     * 获取 SRT 扩展
     */
    public function getSrtExtensions(): array
    {
        return $this->srtExtensions;
    }

    /**
     * 序列化握手包为二进制数据
     */
    public function serialize(): string
    {
        $data = '';

        // 版本号 (4 bytes)
        $data .= pack('N', $this->version);

        // 加密和扩展字段 (4 bytes)
        $encExt = ($this->encryptionField << 24) | ($this->extensionField & 0xFFFFFF);
        $data .= pack('N', $encExt);

        // 初始序列号 (4 bytes)
        $data .= pack('N', $this->initialSequenceNumber);

        // MTU 大小 (4 bytes)
        $data .= pack('N', $this->maxTransmissionUnitSize);

        // 流窗口大小 (4 bytes)
        $data .= pack('N', $this->maxFlowWinSize);

        // 握手类型 (4 bytes)
        $data .= pack('N', $this->handshakeType);

        // SRT Socket ID (4 bytes)
        $data .= pack('N', $this->srtSocketId);

        // 对端 IP 地址 (4 bytes - 简化为 32 位整数)
        $ipInt = $this->peerIpAddress === '0.0.0.0' ? 0 : ip2long($this->peerIpAddress);
        $data .= pack('N', $ipInt ?: 0);

        // SRT 扩展数据
        if (!empty($this->srtExtensions)) {
            $data .= $this->serializeExtensions();
        }

        return $data;
    }

    /**
     * 从二进制数据反序列化握手包
     */
    public static function deserialize(string $data): self
    {
        if (strlen($data) < 32) {
            throw new \InvalidArgumentException('Handshake packet too short');
        }

        $packet = new self();
        $pos = 0;

        // 版本号
        $packet->version = unpack('N', substr($data, $pos, 4))[1];
        $pos += 4;

        // 加密和扩展字段
        $encExt = unpack('N', substr($data, $pos, 4))[1];
        $packet->encryptionField = ($encExt >> 24) & 0xFF;
        $packet->extensionField = $encExt & 0xFFFFFF;
        $pos += 4;

        // 初始序列号
        $packet->initialSequenceNumber = unpack('N', substr($data, $pos, 4))[1];
        $pos += 4;

        // MTU 大小
        $packet->maxTransmissionUnitSize = unpack('N', substr($data, $pos, 4))[1];
        $pos += 4;

        // 流窗口大小
        $packet->maxFlowWinSize = unpack('N', substr($data, $pos, 4))[1];
        $pos += 4;

        // 握手类型
        $packet->handshakeType = unpack('N', substr($data, $pos, 4))[1];
        $pos += 4;

        // SRT Socket ID
        $packet->srtSocketId = unpack('N', substr($data, $pos, 4))[1];
        $pos += 4;

        // 对端 IP 地址
        $ipInt = unpack('N', substr($data, $pos, 4))[1];
        $packet->peerIpAddress = $ipInt ? long2ip($ipInt) : '0.0.0.0';
        $pos += 4;

        // SRT 扩展数据
        if (strlen($data) > $pos) {
            $packet->srtExtensions = $packet->deserializeExtensions(substr($data, $pos));
        }

        return $packet;
    }

    /**
     * 序列化扩展数据
     */
    private function serializeExtensions(): string
    {
        $data = '';

        foreach ($this->srtExtensions as $type => $value) {
            // 扩展类型 (2 bytes)
            $data .= pack('n', $type);

            // 扩展长度和值
            if (is_int($value)) {
                // 整数值 (4 bytes)
                $data .= pack('n', 4); // 长度
                $data .= pack('N', $value);
            } elseif (is_string($value)) {
                // 字符串值
                $len = strlen($value);
                $data .= pack('n', $len); // 长度
                $data .= $value;
                // 对齐到 4 字节边界
                $padding = (4 - ($len % 4)) % 4;
                $data .= str_repeat("\0", $padding);
            }
        }

        return $data;
    }

    /**
     * 反序列化扩展数据
     */
    private function deserializeExtensions(string $data): array
    {
        $extensions = [];
        $pos = 0;
        $len = strlen($data);

        while ($pos < $len - 4) {
            // 扩展类型
            $type = unpack('n', substr($data, $pos, 2))[1];
            $pos += 2;

            // 扩展长度
            $extLen = unpack('n', substr($data, $pos, 2))[1];
            $pos += 2;

            if ($pos + $extLen > $len) {
                break; // 数据不完整
            }

            // 扩展值
            if ($extLen === 4) {
                // 整数值
                $extensions[$type] = unpack('N', substr($data, $pos, 4))[1];
            } else {
                // 字符串值
                $extensions[$type] = substr($data, $pos, $extLen);
            }

            $pos += $extLen;

            // 跳过对齐字节
            $padding = (4 - ($extLen % 4)) % 4;
            $pos += $padding;
        }

        return $extensions;
    }
}
