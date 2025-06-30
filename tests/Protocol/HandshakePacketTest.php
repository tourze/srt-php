<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Protocol;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Protocol\HandshakePacket;
use Tourze\SRT\Exception\InvalidPacketException;

class HandshakePacketTest extends TestCase
{
    public function testInitialState(): void
    {
        $packet = new HandshakePacket();
        $this->assertEquals(0, $packet->getVersion());
        $this->assertEquals(0, $packet->getEncryptionField());
        $this->assertEquals('', $packet->getPeerIpAddress());
    }

    public function testSetAndGetVersion(): void
    {
        $packet = new HandshakePacket();
        $packet->setVersion(4);
        $this->assertEquals(4, $packet->getVersion());
    }

    public function testSetAndGetPeerIpAddress(): void
    {
        $packet = new HandshakePacket();
        $packet->setPeerIpAddress('192.168.1.1');
        $this->assertEquals('192.168.1.1', $packet->getPeerIpAddress());
    }

    public function testSerializeAndDeserialize(): void
    {
        $original = new HandshakePacket();
        $original->setVersion(4);
        $original->setInitialSequenceNumber(12345);
        $original->setSrtSocketId(67890);
        
        $serialized = $original->serialize();
        $deserialized = HandshakePacket::deserialize($serialized);
        
        $this->assertEquals(4, $deserialized->getVersion());
        $this->assertEquals(12345, $deserialized->getInitialSequenceNumber());
        $this->assertEquals(67890, $deserialized->getSrtSocketId());
    }

    public function testDeserializeTooShort(): void
    {
        $this->expectException(InvalidPacketException::class);
        HandshakePacket::deserialize('short');
    }
} 