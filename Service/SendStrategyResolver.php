<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Api\SendStrategyInterface;

class SendStrategyResolver
{
    /**
     * @param SendStrategyInterface $defaultStrategy DirectSendStrategy - always-on fallback
     * @param SendStrategyInterface[] $strategies Optional strategies injected by satellite modules
     */
    public function __construct(
        private SendStrategyInterface $defaultStrategy,
        private array $strategies = []
    ) {}

    public function resolve(int $storeId): SendStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->isApplicable($storeId)) {
                return $strategy;
            }
        }

        return $this->defaultStrategy;
    }
}
