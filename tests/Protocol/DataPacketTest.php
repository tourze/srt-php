<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Protocol;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Protocol\DataPacket;
use Tourze\SRT\Exception\InvalidPacketException;

class DataPacketTest extends TestCase
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
        $packet = new DataPacket();
        $packet->setSequenceNumber(12345);
        $this->assertEquals(12345, $packet->getSequenceNumber());
    }

    public function testSetAndGetPayload(): void
    {
        $packet = new DataPacket();
        $payload = 'test payload data';
        $packet->setPayload($payload);
        $this->assertEquals($payload, $packet->getPayload());
        $this->assertEquals(strlen($payload), $packet->getPayloadLength());
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