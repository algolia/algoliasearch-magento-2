<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Ui\Component\Listing\Column\Store;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\AlgoliaSearch\Ui\Component\Listing\Column\Store\Options;
use Magento\Framework\Escaper;
use Magento\Store\Model\System\Store;

class OptionsTest extends TestCase
{
    private const QUEUE_LISTING_XML_PATH =
        '/../../../../../../../view/adminhtml/ui_component/algolia_algoliasearch_indexing_queue_listing.xml';

    public function testAllStoreViewsIsTheFirstOption(): void
    {
        $options = $this->createOptions()->toOptionArray();

        $this->assertNotEmpty($options);
        $this->assertSame(Options::ALL_STORE_VIEWS, (string) $options[0]['value']);
        $this->assertSame('All Store Views', (string) $options[0]['label']);
    }

    public function testQueueListingXmlCaptionValueIsPairedWithAllStoreViewsOption(): void
    {
        $xml = simplexml_load_file(__DIR__ . self::QUEUE_LISTING_XML_PATH);
        $this->assertNotFalse($xml, 'Failed to load algolia_algoliasearch_indexing_queue_listing.xml');

        $settings = $xml->xpath("//filterSelect[@name='store_id']/settings");
        $this->assertNotEmpty($settings, "The store_id filterSelect is missing from the queue listing XML");

        $this->assertSame(
            Options::ALL_STORE_VIEWS,
            (string) $settings[0]->captionValue,
            'captionValue must equal Options::ALL_STORE_VIEWS: the native select strips the option matching '
            . 'captionValue, so a drift makes the "0" option selectable and empties the grid (no queue row '
            . 'has store_id 0). See MAGE-1734.'
        );

        $this->assertSame(
            Options::class,
            (string) $settings[0]->options['class'],
            'The store_id filter must use the options class that owns ALL_STORE_VIEWS, otherwise the '
            . 'captionValue pairing no longer holds. See MAGE-1734.'
        );

        $optionValues = array_map(
            static fn (array $option): string => (string) $option['value'],
            $this->createOptions()->toOptionArray()
        );
        $this->assertContains(
            Options::ALL_STORE_VIEWS,
            $optionValues,
            'The option matching captionValue must exist in the rendered options, or the filter loses its '
            . 'empty-state caption. See MAGE-1734.'
        );
    }

    private function createOptions(): Options
    {
        $systemStore = $this->createStub(Store::class);
        $systemStore->method('getWebsiteCollection')->willReturn([]);
        $systemStore->method('getGroupCollection')->willReturn([]);
        $systemStore->method('getStoreCollection')->willReturn([]);

        return new Options($systemStore, new Escaper());
    }
}
