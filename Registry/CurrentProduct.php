<?php

namespace Algolia\AlgoliaSearch\Registry;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;

/**
 * Class CurrentProduct
 *
 * Get current product without registry
 */
class CurrentProduct
{
    /** @var ProductInterface */
    private $product;

    /** @var ProductInterfaceFactory */
    private $productFactory;

    /**
     * CurrentProduct constructor.
     *
     */
    public function __construct(
        ProductInterfaceFactory $productFactory
    )
    {
        $this->productFactory = $productFactory;
    }

    /**
     * Setter
     *
     */
    public function set(ProductInterface $product): void
    {
        $this->product = $product;
    }

    /**
     * Getter
     *
     */
    public function get(): ProductInterface
    {
        return $this->product ?? $this->createProduct();
    }

    /**
     * Product factory
     *
     */
    private function createProduct(): ProductInterface
    {
        return $this->productFactory->create();
    }
}
