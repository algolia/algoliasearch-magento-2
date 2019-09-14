define([
  "jquery",
  'Magento_Catalog/js/price-utils',
  'mage/template',
  "jquery/ui"
], function ($, priceUtil, mageTemplate) {

  "use strict";

  $.widget('algolia.rangeSlider', {

    options: {
      fromLabel : '[data-role=from-label]',
      toLabel : '[data-role=to-label]',
      sliderBar : '[data-role=slider-bar]',
      applyButton : '[data-role=apply-range]',
      rate : 1.0000
    },

    _create: function () {
      this._initSliderValues();
      this._createSlider();
      this._refreshDisplay();
      this.element.find(this.options.applyButton).bind('click', this._applyRange.bind(this));
    },

    _initSliderValues: function() {
      this.rate = parseFloat(this.options.rate);
      this.from = Math.floor(this.options.currentValue.from * this.rate);
      this.to = Math.round(this.options.currentValue.to * this.rate);
      this.minValue = Math.floor(this.options.minValue * this.rate);
      this.maxValue = Math.round(this.options.maxValue * this.rate);
    },

    _createSlider: function() {
      this.element.find(this.options.sliderBar).slider({
        range: true,
        min: this.minValue,
        max: this.maxValue,
        values: [ this.from, this.to ],
        slide: this._onSliderChange.bind(this),
        step: this.options.step
      });
    },

    _onSliderChange : function (ev, ui) {
      this.from = ui.values[0];
      this.to = ui.values[1];
      this._refreshDisplay();
    },

    _refreshDisplay: function() {

      if (this.element.find('[data-role=from-label]')) {
        this.element.find('[data-role=from-label]').html(this._formatLabel(this.from));
      }

      if (this.element.find('[data-role=to-label]')) {
        this.element.find('[data-role=to-label]').html(this._formatLabel(this.to));
      }
    },

    _applyRange : function () {
      var range = {
        from : this.from * (1 / this.rate),
        to   : this.to * (1 / this.rate)
      };
      var url = mageTemplate(this.options.urlTemplate)(range);
      this.element.find(this.options.applyButton).attr('href', url);
    },

    _formatLabel : function(value) {
      var formattedValue = value;

      if (this.options.fieldFormat) {
        formattedValue = priceUtil.formatPrice(value, this.options.fieldFormat);
      }

      return formattedValue;
    }
  });

  return $.algolia.rangeSlider;
});
