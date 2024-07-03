define([
  'jquery',
  'uiComponent',
  'underscore',
  'mage/translate'
], function ($, Component, _) {
  "use strict";

  return Component.extend({
    defaults: {
      template: "Algolia_AlgoliaSearch/attribute-filter",
      noResultLabel : $.mage.__("No results.")
    },

    /* Initialization */
    initialize: function () {
      this._super();
      this.expanded = false;
      this.items = this.items.map(this.addItemId.bind(this));
      this.observe(['fulltextSearch', 'expanded']);

      var lastSelectedIndex = Math.max.apply(null, (this.items.map(
        function (v, k) {return v['is_selected'] ? k : 0;}))
      );
      this.maxSize = Math.max(this.maxSize, lastSelectedIndex + 1);

      this.initPlaceholder();
      this.onShowLess();
      this.searchActive = this.items.length > this.maxSize;
    },

    /* Placeholder initialization */
    initPlaceholder: function () {
      this.searchPlaceholder = $('<div/>').html($.mage.__('Search for other ...')).text();
    },

    /* Behaviour while typing in the search input */
    onSearchChange: function (component, ev) {
      var text = ev.target.value;
      if (text.trim() === "") {
        component.fulltextSearch(null);
        component.onShowLess();
      } else {
        component.fulltextSearch(text);
        component.onShowMore();
      }
      return true;
    },

    /* Reset value on focus if search is considered as empty */
    onSearchFocusOut: function(component, ev) {
      var text = ev.target.value;
      if (text.trim() === "") {
        component.fulltextSearch(null);
        ev.target.value = "";
      }
    },

    /* Get additional results */
    loadAdditionalItems: function (callback) {
      $.get(this.ajaxLoadUrl, function (data) {
        this.items = data.map(this.addItemId.bind(this));
        this.hasMoreItems  = false;

        if (callback) {
          return callback();
        }
      }.bind(this));
    },

    /* Get items list */
    getDisplayedItems: function () {
      var items = this.items;

      if (this.expanded() === false) {
        items = this.items.slice(0, this.maxSize);
      }

      if (this.fulltextSearch()) {
        var searchTokens    = this.slugify(this.fulltextSearch()).split('-');
        var lastSearchToken = searchTokens.splice(-1, 1)[0];

        items = items.filter(function(item) {
          var isValidItem = true;
          var itemTokens = this.slugify(item.label).split('-');
          searchTokens.forEach(function(currentToken) {
            if (itemTokens.indexOf(currentToken) === -1) {
              isValidItem = false;
            }
          })
          if (isValidItem && lastSearchToken) {
            var ngrams = itemTokens.map(function(token) {return token.substring(0, lastSearchToken.length)});
            isValidItem = ngrams.indexOf(lastSearchToken) !== -1;
          }
          return isValidItem;
        }.bind(this))
      }

      return items;
    },

    /* Check if search has results */
    hasSearchResult: function () {
      return this.getDisplayedItems().length > 0
    },

    /* Get No Search result message */
    getNoResultMessage : function() {
      return this.noResultLabel;
    },

    /* Callback when list is being populated */
    onShowMore: function () {
      if (this.hasMoreItems) {
        this.loadAdditionalItems(this.onShowMore.bind(this));
      } else {
        this.expanded(true);
      }
    },

    /* Callback when list is refined */
    onShowLess: function () {
      this.expanded(false);
    },

    /* Slugify search */
    slugify: function(text) {
      return text.toString().toLowerCase().replace(/\s+/g, '-').replace(/[^\w\-]+/g, '').replace(/\-\-+/g, '-').replace(/^-+/, '')
    },

    /* Add id to item list. */
    addItemId: function (item) {
      item.id = _.uniqueId(this.index + "_option_");
      item.displayProductCount = this.displayProductCount && (item.count >= 1)
      return item;
    },
  });
});
