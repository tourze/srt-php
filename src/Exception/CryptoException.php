<?php

declare(strict_types=1);

namespace Tourze\SRT\Exception;

/**
 * 加密相关异常
 *
 * 用于处理 SRT 协议加密过程中的各种错误
 */
class CryptoException extends \RuntimeException
{
    /**
     * 加密失败
     */
    public const ENCRYPTION_FAILED = 1001;

    /**
     * 解密失败
     */
    public const DECRYPTION_FAILED = 1002;

    /**
     * 密钥生成失败
     */
    public const KEY_GENERATION_FAILED = 1003;

    /**
     * 不支持的算法
     */
    public const UNSUPPORTED_ALGORITHM = 1004;

    /**
     * 密钥交换失败
     */
    public const KEY_EXCHANGE_FAILED = 1005;
}
