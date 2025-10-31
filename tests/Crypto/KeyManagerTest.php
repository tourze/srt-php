<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Crypto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\SRT\Crypto\KeyManager;

/**
 * @internal
 */
#[CoversClass(KeyManager::class)]
final class KeyManagerTest extends TestCase
{
    private KeyManager $keyManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyManager = new KeyManager();
    }

    public function testClassExists(): void
    {
        $this->assertInstanceOf(KeyManager::class, $this->keyManager);
        $this->assertIsArray($this->keyManager->getStats());
    }

    public function testClearAllKeys(): void
    {
        $initialStats = $this->keyManager->getStats();
        $this->assertSame(0, $initialStats['stored_keys']);
        $this->assertSame(0, $initialStats['stored_salts']);

        $this->keyManager->clearAllKeys();

        $stats = $this->keyManager->getStats();
        $this->assertSame(0, $stats['stored_keys']);
        $this->assertSame(0, $stats['stored_salts']);
    }

    public function testGenerateKeyPair(): void
    {
        $keyPair = $this->keyManager->generateKeyPair();

        $this->assertIsArray($keyPair);
        $this->assertArrayHasKey('public_key', $keyPair);
        $this->assertArrayHasKey('private_key', $keyPair);
        $this->assertArrayHasKey('keypair', $keyPair);

        $this->assertIsString($keyPair['public_key']);
        $this->assertIsString($keyPair['private_key']);
        $this->assertIsString($keyPair['keypair']);

        $this->assertSame(32, strlen($keyPair['public_key']));
        $this->assertSame(32, strlen($keyPair['private_key']));
        $this->assertSame(64, strlen($keyPair['keypair']));
    }

    public function testGenerateKeyPairUniqueness(): void
    {
        $keyPair1 = $this->keyManager->generateKeyPair();
        $keyPair2 = $this->keyManager->generateKeyPair();

        $this->assertNotSame($keyPair1['public_key'], $keyPair2['public_key']);
        $this->assertNotSame($keyPair1['private_key'], $keyPair2['private_key']);
        $this->assertNotSame($keyPair1['keypair'], $keyPair2['keypair']);
    }

    public function testGenerateSalt(): void
    {
        $salt = $this->keyManager->generateSalt();

        $this->assertIsString($salt);
        $this->assertSame(16, strlen($salt));
    }

    public function testGenerateSaltWithCustomLength(): void
    {
        $customLength = 32;
        $salt = $this->keyManager->generateSalt($customLength);

        $this->assertIsString($salt);
        $this->assertSame($customLength, strlen($salt));
    }

    public function testGenerateSaltUniqueness(): void
    {
        $salt1 = $this->keyManager->generateSalt();
        $salt2 = $this->keyManager->generateSalt();

        $this->assertNotSame($salt1, $salt2);
    }

    public function testGenerateSaltUpdatesStats(): void
    {
        $initialStats = $this->keyManager->getStats();
        $initialSaltCount = $initialStats['salt_generated'];

        $this->keyManager->generateSalt();

        $updatedStats = $this->keyManager->getStats();
        $this->assertSame($initialSaltCount + 1, (int) $updatedStats['salt_generated']);
    }

    public function testGetKey(): void
    {
        $keyId = 'test-key';

        $result = $this->keyManager->getKey($keyId);
        $this->assertNull($result);
    }

    public function testGetSalt(): void
    {
        $saltId = 'test-salt';

        $result = $this->keyManager->getSalt($saltId);
        $this->assertNull($result);
    }

    public function testGetStats(): void
    {
        $stats = $this->keyManager->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('stored_keys', $stats);
        $this->assertArrayHasKey('stored_salts', $stats);
        $this->assertArrayHasKey('keys_generated', $stats);
        $this->assertArrayHasKey('keys_rotated', $stats);
        $this->assertArrayHasKey('salt_generated', $stats);

        $this->assertSame(0, $stats['stored_keys']);
        $this->assertSame(0, $stats['stored_salts']);
        $this->assertSame(0, $stats['keys_generated']);
        $this->assertSame(0, $stats['keys_rotated']);
        $this->assertSame(0, $stats['salt_generated']);
    }

    public function testMultipleSaltGeneration(): void
    {
        $initialStats = $this->keyManager->getStats();

        $this->keyManager->generateSalt();
        $this->keyManager->generateSalt();
        $this->keyManager->generateSalt();

        $updatedStats = $this->keyManager->getStats();
        $this->assertSame((int) $initialStats['salt_generated'] + 3, (int) $updatedStats['salt_generated']);
    }

    public function testGenerateSaltWithMinimumLength(): void
    {
        $salt = $this->keyManager->generateSalt(1);

        $this->assertIsString($salt);
        $this->assertSame(1, strlen($salt));
    }

    public function testGenerateSaltWithLargeLength(): void
    {
        $largeLength = 1024;
        $salt = $this->keyManager->generateSalt($largeLength);

        $this->assertIsString($salt);
        $this->assertSame($largeLength, strlen($salt));
    }
}
