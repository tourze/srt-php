<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Crypto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\SRT\Crypto\EncryptionManager;
use Tourze\SRT\Exception\CryptoException;

/**
 * @internal
 */
#[CoversClass(EncryptionManager::class)]
final class EncryptionManagerTest extends TestCase
{
    private EncryptionManager $encryptionManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encryptionManager = new EncryptionManager();
    }

    public function testClassExists(): void
    {
        $this->assertInstanceOf(EncryptionManager::class, $this->encryptionManager);
        $this->assertNotEmpty($this->encryptionManager->getStats());
    }

    public function testDecryptPacket(): void
    {
        $testData = 'test packet data';
        $sequenceNumber = 123;
        $passphrase = 'test-passphrase';

        $this->encryptionManager->enableEncryption($passphrase);

        $encryptedData = $this->encryptionManager->encryptPacket($testData, $sequenceNumber);
        $decryptedData = $this->encryptionManager->decryptPacket($encryptedData, $sequenceNumber);

        $this->assertSame($testData, $decryptedData);
    }

    public function testDecryptPacketWithoutEncryption(): void
    {
        $testData = 'test packet data';
        $sequenceNumber = 123;

        $result = $this->encryptionManager->decryptPacket($testData, $sequenceNumber);

        $this->assertSame($testData, $result);
    }

    public function testDisableEncryption(): void
    {
        $passphrase = 'test-passphrase';

        $this->encryptionManager->enableEncryption($passphrase);
        $this->assertTrue($this->encryptionManager->isEncryptionEnabled());

        $this->encryptionManager->disableEncryption();
        $this->assertFalse($this->encryptionManager->isEncryptionEnabled());
    }

    public function testEnableEncryption(): void
    {
        $passphrase = 'test-passphrase';

        $this->assertFalse($this->encryptionManager->isEncryptionEnabled());

        $this->encryptionManager->enableEncryption($passphrase);
        $this->assertTrue($this->encryptionManager->isEncryptionEnabled());
    }

    public function testEncryptPacket(): void
    {
        $testData = 'test packet data';
        $sequenceNumber = 123;
        $passphrase = 'test-passphrase';

        $this->encryptionManager->enableEncryption($passphrase);

        $encryptedData = $this->encryptionManager->encryptPacket($testData, $sequenceNumber);

        $this->assertNotEmpty($encryptedData);
        $this->assertNotSame($testData, $encryptedData);
    }

    public function testEncryptPacketWithoutEncryption(): void
    {
        $testData = 'test packet data';
        $sequenceNumber = 123;

        $result = $this->encryptionManager->encryptPacket($testData, $sequenceNumber);

        $this->assertSame($testData, $result);
    }

    public function testEncryptPacketUpdatesStats(): void
    {
        $testData = 'test packet data';
        $sequenceNumber = 123;
        $passphrase = 'test-passphrase';

        $this->encryptionManager->enableEncryption($passphrase);

        $initialStats = $this->encryptionManager->getStats();
        $this->encryptionManager->encryptPacket($testData, $sequenceNumber);
        $updatedStats = $this->encryptionManager->getStats();

        $this->assertIsInt($initialStats['encrypted_packets']);
        $this->assertIsInt($updatedStats['encrypted_packets']);
        $this->assertIsInt($initialStats['key_usage_count']);
        $this->assertIsInt($updatedStats['key_usage_count']);

        $this->assertSame($initialStats['encrypted_packets'] + 1, $updatedStats['encrypted_packets']);
        $this->assertSame($initialStats['key_usage_count'] + 1, $updatedStats['key_usage_count']);
    }

    public function testResetStats(): void
    {
        $testData = 'test packet data';
        $sequenceNumber = 123;
        $passphrase = 'test-passphrase';

        $this->encryptionManager->enableEncryption($passphrase);
        $this->encryptionManager->encryptPacket($testData, $sequenceNumber);

        $stats = $this->encryptionManager->getStats();
        $this->assertGreaterThan(0, $stats['encrypted_packets']);

        $this->encryptionManager->resetStats();
        $resetStats = $this->encryptionManager->getStats();

        $this->assertSame(0, $resetStats['encrypted_packets']);
        $this->assertSame(0, $resetStats['decrypted_packets']);
        $this->assertSame(0, $resetStats['key_refreshes']);
        $this->assertSame(0, $resetStats['encryption_errors']);
        $this->assertSame(0, $resetStats['decryption_errors']);
    }

    public function testValidateConfig(): void
    {
        try {
            $result = $this->encryptionManager->validateConfig();
            $this->assertTrue($result);
        } catch (CryptoException $e) {
            $this->assertStringContainsString('系统不支持加密算法', $e->getMessage());
        }
    }

    public function testValidateConfigWithUnsupportedAlgorithm(): void
    {
        $this->expectException(CryptoException::class);
        $this->expectExceptionMessage('不支持的加密算法');

        new EncryptionManager('INVALID-ALGORITHM');
    }

    public function testConstructorWithPassphrase(): void
    {
        $passphrase = 'test-passphrase';
        $manager = new EncryptionManager(EncryptionManager::ALGO_AES_256, $passphrase);

        $this->assertTrue($manager->isEncryptionEnabled());
        $this->assertSame(EncryptionManager::ALGO_AES_256, $manager->getAlgorithm());
    }

    public function testGetAlgorithm(): void
    {
        $this->assertSame(EncryptionManager::ALGO_AES_256, $this->encryptionManager->getAlgorithm());

        $manager128 = new EncryptionManager(EncryptionManager::ALGO_AES_128);
        $this->assertSame(EncryptionManager::ALGO_AES_128, $manager128->getAlgorithm());
    }

    public function testSetKeyRefreshInterval(): void
    {
        $initialStats = $this->encryptionManager->getStats();
        $this->assertSame(1000000, $initialStats['key_refresh_interval']);

        $this->encryptionManager->setKeyRefreshInterval(5000);
        $updatedStats = $this->encryptionManager->getStats();
        $this->assertSame(5000, $updatedStats['key_refresh_interval']);
    }

    public function testSetKeyRefreshIntervalMinimumValue(): void
    {
        $this->encryptionManager->setKeyRefreshInterval(500);
        $stats = $this->encryptionManager->getStats();
        $this->assertSame(1000, $stats['key_refresh_interval']);
    }

    public function testGetKeyUsageCount(): void
    {
        $this->assertSame(0, $this->encryptionManager->getKeyUsageCount());

        $this->encryptionManager->enableEncryption('test-passphrase');
        $this->encryptionManager->encryptPacket('test data', 1);

        $this->assertSame(1, $this->encryptionManager->getKeyUsageCount());
    }

    public function testGetStats(): void
    {
        $stats = $this->encryptionManager->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('algorithm', $stats);
        $this->assertArrayHasKey('encryption_enabled', $stats);
        $this->assertArrayHasKey('key_usage_count', $stats);
        $this->assertArrayHasKey('key_refresh_interval', $stats);
        $this->assertArrayHasKey('encrypted_packets', $stats);
        $this->assertArrayHasKey('decrypted_packets', $stats);
        $this->assertArrayHasKey('key_refreshes', $stats);
        $this->assertArrayHasKey('encryption_errors', $stats);
        $this->assertArrayHasKey('decryption_errors', $stats);
    }
}
