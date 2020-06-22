requirejs([
    'jquery',
    'algoliaAnalytics',
    'algoliaBundle',
], function ($, algoliaAnalytics, algoliaBundle) {

    var algoliaInsights = {
        config: null,
        defaultIndexName: null,
        isTracking: false,

        track: function(algoliaConfig) {

            if (this.isTracking) {
                return;
            }

            this.config = algoliaConfig;
            this.defaultIndexName = algoliaConfig.indexName + '_products';

            if (algoliaConfig.ccAnalytics.enabled
                || algoliaConfig.personalization.enabled) {

                this.initializeAnalytics();
                this.addSearchParameters();
                this.bindData();
                this.bindEvents();

                this.isTracking = true;
            }
        },

        initializeAnalytics: function() {
            algoliaAnalytics.init({
                appId: this.config.applicationId,
                apiKey: this.config.apiKey
            });

            var userAgent = 'insights-js-in-magento (' + this.config.extensionVersion + ')';
            algoliaAnalytics.addAlgoliaAgent(userAgent);

            var userToken = getCookie('aa-search');
            if (userToken && userToken !== '') algoliaAnalytics.setUserToken(userToken);

        },

        addSearchParameters: function() {

            var self = this;
            algolia.registerHook('beforeWidgetInitialization', function (allWidgetConfiguration) {
                allWidgetConfiguration.configure = allWidgetConfiguration.configure || {};
                if (algoliaConfig.ccAnalytics.enabled) {
                    allWidgetConfiguration.configure.clickAnalytics = true;
                }

                if (algoliaConfig.personalization.enabled) {
                    allWidgetConfiguration.configure.enablePersonalization = true;
                    allWidgetConfiguration.configure.userToken = algoliaAnalytics.userToken;
                }

                return allWidgetConfiguration;
            });
        },

        bindData: function() {

            var persoConfig = this.config.personalization;

            if (persoConfig.enabled && persoConfig.clickedEvents.productRecommended.enabled) {
                $(persoConfig.clickedEvents.productRecommended.selector).each(function (index, element) {
                    if ($(element).find('[data-role="priceBox"]').length) {
                        var objectId = $(element).find('[data-role="priceBox"]').data('product-id');
                        $(element).attr('data-objectid', objectId);
                    }
                });
            }
        },

        bindEvents: function() {

            this.bindClickedEvents();
            this.bindViewedEvents();

            algolia.triggerHooks('afterInsightsBindEvents', this);

        },

        bindClickedEvents: function() {

            var self = this;

            algoliaBundle.$(function ($) {
                $(self.config.autocomplete.selector).on('autocomplete:selected', function (e, suggestion) {
                    var eventData = self.buildEventData(
                        'Clicked', suggestion.objectID, suggestion.__indexName, suggestion.__position, suggestion.__queryID
                    );
                    self.trackClick(eventData);
                });
            });


            if (this.config.ccAnalytics.enabled || this.config.personalization.enabled) {
                $(document).on('click', this.config.ccAnalytics.ISSelector, function() {
                    var $this = $(this);
                    if ($this.data('clicked')) return;

                    var eventData = self.buildEventData(
                        'Clicked', $this.data('objectid'), $this.data('indexname'), $this.data('position'), $this.data('queryid')
                    );

                    self.trackClick(eventData);
                    // to prevent duplicated click events
                    $this.attr('data-clicked', true);
                });
            }

            if (this.config.personalization.enabled) {

                // Clicked Events
                var clickEvents = Object.keys(this.config.personalization.clickedEvents);

                for (var i = 0; i < clickEvents.length; i++) {
                    var clickEvent = this.config.personalization.clickedEvents[clickEvents[i]];
                    if (clickEvent.enabled && clickEvent.method == 'clickedObjectIDs') {
                        $(document).on('click', clickEvent.selector, function(e) {
                            var $this = $(this);
                            if ($this.data('clicked')) return;

                            var event = self.getClickedEventBySelector(e.handleObj.selector);
                            var eventData = self.buildEventData(
                                event.eventName,
                                $this.data('objectid'),
                                $this.data('indexname') ? $this.data('indexname') : self.defaultIndexName
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

                    algolia.registerHook('afterInstantsearchStart', function (search, algoliaBundle) {
                        var selectors = document.querySelectorAll(containers.join(', '));
                        selectors.forEach(function (e) {
                            e.addEventListener('click', function (event) {
                                var attribute = this.dataset.attr;
                                var elem = event.target;
                                if ($(elem).is("input[type=checkbox]") && elem.checked) {
                                    var filter = attribute + ':' + elem.value;
                                    self.trackFilterClick([filter]);
                                }
                            });
                        });

                        return search;
                    });
                }
            }
        },

        getClickedEventBySelector: function(selector) {

            var events = this.config.personalization.clickedEvents,
                keys = Object.keys(events);

            for (var i = 0; i < keys.length; i++) {
                if (events[keys[i]].selector == selector) {
                    return events[keys[i]];
                }
            }

            return {};
        },

        bindViewedEvents: function() {

            var self = this;

            // viewed event is exclusive to personalization
            if (!this.config.personalization.enabled) {
                return;
            }

            var viewConfig = this.config.personalization.viewedEvents.viewProduct;
            if (viewConfig.enabled) {
                $(document).ready(function () {
                    if ($('body').hasClass('catalog-product-view')) {
                        var objectId = $('#product_addtocart_form').find('input[name="product"]').val();
                        if (objectId) {
                            var viewData = self.buildEventData(viewConfig.eventName, objectId, self.defaultIndexName);
                            self.trackView(viewData);
                        }
                    }
                });
            }
        },

        buildEventData: function(eventName, objectId, indexName, position = null, queryId = null) {

            var eventData = {
                eventName: eventName,
                objectIDs: [objectId + ''],
                index: indexName
            };

            if (position) {
                eventData.positions = [parseInt(position)];
            }

            if (queryId) {
                eventData.queryID = queryId;
            }

            return eventData;
        },

        trackClick: function(eventData) {
            if (eventData.queryID) {
                algoliaAnalytics.clickedObjectIDsAfterSearch(eventData);
            } else {
                algoliaAnalytics.clickedObjectIDs(eventData);
            }
        },

        trackFilterClick: function(filters) {

            var eventData = {
                index: this.defaultIndexName,
                eventName: this.config.personalization.filterClicked.eventName,
                filters: filters
            };

            algoliaAnalytics.clickedFilters(eventData);
        },

        trackView: function(eventData) {
            algoliaAnalytics.viewedObjectIDs(eventData);
        },

        trackConversion: function(eventData) {
            if (eventData.queryID) {
                algoliaAnalytics.convertedObjectIDsAfterSearch(eventData);
            } else {
                algoliaAnalytics.convertedObjectIDs(eventData);
            }
        }

    };

    algoliaBundle.$(function ($) {
        if (window.algoliaConfig) {
            algoliaInsights.track(algoliaConfig);
        }
    });

    return algoliaInsights;

});