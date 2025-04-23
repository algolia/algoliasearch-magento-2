<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class InstantSearchRedirectOptions implements OptionSourceInterface
{
    public const REDIRECT_ON_PAGE_LOAD = 1;
    public const REDIRECT_ON_SEARCH_AS_YOU_TYPE = 2;
    public const SELECTABLE_REDIRECT = 3;
    public const OPEN_IN_NEW_WINDOW = 4;

    /** @return array */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::REDIRECT_ON_PAGE_LOAD,
                'label' => __('Redirect on page load (if InstantSearch loads with a redirect, immediately take the user to that URL.)'),
            ],
            [
                'value' => self::REDIRECT_ON_SEARCH_AS_YOU_TYPE,
                'label' => __('Trigger redirect on "search as you type"'),
            ],
            [
                'value' => self::SELECTABLE_REDIRECT,
                'label' => __('Display the redirect as a selectable item above search result hits'),
            ],
            [
                'value' => self::OPEN_IN_NEW_WINDOW,
                'label' => __('Open redirect URL in a new window (applies to clickable links only)'),
            ]
        ];
    }
}
