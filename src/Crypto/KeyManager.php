<?php

declare(strict_types=1);

namespace Tourze\SRT\Crypto;

use Tourze\SRT\Exception\CryptoException;

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
     * 密钥生成算法
     */
    public const KEYGEN_PBKDF2 = 'pbkdf2';
    public const KEYGEN_HKDF = 'hkdf';
    
    /**
     * 默认盐长度
     */
    private const DEFAULT_SALT_LENGTH = 16;
    
    /**
     * 默认迭代次数
     */
    private const DEFAULT_ITERATIONS = 10000;
    
    /**
     * 密钥存储
     */
    private array $keyStore = [];
    
    /**
     * 盐存储
     */
    private array $saltStore = [];
    
    /**
     * 密钥生成统计
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
        $salt = random_bytes($length);
        $this->stats['salt_generated']++;
        
        return $salt;
    }

    /**
     * 使用 PBKDF2 生成密钥
     */
    public function generateKeyPbkdf2(
        string $password,
        string $salt,
        int $iterations = self::DEFAULT_ITERATIONS,
        int $keyLength = 32,
        string $algorithm = 'sha256'
    ): string {
        $key = hash_pbkdf2($algorithm, $password, $salt, $iterations, $keyLength, true);
        
        if ($key === false) {
            throw new CryptoException('PBKDF2 密钥生成失败');
        }
        
        $this->stats['keys_generated']++;
        
        return $key;
    }

    /**
     * 使用 HKDF 生成密钥
     */
    public function generateKeyHkdf(
        string $inputKeyMaterial,
        int $keyLength = 32,
        string $info = '',
        string $salt = '',
        string $algorithm = 'sha256'
    ): string {
        if (!function_exists('hash_hkdf')) {
            throw new CryptoException('HKDF 函数不可用，需要 PHP 7.1.2+');
        }
        
        $key = hash_hkdf($algorithm, $inputKeyMaterial, $keyLength, $info, $salt);
        
        if ($key === false) {
            throw new CryptoException('HKDF 密钥生成失败');
        }
        
        $this->stats['keys_generated']++;
        
        return $key;
    }

    /**
     * 存储密钥
     */
    public function storeKey(string $keyId, string $key): void
    {
        $this->keyStore[$keyId] = $key;
    }

    /**
     * 获取密钥
     */
    public function getKey(string $keyId): ?string
    {
        return $this->keyStore[$keyId] ?? null;
    }

    /**
     * 删除密钥
     */
    public function deleteKey(string $keyId): void
    {
        if (isset($this->keyStore[$keyId])) {
            // 安全清零内存
            \sodium_memzero($this->keyStore[$keyId]);
            unset($this->keyStore[$keyId]);
        }
    }

    /**
     * 存储盐值
     */
    public function storeSalt(string $saltId, string $salt): void
    {
        $this->saltStore[$saltId] = $salt;
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
     * 密钥交换 (Diffie-Hellman)
     */
    public function performKeyExchange(string $myPrivateKey, string $theirPublicKey): string
    {
        try {
            $sharedSecret = \sodium_crypto_box_beforenm($theirPublicKey, $myPrivateKey);
            return $sharedSecret;
        } catch (\Throwable $e) {
            throw new CryptoException('密钥交换失败: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 轮换密钥
     */
    public function rotateKey(string $keyId, string $newKey): void
    {
        if (isset($this->keyStore[$keyId])) {
            // 安全清零旧密钥
            \sodium_memzero($this->keyStore[$keyId]);
        }
        
        $this->keyStore[$keyId] = $newKey;
        $this->stats['keys_rotated']++;
    }

    /**
     * 导出密钥 (用于密钥交换)
     */
    public function exportKey(string $keyId): ?string
    {
        return $this->keyStore[$keyId] ?? null;
    }

    /**
     * 导入密钥 (从密钥交换中获得)
     */
    public function importKey(string $keyId, string $key): void
    {
        $this->keyStore[$keyId] = $key;
    }

    /**
     * 验证密钥强度
     */
    public function validateKeyStrength(string $key): bool
    {
        // 检查密钥长度
        if (strlen($key) < 16) {
            return false;
        }
        
        // 检查密钥熵 (简单检查)
        $entropy = $this->calculateEntropy($key);
        
        // 要求熵值大于 4.0 (对于随机密钥应该接近 8.0)
        return $entropy > 4.0;
    }

    /**
     * 计算数据熵
     */
    private function calculateEntropy(string $data): float
    {
        $frequency = array_count_values(str_split($data));
        $length = strlen($data);
        $entropy = 0.0;
        
        foreach ($frequency as $count) {
            $probability = $count / $length;
            $entropy -= $probability * log($probability, 2);
        }
        
        return $entropy;
    }

    /**
     * 获取存储的密钥数量
     */
    public function getKeyCount(): int
    {
        return count($this->keyStore);
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
     * 重置统计信息
     */
    public function resetStats(): void
    {
        $this->stats = [
            'keys_generated' => 0,
            'keys_rotated' => 0,
            'salt_generated' => 0,
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