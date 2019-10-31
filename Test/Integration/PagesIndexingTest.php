<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;
use Algolia\AlgoliaSearch\Model\Indexer\Page;

class PagesIndexingTest extends IndexingTestCase
{
    public function testOnlyOnStockProducts()
    {
        $this->setConfig(
            'algoliasearch_autocomplete/autocomplete/excluded_pages',
            $this->getSerializer()->serialize([])
        );

        /** @var Page $indexer */
        $indexer = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\Page');

        $this->processTest($indexer, 'pages', 6);
    }

    public function testExcludedPages()
    {
        $excludedPages = [
            ['attribute' => 'no-route'],
            ['attribute' => 'home'],
        ];
        $this->setConfig(
            'algoliasearch_autocomplete/autocomplete/excluded_pages',
            $this->getSerializer()->serialize($excludedPages)
        );

        /** @var Page $indexer */
        $indexer = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\Page');
        $this->processTest($indexer, 'pages', 4);

        $results = $this->algoliaHelper->query($this->indexPrefix . 'default_pages', '', []);

        $noRoutePageExists = false;
        $homePageExists = false;
        foreach ($results['hits'] as $hit) {
            if ($hit['slug'] === 'no-route') {
                $noRoutePageExists = true;
                continue;
            }
            if ($hit['slug'] === 'home') {
                $homePageExists = true;
                continue;
            }
        }

        $this->assertFalse($noRoutePageExists, 'no-route page exists in pages index and it should not');
        $this->assertFalse($homePageExists, 'home page exists in pages index and it should not');
    }

    public function testDefaultIndexableAttributes()
    {
        /** @var Page $indexer */
        $indexer = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\Page');
        $indexer->executeFull();

        $this->algoliaHelper->waitLastTask();

        $results = $this->algoliaHelper->query($this->indexPrefix . 'default_pages', '', ['hitsPerPage' => 1]);
        $hit = reset($results['hits']);

        $defaultAttributes = [
            'objectID',
            'name',
            'url',
            'slug',
            'content',
            'algoliaLastUpdateAtCET',
            '_highlightResult',
            '_snippetResult',
        ];

        foreach ($defaultAttributes as $key => $attribute) {
            $this->assertTrue(key_exists($attribute, $hit), 'Pages attribute "' . $attribute . '" should be indexed but it is not"');
            unset($hit[$attribute]);
        }

        $extraAttributes = implode(', ', array_keys($hit));
        $this->assertTrue(empty($hit), 'Extra pages attributes (' . $extraAttributes . ') are indexed and should not be.');
    }

    public function testStripTags()
    {
        $pageFactory = $this->getObjectManager()->create('\Magento\Cms\Model\PageFactory');
        $testPage = $pageFactory->create();

        $testPage = $testPage->setTitle('Example CMS page')
                             ->setIdentifier('example-cms-page')
                             ->setIsActive(true)
                             ->setPageLayout('1column')
                             ->setStores([0])
                             ->setContent('Hello Im a test CMS page with script tags and style tags. <script>alert("Foo");</script> <style>.bar { font-weight: bold; }</style>')
                             ->save();

        $testPageId = (string) $testPage->getId();

        /** @var PageHelper $pagesHelper */
        $pagesHelper = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Helper\Entity\PageHelper');
        $pages = $pagesHelper->getPages(1);
        foreach ($pages as $page) {
            if ($page['objectID'] === $testPageId) {
                $content = $page['content'];
                $this->assertNotContains('<script>', $content);
                $this->assertNotContains('alert("Foo");', $content);
                $this->assertNotContains('<style>', $content);
                $this->assertNotContains('.bar { font-weight: bold; }', $content);
            }
        }

        $testPage->delete();
    }

    public function testUtf8()
    {
        $utf8Content = 'příliš žluťoučký kůň';

        $pageFactory = $this->getObjectManager()->create('\Magento\Cms\Model\PageFactory');
        $testPage = $pageFactory->create();

        $testPage = $testPage->setTitle('Example CMS page')
                             ->setIdentifier('example-cms-page-utf8')
                             ->setIsActive(true)
                             ->setPageLayout('1column')
                             ->setStores([0])
                             ->setContent($utf8Content)
                             ->save();

        $testPageId = (string) $testPage->getId();

        /** @var PageHelper $pagesHelper */
        $pagesHelper = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Helper\Entity\PageHelper');
        $pages = $pagesHelper->getPages(1);
        foreach ($pages as $page) {
            if ($page['objectID'] === $testPageId) {
                $this->assertSame($utf8Content, $page['content']);
            }
        }

        $testPage->delete();
    }
}
