define([
    'jquery',
    'algoliaAnalyticsLib',
    'algoliaCommon',
    'mage/cookies',
], function ($, algoliaAnalyticsWrapper) {
    algoliaAnalytics = algoliaAnalyticsWrapper.default;

    window.algoliaInsights = {
        config            : null,
        defaultIndexName  : null,
        isTracking        : false,
        hasAddedParameters: false,

        useCookie() {
            return !this.config.cookieConfiguration.cookieRestrictionModeEnabled
                || !!getCookie(this.config.cookieConfiguration.consentCookieName);
        },

        // Although events can accept both auth and anon tokens, queries can only accept a single token
        determineUserToken() {
            return algoliaAnalytics.getAuthenticatedUserToken() ?? algoliaAnalytics.getUserToken();
        },

        track(algoliaConfig) {
            this.config = algoliaConfig;
            this.defaultIndexName = algoliaConfig.indexName + '_products';

            if (this.isTracking) {
                return;
            }

            if (
                algoliaConfig.ccAnalytics.enabled ||
                algoliaConfig.personalization.enabled
            ) {
                this.initializeAnalytics();
                this.addSearchParameters();
                this.bindData();
                this.bindEvents();

                this.isTracking = true;
            }
        },

        initializeAnalytics(partial = false) {
            algoliaAnalytics.init({
                appId         : this.config.applicationId,
                apiKey        : this.config.apiKey,
                useCookie     : this.useCookie(),
                cookieDuration: Number(
                    this.config.cookieConfiguration.cookieDuration
                ),
                partial
            });

            const userAgent =
                'insights-js-in-magento (' + this.config.extensionVersion + ')';
            algoliaAnalytics.addAlgoliaAgent(userAgent);

            // TODO: Reevaluate need for unset cookie
            const userToken = getCookie(algoliaConfig.cookieConfiguration.customerTokenCookie);
            const unsetAuthenticationToken = getCookie('unset_authentication_token');
            if (userToken && userToken !== '') {
                algoliaAnalytics.setAuthenticatedUserToken(userToken);
            } else if (unsetAuthenticationToken && unsetAuthenticationToken !== '') {
                algoliaAnalytics.setAuthenticatedUserToken('undefined');
                $.mage.cookies.clear('unset_authentication_token');
            }
        },

        applyInsightsToSearchParams(params = {}) {
            if (algoliaConfig.ccAnalytics.enabled) {
                params.clickAnalytics = true;
            }

            if (algoliaConfig.personalization.enabled) {
                params.enablePersonalization = true;
            }

            if (algoliaConfig.ccAnalytics.enabled || algoliaConfig.personalization.enabled) {
                params.userToken = this.determineUserToken();
            }

            return params;
        },

        addSearchParameters() {
            if (this.hasAddedParameters) {
                return;
            }

            algolia.registerHook(
                'beforeWidgetInitialization',
                (allWidgetConfiguration) => {

                    allWidgetConfiguration.configure =
                        algoliaInsights.applyInsightsToSearchParams(allWidgetConfiguration.configure);

                    return allWidgetConfiguration;
                }
            );

            algolia.registerHook(
                'afterAutocompleteProductSourceOptions',
                (options) => {
                    return algoliaInsights.applyInsightsToSearchParams(options);
                }
            );

            this.hasAddedParameters = true;
        },

        bindData: function () {
            const persoConfig = this.config.personalization;

            if (
                persoConfig.enabled &&
                persoConfig.clickedEvents.productRecommended.enabled
            ) {
                $(persoConfig.clickedEvents.productRecommended.selector).each(function (
                    index,
                    element
                ) {
                    if ($(element).find('[data-role="priceBox"]').length) {
                        const objectId = $(element)
                            .find('[data-role="priceBox"]')
                            .data('product-id');
                        $(element).attr('data-objectid', objectId);
                    }
                });
            }
        },

        bindEvents() {
            this.bindClickedEvents();
            this.bindViewedEvents();

            algolia.triggerHooks('afterInsightsBindEvents', this);
        },

        bindClickedEvents() {
            var self = this;

            // TODO: Switch to insights plugin
            $(function ($) {
                $(self.config.autocomplete.selector).on(
                    'autocomplete:selected',
                    function (e, suggestion) {
                        var eventData = self.buildEventData(
                            'Clicked',
                            suggestion.objectID,
                            suggestion.__indexName,
                            suggestion.__position,
                            suggestion.__queryID
                        );
                        self.trackClick(eventData);
                    }
                );
            });

            if (this.config.ccAnalytics.enabled) {
                $(document).on(
                    'click',
                    this.config.ccAnalytics.ISSelector,
                    function () {
                        var $this = $(this);
                        if ($this.data('clicked')) return;

                        var eventData = self.buildEventData(
                            'Clicked',
                            $this.data('objectid'),
                            $this.data('indexname'),
                            $this.data('position'),
                            $this.data('queryid')
                        );

                        self.trackClick(eventData);
                        // to prevent duplicated click events
                        $this.attr('data-clicked', true);
                    }
                );
            }

            if (this.config.personalization.enabled) {
                // Clicked Events
                var clickEvents = Object.keys(
                    this.config.personalization.clickedEvents
                );

                for (var i = 0; i < clickEvents.length; i++) {
                    var clickEvent =
                        this.config.personalization.clickedEvents[clickEvents[i]];
                    if (clickEvent.enabled && clickEvent.method == 'clickedObjectIDs') {
                        $(document).on('click', clickEvent.selector, function (e) {
                            var $this = $(this);
                            if ($this.data('clicked')) return;

                            var event = self.getClickedEventBySelector(e.handleObj.selector);
                            var eventData = self.buildEventData(
                                event.eventName,
                                $this.data('objectid'),
                                $this.data('indexname')
                                    ? $this.data('indexname')
                                    : self.defaultIndexName
                            );

                            self.trackClick(eventData);
                            $this.attr('data-clicked', true);
                        });
                    }
                }

                // Filter Clicked
                if (this.config.personalization.filterClicked.enabled) {
                    var facets = this.config.facets;
                    var containers = [];
                    for (var i = 0; i < facets.length; i++) {
                        var elem = createISWidgetContainer(facets[i].attribute);
                        containers.push('.' + elem.className);
                    }

                    algolia.registerHook(
                        'afterInstantsearchStart',
                        function (search) {
                            var selectors = document.querySelectorAll(containers.join(', '));
                            selectors.forEach(function (e) {
                                e.addEventListener('click', function (event) {
                                    var attribute = this.dataset.attr;
                                    var elem = event.target;
                                    if ($(elem).is('input[type=checkbox]') && elem.checked) {
                                        var filter = attribute + ':' + elem.value;
                                        self.trackFilterClick([filter]);
                                    }
                                });
                            });

                            return search;
                        }
                    );
                }
            }
        },

        getClickedEventBySelector(selector) {
            var events = this.config.personalization.clickedEvents,
                keys = Object.keys(events);

            for (var i = 0; i < keys.length; i++) {
                if (events[keys[i]].selector == selector) {
                    return events[keys[i]];
                }
            }

            return {};
        },

        bindViewedEvents() {
            var self = this;

            // viewed event is exclusive to personalization
            if (!this.config.personalization.enabled) {
                return;
            }

            var viewConfig = this.config.personalization.viewedEvents.viewProduct;
            if (viewConfig.enabled) {
                $(document).ready(function () {
                    if ($('body').hasClass('catalog-product-view')) {
                        var objectId = $('#product_addtocart_form')
                            .find('input[name="product"]')
                            .val();
                        if (objectId) {
                            var viewData = self.buildEventData(
                                viewConfig.eventName,
                                objectId,
                                self.defaultIndexName
                            );
                            self.trackView(viewData);
                        }
                    }
                });
            }
        },

        buildEventData(
            eventName,
            objectId,
            indexName,
            position = null,
            queryId = null
        ) {
            const eventData = {
                eventName: eventName,
                objectIDs: [objectId + ''],
                index    : indexName,
            };

            if (position) {
                eventData.positions = [parseInt(position)];
            }

            if (queryId) {
                eventData.queryID = queryId;
            }

            return eventData;
        },

        trackClick(eventData) {
            if (eventData.queryID) {
                algoliaAnalytics.clickedObjectIDsAfterSearch(eventData);
            } else {
                algoliaAnalytics.clickedObjectIDs(eventData);
            }
        },

        trackFilterClick(filters) {
            const eventData = {
                index    : this.defaultIndexName,
                eventName: this.config.personalization.filterClicked.eventName,
                filters  : filters,
            };

            algoliaAnalytics.clickedFilters(eventData);
        },

        trackView(eventData) {
            algoliaAnalytics.viewedObjectIDs(eventData);
        },

        trackConversion(eventData) {
            if (eventData.queryID) {
                algoliaAnalytics.convertedObjectIDsAfterSearch(eventData);
            } else {
                algoliaAnalytics.convertedObjectIDs(eventData);
            }
        },

        bindConsentButtonClick(algoliaConfig) {
            $(document).on(
                'click',
                algoliaConfig.cookieConfiguration.cookieAllowButtonSelector,
                (event) => {
                    event.preventDefault();
                    algoliaInsights.initializeAnalytics(algoliaConfig, true);
                }
            );
        }
    };

    algoliaInsights.addSearchParameters();

    $(function ($) {
        if (window.algoliaConfig) {
            algoliaInsights.bindConsentButtonClick(algoliaConfig);
            algoliaInsights.track(algoliaConfig);
        }
    });

    return algoliaInsights;
});
