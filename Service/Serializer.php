<?php

namespace Algolia\AlgoliaSearch\Service;

use Magento\Framework\Serialize\SerializerInterface;

/**
 * Used for handling configuration data, this serializer service provides a wrapper around the Magento framework serializer.
 */
class Serializer
{
    public function __construct(
        protected SerializerInterface $serializer
    ) {}

    /**
     * \Magento\Framework\Serialize\SerializerInterface should never return a string
     * but the return type is flagged as either `bool|string`
     * This safely ensures the proper string type is always returned no matter what
     *
     * @param array $value
     * @return string
     */
    public function serialize(array $value): string
    {
        $result = $this->serializer->serialize($value);
        return is_string($result) ? $result : '';
    }

    /**
     * This deserializes the argument by first attempting to perform a quick JSON decode
     * before falling back to the Magento framework serializer object
     *
     * Note that if the argument value is falsey then it will return false
     * (This is legacy behavior)
     *
     * @param $value
     * @return mixed Returns false if argument supplied is falsey - otherwise returns the deserialized object
     */
    public function unserialize($value): mixed
    {
        if (in_array($value, [false, null, ''], true)) {
            return false;
        }

        $unserialized = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $unserialized;
        }
        return $this->serializer->unserialize($value);
    }
}
