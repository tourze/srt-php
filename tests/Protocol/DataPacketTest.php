<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Protocol;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\SRT\Exception\InvalidPacketException;
use Tourze\SRT\Protocol\DataPacket;

/**
 * @internal
 */
#[CoversClass(DataPacket::class)]
final class DataPacketTest extends TestCase
{
    public function testInitialState(): void
    {
        $packet = new DataPacket();
        $this->assertEquals(0, $packet->getSequenceNumber());
        $this->assertEquals(0, $packet->getMessageNumber());
        $this->assertEquals('', $packet->getPayload());
    }

    public function testSetAndGetSequenceNumber(): void
    {
        // 测试通过构造函数设置序列号
        $packet = new DataPacket(12345);
        $this->assertEquals(12345, $packet->getSequenceNumber());

        // setSequenceNumber 方法现在不起作用，因为 sequenceNumber 是 readonly 的
        $packet->setSequenceNumber(54321);
        $this->assertEquals(12345, $packet->getSequenceNumber()); // 应该保持原值
    }

    public function testSetAndGetPayload(): void
    {
        // 测试通过构造函数设置载荷
        $payload = 'test payload data';
        $packet = new DataPacket(0, 0, $payload);
        $this->assertEquals($payload, $packet->getPayload());
        $this->assertEquals(strlen($payload), $packet->getPayloadLength());

        // setPayload 方法现在不起作用，因为 payload 是 readonly 的
        $newPayload = 'new payload';
        $packet->setPayload($newPayload);
        $this->assertEquals($payload, $packet->getPayload()); // 应该保持原值
    }

    public function testPacketPosition(): void
    {
        $packet = new DataPacket();
        $packet->setPacketPosition(DataPacket::PP_FIRST);
        $this->assertEquals(DataPacket::PP_FIRST, $packet->getPacketPosition());
        $this->assertTrue($packet->isFirstPacket());
        $this->assertFalse($packet->isSinglePacket());
    }

    public function testSerializeAndDeserialize(): void
    {
        $original = new DataPacket(12345, 67890, 'test data');
        $serialized = $original->serialize();
        $deserialized = DataPacket::deserialize($serialized);

        $this->assertEquals(12345, $deserialized->getSequenceNumber());
        $this->assertEquals(67890, $deserialized->getMessageNumber());
        $this->assertEquals('test data', $deserialized->getPayload());
    }

    public function testDeserializeTooShort(): void
    {
        $this->expectException(InvalidPacketException::class);
        DataPacket::deserialize('short');
    }
}
