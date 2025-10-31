<?php

declare(strict_types=1);

namespace Tourze\SRT\Crypto;

/**
 * SRT 密钥管理器
 *
 * 负责管理 SRT 协议的密钥操作，包括：
 * - 密钥生成
 * - 密钥存储
 * - 密钥交换
 * - 密钥轮换
 */
class KeyManager
{
    /**
     * 默认盐长度
     */
    private const DEFAULT_SALT_LENGTH = 16;

    /**
     * 密钥存储
     * @var array<string, string>
     */
    private array $keyStore = [];

    /**
     * 盐存储
     * @var array<string, string>
     */
    private array $saltStore = [];

    /**
     * 密钥生成统计
     * @var array<string, int>
     */
    private array $stats = [
        'keys_generated' => 0,
        'keys_rotated' => 0,
        'salt_generated' => 0,
    ];

    /**
     * 生成随机盐
     */
    public function generateSalt(int $length = self::DEFAULT_SALT_LENGTH): string
    {
        $salt = random_bytes(max(1, $length));
        ++$this->stats['salt_generated'];

        return $salt;
    }

    /**
     * 获取密钥
     */
    public function getKey(string $keyId): ?string
    {
        return $this->keyStore[$keyId] ?? null;
    }

    /**
     * 获取盐值
     */
    public function getSalt(string $saltId): ?string
    {
        return $this->saltStore[$saltId] ?? null;
    }

    /**
     * 生成密钥对 (用于非对称加密)
     * @return array<string, string>
     */
    public function generateKeyPair(): array
    {
        $keyPair = \sodium_crypto_box_keypair();

        return [
            'public_key' => \sodium_crypto_box_publickey($keyPair),
            'private_key' => \sodium_crypto_box_secretkey($keyPair),
            'keypair' => $keyPair,
        ];
    }

    /**
     * 清除所有密钥
     */
    public function clearAllKeys(): void
    {
        foreach ($this->keyStore as $key) {
            \sodium_memzero($key);
        }

        $this->keyStore = [];
        $this->saltStore = [];
    }

    /**
     * 获取统计信息
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return [
            'stored_keys' => count($this->keyStore),
            'stored_salts' => count($this->saltStore),
            ...$this->stats,
        ];
    }

    /**
     * 析构函数 - 清理敏感数据
     */
    public function __destruct()
    {
        $this->clearAllKeys();
    }
}
