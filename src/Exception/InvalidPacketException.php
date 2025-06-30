<?php

declare(strict_types=1);

namespace Tourze\SRT\Exception;

use InvalidArgumentException;

/**
 * SRT 包格式无效异常
 *
 * 当解析或构造 SRT 包时遇到格式错误时抛出此异常
 */
class InvalidPacketException extends InvalidArgumentException
{
    public static function invalidHeaderLength(int $actual): self
    {
        return new self("SRT header must be exactly 16 bytes, got {$actual} bytes");
    }

    public static function invalidSequenceNumber(int $sequenceNumber): self
    {
        return new self("Sequence number must be in range 0-2147483647, got {$sequenceNumber}");
    }

    public static function invalidMessageNumber(int $messageNumber): self
    {
        return new self("Message number must be in range 0-67108863, got {$messageNumber}");
    }

    public static function invalidControlType(int $controlType): self
    {
        return new self("Invalid control type: {$controlType}");
    }

    public static function invalidPacketPosition(int $position): self
    {
        return new self("Packet position must be 0-3, got {$position}");
    }

    public static function invalidEncryptionFlags(int $flags): self
    {
        return new self("Encryption flags must be 0-3, got {$flags}");
    }
} 