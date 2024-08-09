<?php

namespace Algolia\AlgoliaSearch\Model\Config;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\UrlInterface;

class EnableClickAnalyticsComment implements CommentInterface
{
    public function __construct(
        protected UrlInterface $urlInterface
    ) { }

    private function getLink(string $section, string $fragment = ""): string
    {
        $url = $this->urlInterface->getUrl("adminhtml/system_config/edit/section/$section");
        if ($fragment) {
            $url .= "#$fragment";
        }
        return $url;
    }

    public function getCommentText($elementValue)
    {
        $magentoCookieConfigLink = $this->getLink('web', 'web_cookie-link');
        $algoliaCookieConfigLink = $this->getLink( 'algoliasearch_credentials', 'algoliasearch_credentials_algolia_cookie_configuration-link');

        // return 'If your Magento cookie settings, specifically <b>General > Web > Default Cookie Settings > Cookie Restriction Mode</b> is set to "No," we will consider it as Implicit Cookie Consent. In this case, <code>useCookie</code> will be set to True by default for all insight events. Conversely, if <b>Cookie Restriction Mode is set to "Yes"</b>, Insight events will not be allowed without explicit cookie consent.';
        return <<<COMMENT
            Getting direct and accurate feedback from your users about their behavior, interests, and expectations helps you
            measure the success of your search solution and improve customer retention.

            <br/><br/>

            Capturing events data not only empowers you to make informed decisions but enables you to activate AI-powered
            features like Dynamic Re-Ranking that leverages this data to further enhance search performance, delivering an
            even more powerful and efficient user experience.

            <br/><br/>

            Enabling this feature is <strong>strongly recommended</strong>.

            <br/><br/>
            Please note that click analytics will only send details anonymously unless users
            <a href="https://experienceleague.adobe.com/en/docs/commerce-admin/start/compliance/privacy/compliance-cookie-law" target="_blank">provide consent</a>
            to use cookies, either by "opting in" (expressed consent) or as implied by the use of your site.

            <br/><br/>
            To obtain expressed consent for using cookies, Algolia will respect the configuration at:
            <a href="$magentoCookieConfigLink">General > Web > Default Cookie Settings > Cookie Restriction Mode</a>
            <br/><br/>
            This behavior can be further customized at:
            <a href="$algoliaCookieConfigLink">Algolia Search > Credentials and Basic Setup > Algolia Cookie Configuration</a>

            COMMENT;
    }

}
