<?php

namespace Algolia\AlgoliaSearch\Service\Page;

use Algolia\AlgoliaSearch\Api\Builder\RecordBuilderInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\UrlFactory;

class RecordBuilder implements RecordBuilderInterface
{
    public function __construct(
        protected ManagerInterface $eventManager,
        protected ConfigHelper     $configHelper,
        protected FilterProvider   $filterProvider,
        protected UrlFactory       $frontendUrlFactory,
    ){}

    /**
     * Builds a Page record
     *
     * @param DataObject $entity
     * @return array
     *
     * @throws AlgoliaException
     */
    public function buildRecord(DataObject $entity): array
    {
        if (!$entity instanceof Page) {
            throw new AlgoliaException('Object must be a Page model');
        }

        $page = $entity;

        $pageObject = [];
        $pageObject['slug'] = $page->getIdentifier();
        $pageObject['name'] = $page->getTitle();

        $content = $entity->getContent();
        if ($this->configHelper->getRenderTemplateDirectives()) {
            $content = $this->filterProvider->getPageFilter()->filter($content);
        }

        $frontendUrlBuilder = $this->frontendUrlFactory->create()->setScope($page->getData('store_id'));
        $pageObject[AlgoliaConnector::ALGOLIA_API_OBJECT_ID] = $page->getId();
        $pageObject['url'] = $frontendUrlBuilder->getUrl(
            null,
            [
                '_direct' => $page->getIdentifier(),
                '_secure' => $this->configHelper->useSecureUrlsInFrontend($page->getData('store_id')),
            ]
        );
        $pageObject['content'] = $this->strip($content, ['script', 'style']);

        $transport = new DataObject($pageObject);
        $this->eventManager->dispatch(
            'algolia_after_create_page_object',
            ['page' => $transport, 'pageObject' => $page]
        );

        return $transport->getData();
    }

    protected function strip($s, $completeRemoveTags = [])
    {
        if ($completeRemoveTags && $completeRemoveTags !== [] && $s) {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $encodedStr = mb_encode_numericentity($s, [0x80, 0x10fffff, 0, ~0]);
            $dom->loadHTML($encodedStr);
            libxml_use_internal_errors(false);

            $toRemove = [];
            foreach ($completeRemoveTags as $tag) {
                $removeTags = $dom->getElementsByTagName($tag);

                foreach ($removeTags as $item) {
                    $toRemove[] = $item;
                }
            }

            foreach ($toRemove as $item) {
                $item->parentNode->removeChild($item);
            }

            $s = $dom->saveHTML();
        }

        $s = html_entity_decode($s, 0, 'UTF-8');

        $s = trim(preg_replace('/\s+/', ' ', $s));
        $s = preg_replace('/&nbsp;/', ' ', $s);
        $s = preg_replace('!\s+!', ' ', $s);
        $s = preg_replace('/\{\{[^}]+\}\}/', ' ', $s);
        $s = strip_tags($s);
        $s = trim($s);

        return $s;
    }
}
