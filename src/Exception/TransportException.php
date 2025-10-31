<?php

declare(strict_types=1);

namespace Tourze\SRT\Exception;

/**
 * 传输层异常
 *
 * 当网络传输操作失败时抛出此异常
 */
class TransportException extends \RuntimeException
{
    public static function bindFailed(string $host, int $port, string $error): self
    {
        return new self("Failed to bind to {$host}:{$port}: {$error}");
    }

    public static function connectFailed(string $host, int $port, string $error): self
    {
        return new self("Failed to connect to {$host}:{$port}: {$error}");
    }

    public static function sendFailed(string $error): self
    {
        return new self("Failed to send data: {$error}");
    }

    public static function receiveFailed(string $error): self
    {
        return new self("Failed to receive data: {$error}");
    }

    public static function socketCreationFailed(string $error): self
    {
        return new self("Failed to create socket: {$error}");
    }

    public static function socketOptionFailed(string $option, string $error): self
    {
        return new self("Failed to set socket option {$option}: {$error}");
    }

    public static function invalidAddress(string $address): self
    {
        return new self("Invalid address: {$address}");
    }
}
