<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Algolia\AlgoliaSearch\Registry\CurrentCategory;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Controller\Adminhtml\Category\Edit as EditController;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;

class SetAdminCurrentCategory
{
    /** @var CurrentCategory */
    protected $currentCategory;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @param CurrentCategory $currentCategory
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        CurrentCategory $currentCategory,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->currentCategory = $currentCategory;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * Set the current category in adminhtml area in the Algolia registry without using Magento registry
     * (which is deprecated)
     *
     * @param EditController $subject
     * @param Page $result
     *
     * @return Page
     */
    public function afterExecute(EditController $subject, $result)
    {
        $categoryId = $subject->getRequest()->getParam('id');
        try {
            $currentCategory = $this->categoryRepository->get($categoryId);
            $this->currentCategory->set($currentCategory);
        } catch (NoSuchEntityException) {
            return null;
        }

        return $result;
    }
}
