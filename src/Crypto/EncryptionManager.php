<?php

declare(strict_types=1);

namespace Tourze\SRT\Crypto;

use Tourze\SRT\Exception\CryptoException;

/**
 * SRT 加密管理器
 *
 * 负责管理 SRT 协议的加密功能，包括：
 * - AES 加密/解密
 * - 密钥生成和管理
 * - 加密协商
 * - 密钥更新
 */
class EncryptionManager
{
    /**
     * 支持的加密算法
     */
    public const ALGO_AES_128 = 'AES-128-CTR';
    public const ALGO_AES_192 = 'AES-192-CTR';
    public const ALGO_AES_256 = 'AES-256-CTR';

    /**
     * 密钥长度映射
     */
    private const KEY_LENGTHS = [
        self::ALGO_AES_128 => 16,
        self::ALGO_AES_192 => 24,
        self::ALGO_AES_256 => 32,
    ];

    /**
     * 当前使用的加密算法
     */
    private string $algorithm;

    /**
     * 加密密钥
     */
    private string $encryptionKey;

    /**
     * 解密密钥 (可能与加密密钥不同)
     */
    private string $decryptionKey;

    /**
     * 密码短语
     */
    private string $passphrase;

    /**
     * 是否启用加密
     */
    private bool $encryptionEnabled = false;

    /**
     * 密钥更新间隔 (包数量)
     */
    private int $keyRefreshInterval = 1000000;

    /**
     * 当前密钥使用的包计数
     */
    private int $keyUsageCount = 0;

    /**
     * 密钥管理器
     */
    private KeyManager $keyManager;

    /**
     * 加密统计信息
     */
    private array $stats = [
        'encrypted_packets' => 0,
        'decrypted_packets' => 0,
        'key_refreshes' => 0,
        'encryption_errors' => 0,
        'decryption_errors' => 0,
    ];

    public function __construct(
        string $algorithm = self::ALGO_AES_256,
        string $passphrase = ''
    ) {
        if (!in_array($algorithm, [self::ALGO_AES_128, self::ALGO_AES_192, self::ALGO_AES_256])) {
            throw new CryptoException("不支持的加密算法: {$algorithm}");
        }

        $this->algorithm = $algorithm;
        $this->passphrase = $passphrase;
        $this->keyManager = new KeyManager();

        if (!empty($passphrase)) {
            $this->enableEncryption($passphrase);
        }
    }

    /**
     * 启用加密
     */
    public function enableEncryption(string $passphrase): void
    {
        $this->passphrase = $passphrase;
        $this->encryptionEnabled = true;

        // 生成初始密钥
        $this->generateKeys();
    }

    /**
     * 禁用加密
     */
    public function disableEncryption(): void
    {
        $this->encryptionEnabled = false;
        $this->clearKeys();
    }

    /**
     * 加密数据包
     */
    public function encryptPacket(string $data, int $sequenceNumber): string
    {
        if (!$this->encryptionEnabled) {
            return $data;
        }

        try {
            // 检查是否需要密钥更新
            $this->checkKeyRefresh();

            // 生成初始化向量 (IV)
            $iv = $this->generateIv($sequenceNumber);

            // 执行加密
            $encrypted = openssl_encrypt(
                $data,
                $this->algorithm,
                $this->encryptionKey,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($encrypted === false) {
                $this->stats['encryption_errors']++;
                throw new CryptoException('数据包加密失败: ' . openssl_error_string());
            }

            $this->stats['encrypted_packets']++;
            $this->keyUsageCount++;

            return $encrypted;

        } catch (\Throwable $e) {
            $this->stats['encryption_errors']++;
            throw new CryptoException('加密过程中发生错误: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 解密数据包
     */
    public function decryptPacket(string $encryptedData, int $sequenceNumber): string
    {
        if (!$this->encryptionEnabled) {
            return $encryptedData;
        }

        try {
            // 生成初始化向量 (IV)
            $iv = $this->generateIv($sequenceNumber);

            // 执行解密
            $decrypted = openssl_decrypt(
                $encryptedData,
                $this->algorithm,
                $this->decryptionKey,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($decrypted === false) {
                $this->stats['decryption_errors']++;
                throw new CryptoException('数据包解密失败: ' . openssl_error_string());
            }

            $this->stats['decrypted_packets']++;

            return $decrypted;

        } catch (\Throwable $e) {
            $this->stats['decryption_errors']++;
            throw new CryptoException('解密过程中发生错误: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 生成密钥对
     */
    private function generateKeys(): void
    {
        $keyLength = self::KEY_LENGTHS[$this->algorithm];

        // 使用 PBKDF2 从密码短语生成密钥
        $salt = $this->keyManager->generateSalt();
        $this->encryptionKey = hash_pbkdf2(
            'sha256',
            $this->passphrase,
            $salt,
            10000, // 迭代次数
            $keyLength,
            true
        );

        // 在对称加密中，解密密钥与加密密钥相同
        $this->decryptionKey = $this->encryptionKey;

        $this->keyUsageCount = 0;
    }

    /**
     * 生成初始化向量 (IV)
     */
    private function generateIv(int $sequenceNumber): string
    {
        // 使用序列号作为 IV 的一部分，确保每个包的 IV 不同但可重现
        $ivData = pack('N', $sequenceNumber) . str_repeat("\x00", 12);
        
        // CTR 模式需要16字节的 IV
        return $ivData;
    }

    /**
     * 检查是否需要密钥更新
     */
    private function checkKeyRefresh(): void
    {
        if ($this->keyUsageCount >= $this->keyRefreshInterval) {
            $this->refreshKeys();
        }
    }

    /**
     * 更新密钥
     */
    private function refreshKeys(): void
    {
        // 生成新的密钥
        $this->generateKeys();
        $this->stats['key_refreshes']++;
    }

    /**
     * 清除密钥
     */
    private function clearKeys(): void
    {
        if (isset($this->encryptionKey)) {
            sodium_memzero($this->encryptionKey);
        }
        if (isset($this->decryptionKey)) {
            sodium_memzero($this->decryptionKey);
        }
    }

    /**
     * 设置密钥更新间隔
     */
    public function setKeyRefreshInterval(int $interval): void
    {
        $this->keyRefreshInterval = max(1000, $interval);
    }

    /**
     * 获取当前加密算法
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * 检查是否启用了加密
     */
    public function isEncryptionEnabled(): bool
    {
        return $this->encryptionEnabled;
    }

    /**
     * 获取密钥使用计数
     */
    public function getKeyUsageCount(): int
    {
        return $this->keyUsageCount;
    }

    /**
     * 获取加密统计信息
     */
    public function getStats(): array
    {
        return [
            'algorithm' => $this->algorithm,
            'encryption_enabled' => $this->encryptionEnabled,
            'key_usage_count' => $this->keyUsageCount,
            'key_refresh_interval' => $this->keyRefreshInterval,
            ...$this->stats,
        ];
    }

    /**
     * 重置统计信息
     */
    public function resetStats(): void
    {
        $this->stats = [
            'encrypted_packets' => 0,
            'decrypted_packets' => 0,
            'key_refreshes' => 0,
            'encryption_errors' => 0,
            'decryption_errors' => 0,
        ];
    }

    /**
     * 验证加密配置
     */
    public function validateConfig(): bool
    {
        if (!extension_loaded('openssl')) {
            throw new CryptoException('OpenSSL 扩展未安装');
        }

        if (!in_array($this->algorithm, openssl_get_cipher_methods())) {
            throw new CryptoException("系统不支持加密算法: {$this->algorithm}");
        }

        return true;
    }

    /**
     * 析构函数 - 清理敏感数据
     */
    public function __destruct()
    {
        $this->clearKeys();
    }
}
