<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Data;

use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Model\Data\PriceData;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PriceDataTest extends TestCase
{
    protected function createObjectToTest(): PriceData
    {
        return new PriceData();
    }

    /**
     * @return array<string, array{float, int|null}>
     */
    public static function priceDataProvider(): array
    {
        return [
            'default price' => [99.99, null],
            'group price for group 1' => [89.99, 1],
            'group price for group 0' => [79.99, 0],
            'group price for group 5' => [69.99, 5],
        ];
    }

    #[DataProvider('priceDataProvider')]
    public function testSetAndGetPriceStoresAndRetrievesCorrectValue(
        float $price,
        ?int $groupId
    ): void {
        $priceData = $this->createObjectToTest();

        $priceData->setPrice($price, $groupId);

        $this->assertSame($price, $priceData->getPrice($groupId));
    }

    #[DataProvider('priceDataProvider')]
    public function testSetAndGetTierPriceStoresAndRetrievesCorrectValue(
        float $price,
        ?int $groupId
    ): void {
        $priceData = $this->createObjectToTest();

        $priceData->setTierPrice($price, $groupId);

        $this->assertSame($price, $priceData->getTierPrice($groupId));
    }

    #[DataProvider('priceDataProvider')]
    public function testSetAndGetOriginalPriceStoresAndRetrievesCorrectValue(
        float $price,
        ?int $groupId
    ): void {
        $priceData = $this->createObjectToTest();

        $priceData->setOriginalPrice($price, $groupId);

        $this->assertSame($price, $priceData->getOriginalPrice($groupId));
    }

    #[DataProvider('priceDataProvider')]
    public function testSetAndGetMaxPriceStoresAndRetrievesCorrectValue(
        float $price,
        ?int $groupId
    ): void {
        $priceData = $this->createObjectToTest();

        $priceData->setMaxPrice($price, $groupId);

        $this->assertSame($price, $priceData->getMaxPrice($groupId));
    }

    #[DataProvider('priceDataProvider')]
    public function testSetAndGetMinPriceStoresAndRetrievesCorrectValue(
        float $price,
        ?int $groupId
    ): void {
        $priceData = $this->createObjectToTest();

        $priceData->setMinPrice($price, $groupId);

        $this->assertSame($price, $priceData->getMinPrice($groupId));
    }

    public function testGetPriceReturnsZeroWhenNotSet(): void
    {
        $priceData = $this->createObjectToTest();

        $this->assertSame(0.00, $priceData->getPrice());
    }

    public function testGetTierPriceReturnsZeroWhenNotSet(): void
    {
        $priceData = $this->createObjectToTest();

        $this->assertSame(0.00, $priceData->getTierPrice());
    }

    public function testGetOriginalPriceReturnsZeroWhenNotSet(): void
    {
        $priceData = $this->createObjectToTest();

        $this->assertSame(0.00, $priceData->getOriginalPrice());
    }

    public function testGetMaxPriceReturnsZeroWhenNotSet(): void
    {
        $priceData = $this->createObjectToTest();

        $this->assertSame(0.00, $priceData->getMaxPrice());
    }

    public function testGetMinPriceReturnsZeroWhenNotSet(): void
    {
        $priceData = $this->createObjectToTest();

        $this->assertSame(0.00, $priceData->getMinPrice());
    }

    public function testGetPriceReturnsZeroForUnsetGroupId(): void
    {
        $priceData = $this->createObjectToTest();

        $priceData->setPrice(99.99, null);

        $this->assertSame(0.00, $priceData->getPrice(1));
    }

    public function testGetTierPriceReturnsZeroForUnsetGroupId(): void
    {
        $priceData = $this->createObjectToTest();

        $priceData->setTierPrice(89.99, null);

        $this->assertSame(0.00, $priceData->getTierPrice(1));
    }

    /**
     * @return array<string, array{string, int|null}>
     */
    public static function formattedPriceDataProvider(): array
    {
        return [
            'default formatted price' => ['$99.99', null],
            'group formatted price for group 1' => ['$89.99', 1],
            'group formatted price for group 0' => ['$79.99', 0],
            'empty formatted price' => ['', null],
        ];
    }

    #[DataProvider('formattedPriceDataProvider')]
    public function testSetAndGetFormattedPriceStoresAndRetrievesCorrectValue(
        string $formattedPrice,
        ?int $groupId
    ): void {
        $priceData = $this->createObjectToTest();

        $priceData->setFormattedPrice($formattedPrice, $groupId);

        $this->assertSame($formattedPrice, $priceData->getFormattedPrice($groupId));
    }

    #[DataProvider('formattedPriceDataProvider')]
    public function testSetAndGetFormattedOriginalPriceStoresAndRetrievesCorrectValue(
        string $formattedPrice,
        ?int $groupId
    ): void {
        $priceData = $this->createObjectToTest();

        $priceData->setFormattedOriginalPrice($formattedPrice, $groupId);

        $this->assertSame($formattedPrice, $priceData->getFormattedOriginalPrice($groupId));
    }

    #[DataProvider('formattedPriceDataProvider')]
    public function testSetAndGetFormattedTierPriceStoresAndRetrievesCorrectValue(
        string $formattedPrice,
        ?int $groupId
    ): void {
        $priceData = $this->createObjectToTest();

        $priceData->setFormattedTierPrice($formattedPrice, $groupId);

        $this->assertSame($formattedPrice, $priceData->getFormattedTierPrice($groupId));
    }

    public function testGetFormattedPriceReturnsEmptyStringWhenNotSet(): void
    {
        $priceData = $this->createObjectToTest();

        $this->assertSame('', $priceData->getFormattedPrice());
    }

    public function testGetFormattedOriginalPriceReturnsEmptyStringWhenNotSet(): void
    {
        $priceData = $this->createObjectToTest();

        $this->assertSame('', $priceData->getFormattedOriginalPrice());
    }

    public function testGetFormattedTierPriceReturnsEmptyStringWhenNotSet(): void
    {
        $priceData = $this->createObjectToTest();

        $this->assertSame('', $priceData->getFormattedTierPrice());
    }

    public function testGetFormattedPriceReturnsEmptyStringForUnsetGroupId(): void
    {
        $priceData = $this->createObjectToTest();

        $priceData->setFormattedPrice('$99.99', null);

        $this->assertSame('', $priceData->getFormattedPrice(1));
    }

    /**
     * @return array<string, array{int, int|false}>
     */
    public static function specialDateDataProvider(): array
    {
        return [
            'integer timestamp for from date' => [1234567890, 1234567890],
            'integer timestamp for to date' => [9876543210, 9876543210],
            'zero timestamp' => [0, 0],
        ];
    }

    #[DataProvider('specialDateDataProvider')]
    public function testSetAndGetSpecialFromDateStoresAndRetrievesCorrectValue(
        int $date,
        int|false $expected
    ): void {
        $priceData = $this->createObjectToTest();

        $priceData->setSpecialFromDate($date);

        $this->assertSame($expected, $priceData->getSpecialFromDate());
    }

    #[DataProvider('specialDateDataProvider')]
    public function testSetAndGetSpecialToDateStoresAndRetrievesCorrectValue(
        int $date,
        int|false $expected
    ): void {
        $priceData = $this->createObjectToTest();

        $priceData->setSpecialToDate($date);

        $this->assertSame($expected, $priceData->getSpecialToDate());
    }

    public function testGetSpecialFromDateReturnsFalseWhenNotSet(): void
    {
        $priceData = $this->createObjectToTest();

        $this->assertFalse($priceData->getSpecialFromDate());
    }

    public function testGetSpecialToDateReturnsFalseWhenNotSet(): void
    {
        $priceData = $this->createObjectToTest();

        $this->assertFalse($priceData->getSpecialToDate());
    }

    public function testUnsetMaxPriceRemovesDefaultMaxPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $priceData->setMaxPrice(199.99);
        $priceData->unsetMaxPrice();

        $this->assertSame(0.00, $priceData->getMaxPrice());
    }

    public function testUnsetMaxPriceRemovesGroupSpecificMaxPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $priceData->setMaxPrice(189.99, 1);
        $priceData->unsetMaxPrice(1);

        $this->assertSame(0.00, $priceData->getMaxPrice(1));
    }

    public function testUnsetMinPriceRemovesDefaultMinPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $priceData->setMinPrice(49.99);
        $priceData->unsetMinPrice();

        $this->assertSame(0.00, $priceData->getMinPrice());
    }

    public function testUnsetMinPriceRemovesGroupSpecificMinPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $priceData->setMinPrice(39.99, 1);
        $priceData->unsetMinPrice(1);

        $this->assertSame(0.00, $priceData->getMinPrice(1));
    }

    public function testUnsetMaxPriceDoesNotAffectOtherGroupPrices(): void
    {
        $priceData = $this->createObjectToTest();

        $priceData->setMaxPrice(199.99);
        $priceData->setMaxPrice(189.99, 1);

        $priceData->unsetMaxPrice(1);

        $this->assertSame(199.99, $priceData->getMaxPrice());
        $this->assertSame(0.00, $priceData->getMaxPrice(1));
    }

    public function testUnsetMinPriceDoesNotAffectOtherGroupPrices(): void
    {
        $priceData = $this->createObjectToTest();

        $priceData->setMinPrice(49.99);
        $priceData->setMinPrice(39.99, 1);

        $priceData->unsetMinPrice(1);

        $this->assertSame(49.99, $priceData->getMinPrice());
        $this->assertSame(0.00, $priceData->getMinPrice(1));
    }

    public function testMultipleGroupPricesCanCoexist(): void
    {
        $priceData = $this->createObjectToTest();

        $priceData->setPrice(99.99);
        $priceData->setPrice(89.99, 1);
        $priceData->setPrice(79.99, 2);

        $this->assertSame(99.99, $priceData->getPrice());
        $this->assertSame(89.99, $priceData->getPrice(1));
        $this->assertSame(79.99, $priceData->getPrice(2));
    }

    public function testKeyResolutionForDefaultPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolvePriceKey', [null]);

        $this->assertSame(PriceDataInterface::PREFIX_DEFAULT, $key);
    }

    public function testKeyResolutionForGroupPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolvePriceKey', [5]);

        $this->assertSame(PriceDataInterface::PREFIX_GROUP . '5', $key);
    }

    public function testKeyResolutionForDefaultTierPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolveTierPriceKey', [null]);

        $this->assertSame(
            PriceDataInterface::PREFIX_DEFAULT . PriceDataInterface::SUFFIX_TIER,
            $key
        );
    }

    public function testKeyResolutionForGroupTierPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolveTierPriceKey', [3]);

        $this->assertSame(
            PriceDataInterface::PREFIX_GROUP . '3' . PriceDataInterface::SUFFIX_TIER,
            $key
        );
    }

    public function testKeyResolutionForDefaultOriginalPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolveOriginalPriceKey', [null]);

        $this->assertSame(
            PriceDataInterface::PREFIX_DEFAULT . PriceDataInterface::SUFFIX_ORIGINAL,
            $key
        );
    }

    public function testKeyResolutionForGroupOriginalPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolveOriginalPriceKey', [2]);

        $this->assertSame(
            PriceDataInterface::PREFIX_GROUP . '2' . PriceDataInterface::SUFFIX_ORIGINAL,
            $key
        );
    }

    public function testKeyResolutionForDefaultMaxPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolveMaxPriceKey', [null]);

        $this->assertSame(
            PriceDataInterface::PREFIX_DEFAULT . PriceDataInterface::SUFFIX_MAX,
            $key
        );
    }

    public function testKeyResolutionForGroupMaxPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolveMaxPriceKey', [4]);

        $this->assertSame(
            PriceDataInterface::PREFIX_GROUP . '4' . PriceDataInterface::SUFFIX_MAX,
            $key
        );
    }

    public function testKeyResolutionForDefaultMinPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolveMinPriceKey', [null]);

        $this->assertSame(
            PriceDataInterface::PREFIX_DEFAULT . PriceDataInterface::SUFFIX_MIN,
            $key
        );
    }

    public function testKeyResolutionForGroupMinPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolveMinPriceKey', [7]);

        $this->assertSame(
            PriceDataInterface::PREFIX_GROUP . '7' . PriceDataInterface::SUFFIX_MIN,
            $key
        );
    }

    public function testKeyResolutionForDefaultFormattedPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolveFormattedPriceKey', [null]);

        $this->assertSame(
            PriceDataInterface::PREFIX_DEFAULT . PriceDataInterface::SUFFIX_FORMATTED,
            $key
        );
    }

    public function testKeyResolutionForGroupFormattedPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolveFormattedPriceKey', [6]);

        $this->assertSame(
            PriceDataInterface::PREFIX_GROUP . '6' . PriceDataInterface::SUFFIX_FORMATTED,
            $key
        );
    }

    public function testKeyResolutionForDefaultFormattedOriginalPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolveFormattedOriginalPriceKey', [null]);

        $this->assertSame(
            PriceDataInterface::PREFIX_DEFAULT . PriceDataInterface::SUFFIX_ORIGINAL . PriceDataInterface::SUFFIX_FORMATTED,
            $key
        );
    }

    public function testKeyResolutionForGroupFormattedOriginalPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolveFormattedOriginalPriceKey', [8]);

        $this->assertSame(
            PriceDataInterface::PREFIX_GROUP . '8' . PriceDataInterface::SUFFIX_ORIGINAL . PriceDataInterface::SUFFIX_FORMATTED,
            $key
        );
    }

    public function testKeyResolutionForDefaultFormattedTierPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolveFormattedTierPriceKey', [null]);

        $this->assertSame(
            PriceDataInterface::PREFIX_DEFAULT . PriceDataInterface::SUFFIX_TIER . PriceDataInterface::SUFFIX_FORMATTED,
            $key
        );
    }

    public function testKeyResolutionForGroupFormattedTierPrice(): void
    {
        $priceData = $this->createObjectToTest();

        $key = $this->invokeMethod($priceData, 'resolveFormattedTierPriceKey', [9]);

        $this->assertSame(
            PriceDataInterface::PREFIX_GROUP . '9' . PriceDataInterface::SUFFIX_TIER . PriceDataInterface::SUFFIX_FORMATTED,
            $key
        );
    }
}
