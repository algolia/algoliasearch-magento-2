<?php

namespace Algolia\AlgoliaSearch\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Quote\Setup\QuoteSetupFactory;
use Magento\Sales\Setup\SalesSetupFactory;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var QuoteSetupFactory
     */
    private $quoteSetupFactory;

    /**
     * @var SalesSetupFactory
     */
    private $salesSetupFactory;

    public function __construct(
        QuoteSetupFactory $quoteSetupFactory,
        SalesSetupFactory $salesSetupFactory
    ) {
        $this->quoteSetupFactory = $quoteSetupFactory;
        $this->salesSetupFactory = $salesSetupFactory;
    }
    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        // beware, this is the version we are upgrading from, not to!
        $moduleVersion = $context->getVersion();

        if (version_compare($moduleVersion, '2.0.0', '<')) {
            /** @var \Magento\Quote\Setup\QuoteSetup $quoteSetup */
            $quoteSetup = $this->quoteSetupFactory->create(['resourceName' => 'quote_setup', 'setup' => $setup]);
            $quoteSetup->addAttribute(
                'quote_item',
                'algoliasearch_query_param',
                [
                    'type' => TABLE::TYPE_TEXT,
                    'nullable' => true,
                    'comment' => 'Reference for Algolia analytics order conversion',
                ]
            );

            /** @var \Magento\Sales\Setup\SalesSetup $salesSetup */
            $salesSetup = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);
            $salesSetup->addAttribute(
                'order_item',
                'algoliasearch_query_param',
                [
                    'type' => TABLE::TYPE_TEXT,
                    'nullable' => true,
                    'comment' => 'Reference for Algolia analytics order conversion',
                ]
            );
        }
    }
}
