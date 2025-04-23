<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Service\Serializer;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\TestCase;

class SerializerTest extends TestCase
{
    private ?SerializerInterface $serializerMock = null;
    protected ?Serializer $serializer = null;

    protected function setUp(): void
    {
        $this->serializerMock = $this->createMock(SerializerInterface::class);

        $this->serializer = new Serializer($this->serializerMock);
    }

    /**
     * Add data provider?
     * @return void
     */
    public function testSerializeReturnsString(): void
    {
        $unserialized = ['foo' => 'bar'];
        $this->serializerMock
            ->expects($this->once())
            ->method('serialize')
            ->with($unserialized)
            ->willReturn('{"foo":"bar"}');
        $result = $this->serializer->serialize($unserialized);
        $this->assertEquals('{"foo":"bar"}', $result);
    }

    public function testSerializeFailure(): void
    {
        $unserialized = [];
        $this->serializerMock
            ->expects($this->once())
            ->method('serialize')
            ->with($unserialized)
            ->willReturn(false);
        $result = $this->serializer->serialize($unserialized);
        $this->assertEquals('', $result);
    }

    public function testUnserializeReturnsFalseOnEmptyValues(): void
    {
        $this->assertFalse($this->serializer->unserialize(null));
        $this->assertFalse($this->serializer->unserialize(false));
        $this->assertFalse($this->serializer->unserialize(''));
    }

    public function testUnserializeHandlesValidJson(): void
    {
        $json = '{"key":"value"}';
        $this->assertEquals(['key' => 'value'], $this->serializer->unserialize($json));
    }

    public function testUnserializeFallsBackToSerializer(): void
    {
        $serialized = 'a:1:{s:3:"foo";s:3:"bar";}'; // PHP serialized data

        $this->serializerMock
            ->expects($this->once())
            ->method('unserialize')
            ->with($serialized)
            ->willReturn(['foo' => 'bar']);

        $this->assertEquals(['foo' => 'bar'], $this->serializer->unserialize($serialized));
    }

    public function testUnserializeFailsBothJsonAndSerializer(): void
    {
        $badData = 'not_serialized_at_all';

        $this->serializerMock
            ->expects($this->once())
            ->method('unserialize')
            ->with($badData)
            ->willThrowException(new \InvalidArgumentException());

        $this->expectException(\InvalidArgumentException::class);

        $this->serializer->unserialize($badData);
    }
}
