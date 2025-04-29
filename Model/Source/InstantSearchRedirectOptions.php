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
                'label' => __('Redirect on page load'),
                'description' => __('If InstantSearch loads search results that include a redirect, immediately take the user to that URL.')
            ],
            [
                'value' => self::REDIRECT_ON_SEARCH_AS_YOU_TYPE,
                'label' => __('Trigger redirect on "search as you type"'),
                'description' =>
                    __(
                        'As the user types their query in the %1 widget, matching hits will be retrieved automatically from %2. If a redirect is found for the entered query, this setting will immediately take the user to that URL.',
                        '<a href="https://www.algolia.com/doc/api-reference/widgets/search-box/js/" target="_blank"><code>searchBox</code></a>',
                        '<a href="https://www.algolia.com/doc/guides/getting-started/how-algolia-works/" target="_blank">Algolia</a>'
                    )
            ],
            [
                'value' => self::SELECTABLE_REDIRECT,
                'label' => __('Display redirect as a selectable item'),
                'description' => __('If a redirect is found for a search query, display a clickable link to that URL above the search result hits.')
            ],
            [
                'value' => self::OPEN_IN_NEW_WINDOW,
                'label' => __('Open redirect URL in a new window'),
                'description' => __('This setting applies to clickable links only.')
            ]
        ];
    }
}
