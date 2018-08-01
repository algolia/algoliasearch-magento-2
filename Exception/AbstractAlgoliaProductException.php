<?php
/**
 * Module Algolia Algoliasearch
 */
namespace Algolia\AlgoliaSearch\Exception;

use Magento\Catalog\Model\Product;

/**
 * Class: AbstractAlgoliaProductException
 */
abstract class AbstractAlgoliaProductException extends \Exception
{
    /**
     * @var Product
     */
    protected $product;

    /**
     * @var int
     */
    protected $storeId;

    /**
     * Add related product
     *
     * @param Product $product
     *
     * @return $this
     */
    public function withProduct($product)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Add related store id
     *
     * @param int $storeId
     *
     * @return $this
     */
    public function withStoreId($storeId)
    {
        $this->storeId = $storeId;

        return $this;
    }

    /**
     * Get related product
     *
     * @return Product
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * Get related store id
     *
     * @return int
     */
    public function getStoreId()
    {
        return $this->storeId;
    }
}
