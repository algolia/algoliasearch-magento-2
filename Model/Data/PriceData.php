<?php

namespace Algolia\AlgoliaSearch\Model\Data;

use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface as PDI;
use Magento\Framework\DataObject;

class PriceData extends DataObject implements PDI
{
    /**
     *  ********* KEY RESOLVERS ********
     */
    protected function resolvePriceKey(?int $groupId = null): string
    {
        return $groupId === null ? PDI::PREFIX_DEFAULT : PDI::PREFIX_GROUP . $groupId;
    }

    protected function resolveTierPriceKey(?int $groupId = null): string
    {
        return $groupId === null ?
            PDI::PREFIX_DEFAULT . PDI::SUFFIX_TIER :
            PDI::PREFIX_GROUP.  $groupId . PDI::SUFFIX_TIER;
    }

    protected function resolveOriginalPriceKey(?int $groupId = null): string
    {
        return $groupId === null ?
            PDI::PREFIX_DEFAULT . PDI::SUFFIX_ORIGINAL :
            PDI::PREFIX_GROUP.  $groupId . PDI::SUFFIX_ORIGINAL;
    }

    protected function resolveMaxPriceKey(?int $groupId = null): string
    {
        return $groupId === null ?
            PDI::PREFIX_DEFAULT . PDI::SUFFIX_MAX :
            PDI::PREFIX_GROUP.  $groupId . PDI::SUFFIX_MAX;
    }

    protected function resolveMinPriceKey(?int $groupId = null): string
    {
        return $groupId === null ?
            PDI::PREFIX_DEFAULT . PDI::SUFFIX_MIN :
            PDI::PREFIX_GROUP.  $groupId . PDI::SUFFIX_MIN;
    }

    protected function resolveFormattedPriceKey(?int $groupId = null): string
    {
        return $groupId === null ?
            PDI::PREFIX_DEFAULT . PDI::SUFFIX_FORMATTED:
            PDI::PREFIX_GROUP . $groupId . PDI::SUFFIX_FORMATTED;
    }

    protected function resolveFormattedOriginalPriceKey(?int $groupId = null): string
    {
        return $groupId === null ?
            PDI::PREFIX_DEFAULT . PDI::SUFFIX_ORIGINAL . PDI::SUFFIX_FORMATTED:
            PDI::PREFIX_GROUP . $groupId . PDI::SUFFIX_ORIGINAL . PDI::SUFFIX_FORMATTED;
    }

    protected function resolveFormattedTierPriceKey(?int $groupId = null): string
    {
        return $groupId === null ?
            PDI::PREFIX_DEFAULT . PDI::SUFFIX_TIER . PDI::SUFFIX_FORMATTED:
            PDI::PREFIX_GROUP . $groupId . PDI::SUFFIX_TIER . PDI::SUFFIX_FORMATTED;
    }

    /**
     *  ********* SETTERS ********
     */
    public function setPrice(float $price, ?int $groupId = null): void
    {
        $this->setData($this->resolvePriceKey($groupId), $price);
    }

    public function setTierPrice(float $price, ?int $groupId = null): void
    {
        $this->setData($this->resolveTierPriceKey($groupId), $price);
    }

    public function setOriginalPrice(float $price, ?int $groupId = null): void
    {
        $this->setData($this->resolveOriginalPriceKey($groupId), $price);
    }

    public function setMaxPrice(float $price, ?int $groupId = null): void
    {
        $this->setData($this->resolveMaxPriceKey($groupId), $price);
    }

    public function setMinPrice(float $price, ?int $groupId = null): void
    {
        $this->setData($this->resolveMinPriceKey($groupId), $price);
    }

    public function setFormattedPrice(string $formattedPrice, ?int $groupId = null): void
    {
        $this->setData($this->resolveFormattedPriceKey($groupId), $formattedPrice);
    }

    public function setFormattedOriginalPrice(string $formattedPrice, ?int $groupId = null): void
    {
        $this->setData($this->resolveFormattedOriginalPriceKey($groupId), $formattedPrice);
    }

    public function setFormattedTierPrice(string $formattedPrice, ?int $groupId = null): void
    {
        $this->setData($this->resolveFormattedTierPriceKey($groupId), $formattedPrice);
    }

    public function setSpecialFromDate(int|string $date): void
    {
        $this->setData(PDI::SPECIAL_FROM_DATE, $date);
    }

    public function setSpecialToDate(int|string $date): void
    {
        $this->setData(PDI::SPECIAL_TO_DATE, $date);
    }

    /**
     *  ********* UNSETTERS ********
     */
    public function unsetMaxPrice(?int $groupId = null): void
    {
        $this->unsetData($this->resolveMaxPriceKey($groupId));
    }

    public function unsetMinPrice(?int $groupId = null): void
    {
        $this->unsetData($this->resolveMinPriceKey($groupId));
    }

    /**
     *  ********* GETTERS ********
     */
    public function getPrice(?int $groupId = null): float
    {
        $key = $this->resolvePriceKey($groupId);

        return $this->hasData($key) ? (float) $this->getData($key) : 0.00;
    }

    public function getTierPrice(?int $groupId = null): float
    {
        $key = $this->resolveTierPriceKey($groupId);

        return $this->hasData($key) ? (float) $this->getData($key) : 0.00;
    }

    public function getMaxPrice(?int $groupId = null): float
    {
        $key = $this->resolveMaxPriceKey($groupId);

        return $this->hasData($key) ? $this->getData($key) : 0.00;
    }

    public function getMinPrice(?int $groupId = null): float
    {
        $key = $this->resolveMinPriceKey($groupId);

        return $this->hasData($key) ? $this->getData($key) : 0.00;
    }

    public function getOriginalPrice(?int $groupId = null): float
    {
        $key = $this->resolveOriginalPriceKey($groupId);

        return $this->hasData($key) ? $this->getData($key) : 0.00;
    }

    public function getFormattedPrice(?int $groupId = null): string
    {
        $key = $this->resolveFormattedPriceKey($groupId);

        return $this->hasData($key) ? $this->getData($key) : "";
    }

    public function getFormattedOriginalPrice(?int $groupId = null): string
    {
        $key = $this->resolveFormattedOriginalPriceKey($groupId);

        return $this->hasData($key) ? $this->getData($key) : "";
    }

    public function getFormattedTierPrice(?int $groupId = null): string
    {
        $key = $this->resolveFormattedTierPriceKey($groupId);

        return $this->hasData($key) ? $this->getData($key) : "";
    }

    public function getSpecialFromDate(): false|int
    {
        return $this->getData(PDI::SPECIAL_FROM_DATE);
    }

    public function getSpecialToDate(): false|int
    {
        return $this->getData(PDI::SPECIAL_TO_DATE);
    }
}
