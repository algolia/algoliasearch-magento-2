<?php

namespace Algolia\AlgoliaSearch\Api\Data;

interface PriceDataInterface
{
    public const PREFIX_DEFAULT = 'default';
    public const PREFIX_GROUP = 'group_';
    public const SUFFIX_FORMATED = '_formated';
    public const SUFFIX_TIER = '_tier';
    public const SUFFIX_ORIGINAL = '_original';
    public const SUFFIX_MAX = '_max';
    public const SPECIAL_FROM_DATE = 'special_from_date';
    public const SPECIAL_TO_DATE = 'special_to_date';

    /**
     *  ********* SETTERS ********
     */

    /**
     * set raw price (default if no customer group is specified)
     */
    public function setPrice(float $price, ?int $groupId = null): void;

    /**
     * set tier price (default if no customer group is specified)
     */
    public function setTierPrice(float $price, ?int $groupId = null): void;

    /**
     * set max price (default if no customer group is specified)
     */
    public function setMaxPrice(mixed $price, ?int $groupId = null): void;

    /**
     * set formated price (default if no customer group is specified)
     */
    public function setFormatedPrice(mixed $formatedPrice, ?int $groupId = null): void;

    /**
     * set original formated price (default if no customer group is specified)
     */
    public function setFormatedOriginalPrice(mixed $formatedPrice, ?int $groupId = null): void;

    /**
     * set tier formated price (default if no customer group is specified)
     */
    public function setFormatedTierPrice(mixed $formatedPrice, ?int $groupId = null): void;

    /**
     * set special from date
     */
    public function setSpecialFromDate(mixed $date): void;

    /**
     * set special to date
     */
    public function setSpecialToDate(mixed $date): void;

    /**
     *  ********* GETTERS ********
     */

    /**
     * get raw price (default if no customer group is specified)
     */
    public function getPrice(?int $groupId = null): float;

    /**
     * get tier price (default if no customer group is specified)
     */
    public function getTierPrice(?int $groupId = null): float;

    /**
     * get max price (default if no customer group is specified)
     */
    public function getMaxPrice(?int $groupId = null): mixed;

    /**
     * get formated price (default if no customer group is specified)
     */
    public function getFormatedPrice(?int $groupId = null): mixed;

    /**
     * get original formated price (default if no customer group is specified)
     */
    public function getFormatedOriginalPrice(?int $groupId = null): mixed;

    /**
     * get tier formated price (default if no customer group is specified)
     */
    public function getFormatedTierPrice(?int $groupId = null): mixed;

    /**
     * get special from date
     */
    public function getSpecialFromDate(): mixed;

    /**
     * get special to date
     */
    public function getSpecialToDate(): mixed;
}
