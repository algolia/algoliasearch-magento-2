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

    protected function resolveMaxPriceKey(?int $groupId = null): string
    {
        return $groupId === null ?
            PDI::PREFIX_DEFAULT . PDI::SUFFIX_MAX :
            PDI::PREFIX_GROUP.  $groupId . PDI::SUFFIX_MAX;
    }

    protected function resolveFormatedPriceKey(?int $groupId = null): string
    {
        return $groupId === null ?
            PDI::PREFIX_DEFAULT . PDI::SUFFIX_FORMATED:
            PDI::PREFIX_GROUP . $groupId . PDI::SUFFIX_FORMATED;
    }

    protected function resolveFormatedOriginalPriceKey(?int $groupId = null): string
    {
        return $groupId === null ?
            PDI::PREFIX_DEFAULT . PDI::SUFFIX_ORIGINAL . PDI::SUFFIX_FORMATED:
            PDI::PREFIX_GROUP . $groupId . PDI::SUFFIX_ORIGINAL . PDI::SUFFIX_FORMATED;
    }

    protected function resolveFormatedTierPriceKey(?int $groupId = null): string
    {
        return $groupId === null ?
            PDI::PREFIX_DEFAULT . PDI::SUFFIX_TIER . PDI::SUFFIX_FORMATED:
            PDI::PREFIX_GROUP . $groupId . PDI::SUFFIX_TIER . PDI::SUFFIX_FORMATED;
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

    public function setMaxPrice(mixed $price, ?int $groupId = null): void
    {
        $this->setData($this->resolveMaxPriceKey($groupId), $price);
    }

    public function setFormatedPrice(mixed $formatedPrice, ?int $groupId = null): void
    {
        $this->setData($this->resolveFormatedPriceKey($groupId), $formatedPrice);
    }

    public function setFormatedOriginalPrice(mixed $formatedPrice, ?int $groupId = null): void
    {
        $this->setData($this->resolveFormatedOriginalPriceKey($groupId), $formatedPrice);
    }

    public function setFormatedTierPrice(mixed $formatedPrice, ?int $groupId = null): void
    {
        $this->setData($this->resolveFormatedTierPriceKey($groupId), $formatedPrice);
    }

    public function setSpecialFromDate(mixed $date): void
    {
        $this->setData(PDI::SPECIAL_FROM_DATE, $date);
    }

    public function setSpecialToDate(mixed $date): void
    {
        $this->setData(PDI::SPECIAL_TO_DATE, $date);
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

    public function getMaxPrice(?int $groupId = null): mixed
    {
        $key = $this->resolveMaxPriceKey($groupId);

        return $this->hasData($key) ? $this->getData($key) : "";
    }

    public function getFormatedPrice(?int $groupId = null): mixed
    {
        $key = $this->resolveFormatedPriceKey($groupId);

        return $this->hasData($key) ? $this->getData($key) : "";
    }

    public function getFormatedOriginalPrice(?int $groupId = null): mixed
    {
        $key = $this->resolveFormatedOriginalPriceKey($groupId);

        return $this->hasData($key) ? $this->getData($key) : "";
    }

    public function getFormatedTierPrice(?int $groupId = null): mixed
    {
        $key = $this->resolveFormatedTierPriceKey($groupId);

        return $this->hasData($key) ? $this->getData($key) : "";
    }

    public function getSpecialFromDate(): mixed
    {
        return $this->getData(PDI::SPECIAL_FROM_DATE);
    }

    public function getSpecialToDate(): mixed
    {
        return $this->getData(PDI::SPECIAL_TO_DATE);
    }
}
