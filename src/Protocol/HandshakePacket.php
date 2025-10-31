<?php

declare(strict_types=1);

namespace Tourze\SRT\Protocol;

use Tourze\SRT\Exception\InvalidPacketException;

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

    /**
     * @var array<int, mixed>
     */
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
     * @param array<int, mixed> $extensions
     */
    public function setSrtExtensions(array $extensions): void
    {
        $this->srtExtensions = $extensions;
    }

    /**
     * 获取 SRT 扩展
     * @return array<int, mixed>
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
        $ipInt = '0.0.0.0' === $this->peerIpAddress ? 0 : ip2long($this->peerIpAddress);
        $data .= pack('N', false !== $ipInt ? $ipInt : 0);

        // SRT 扩展数据
        if ([] !== $this->srtExtensions) {
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
            throw InvalidPacketException::invalidHeaderLength(strlen($data));
        }

        $packet = new self();
        $pos = 0;

        $pos = $packet->deserializeVersion($data, $pos);
        $pos = $packet->deserializeEncryptionAndExtension($data, $pos);
        $pos = $packet->deserializeInitialSequenceNumber($data, $pos);
        $pos = $packet->deserializeMtu($data, $pos);
        $pos = $packet->deserializeMaxFlowWinSize($data, $pos);
        $pos = $packet->deserializeHandshakeType($data, $pos);
        $pos = $packet->deserializeSrtSocketId($data, $pos);
        $pos = $packet->deserializePeerIpAddress($data, $pos);

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
     * @return array<int, mixed>
     */
    private function deserializeExtensions(string $data): array
    {
        /** @var array<int, mixed> $extensions */
        $extensions = [];
        $pos = 0;
        $len = strlen($data);

        while ($pos < $len - 4) {
            $extension = $this->extractSingleExtension($data, $pos, $len);
            if (null === $extension) {
                break;
            }

            [$type, $value, $newPos] = $extension;
            $extensions[$type] = $value;
            $pos = $newPos;
        }

        return $extensions;
    }

    /**
     * 提取单个扩展数据
     * @return array{int, mixed, int}|null
     */
    private function extractSingleExtension(string $data, int $pos, int $len): ?array
    {
        $extensionHeader = $this->parseExtensionHeader($data, $pos);
        if (null === $extensionHeader) {
            return null;
        }

        [$type, $extLen] = $extensionHeader;
        $pos += 4;

        if ($pos + $extLen > $len) {
            return null;
        }

        $value = $this->parseExtensionValue($data, $pos, $extLen);
        if (null === $value) {
            return null;
        }

        $pos += $extLen + $this->calculatePadding($extLen);

        return [$type, $value, $pos];
    }

    /**
     * 解析版本号字段
     */
    private function deserializeVersion(string $data, int $pos): int
    {
        $versionResult = unpack('N', substr($data, $pos, 4));
        if (false === $versionResult || !isset($versionResult[1]) || !is_int($versionResult[1])) {
            throw InvalidPacketException::invalidHeaderLength(strlen($data));
        }
        $this->version = $versionResult[1];

        return $pos + 4;
    }

    /**
     * 解析加密和扩展字段
     */
    private function deserializeEncryptionAndExtension(string $data, int $pos): int
    {
        $encExtResult = unpack('N', substr($data, $pos, 4));
        if (false === $encExtResult || !isset($encExtResult[1]) || !is_int($encExtResult[1])) {
            throw InvalidPacketException::invalidHeaderLength(strlen($data));
        }
        $encExt = $encExtResult[1];
        $this->encryptionField = ($encExt >> 24) & 0xFF;
        $this->extensionField = $encExt & 0xFFFFFF;

        return $pos + 4;
    }

    /**
     * 解析初始序列号字段
     */
    private function deserializeInitialSequenceNumber(string $data, int $pos): int
    {
        $initialSeqResult = unpack('N', substr($data, $pos, 4));
        if (false === $initialSeqResult || !isset($initialSeqResult[1]) || !is_int($initialSeqResult[1])) {
            throw InvalidPacketException::invalidHeaderLength(strlen($data));
        }
        $this->initialSequenceNumber = $initialSeqResult[1];

        return $pos + 4;
    }

    /**
     * 解析MTU大小字段
     */
    private function deserializeMtu(string $data, int $pos): int
    {
        $mtuResult = unpack('N', substr($data, $pos, 4));
        if (false === $mtuResult || !isset($mtuResult[1]) || !is_int($mtuResult[1])) {
            throw InvalidPacketException::invalidHeaderLength(strlen($data));
        }
        $this->maxTransmissionUnitSize = $mtuResult[1];

        return $pos + 4;
    }

    /**
     * 解析流窗口大小字段
     */
    private function deserializeMaxFlowWinSize(string $data, int $pos): int
    {
        $maxFlowWinResult = unpack('N', substr($data, $pos, 4));
        if (false === $maxFlowWinResult || !isset($maxFlowWinResult[1]) || !is_int($maxFlowWinResult[1])) {
            throw InvalidPacketException::invalidHeaderLength(strlen($data));
        }
        $this->maxFlowWinSize = $maxFlowWinResult[1];

        return $pos + 4;
    }

    /**
     * 解析握手类型字段
     */
    private function deserializeHandshakeType(string $data, int $pos): int
    {
        $handshakeTypeResult = unpack('N', substr($data, $pos, 4));
        if (false === $handshakeTypeResult || !isset($handshakeTypeResult[1]) || !is_int($handshakeTypeResult[1])) {
            throw InvalidPacketException::invalidHeaderLength(strlen($data));
        }
        $this->handshakeType = $handshakeTypeResult[1];

        return $pos + 4;
    }

    /**
     * 解析SRT Socket ID字段
     */
    private function deserializeSrtSocketId(string $data, int $pos): int
    {
        $srtSocketIdResult = unpack('N', substr($data, $pos, 4));
        if (false === $srtSocketIdResult || !isset($srtSocketIdResult[1]) || !is_int($srtSocketIdResult[1])) {
            throw InvalidPacketException::invalidHeaderLength(strlen($data));
        }
        $this->srtSocketId = $srtSocketIdResult[1];

        return $pos + 4;
    }

    /**
     * 解析对端IP地址字段
     */
    private function deserializePeerIpAddress(string $data, int $pos): int
    {
        $ipIntResult = unpack('N', substr($data, $pos, 4));
        if (false === $ipIntResult || !isset($ipIntResult[1]) || !is_int($ipIntResult[1])) {
            throw InvalidPacketException::invalidHeaderLength(strlen($data));
        }
        $ipInt = $ipIntResult[1];
        $longToIpResult = long2ip($ipInt);
        $this->peerIpAddress = false !== $longToIpResult ? $longToIpResult : '0.0.0.0';

        return $pos + 4;
    }

    /**
     * 解析扩展头部
     * @return array{int, int}|null
     */
    private function parseExtensionHeader(string $data, int $pos): ?array
    {
        $typeResult = unpack('n', substr($data, $pos, 2));
        if (false === $typeResult || !isset($typeResult[1]) || !is_int($typeResult[1])) {
            return null;
        }
        $type = $typeResult[1];

        $extLenResult = unpack('n', substr($data, $pos + 2, 2));
        if (false === $extLenResult || !isset($extLenResult[1]) || !is_int($extLenResult[1])) {
            return null;
        }
        $extLen = $extLenResult[1];

        return [$type, $extLen];
    }

    /**
     * 解析扩展值
     */
    private function parseExtensionValue(string $data, int $pos, int $extLen): mixed
    {
        if (4 === $extLen) {
            $intValueResult = unpack('N', substr($data, $pos, 4));
            if (false !== $intValueResult && isset($intValueResult[1])) {
                $intValue = $intValueResult[1];
                assert(is_int($intValue));

                return $intValue;
            }
        } else {
            return substr($data, $pos, $extLen);
        }

        return null;
    }

    /**
     * 计算对齐填充字节数
     */
    private function calculatePadding(int $length): int
    {
        return (4 - ($length % 4)) % 4;
    }
}
