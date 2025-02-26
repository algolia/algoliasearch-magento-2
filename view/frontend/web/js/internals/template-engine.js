/**
 * For templating the Mustache library is used by default
 * Docs: https://github.com/janl/mustache.js
 *
 * Alternatively you can also use Hogan
 * Docs:http://twitter.github.io/hogan.js/
 **/

define([], function () {
  const ENGINE_TYPE_HOGAN = 'hogan';
  const ENGINE_TYPE_MUSTACHE = 'mustache';

  const trackingAdapter = {
      processAndMeasure: function(template, data, tag = '') {
        const start = performance.now();
        const result = this.process(template, data);
        const end = performance.now();
        const prefix = tag ? `${tag}: ` : '';
        console.log(
          `${prefix}Template execution time with "${this.engineType}": %s ms`,
          end - start
        );
        return result;
      }
  };

  return {
    ENGINE_TYPE_HOGAN,
    ENGINE_TYPE_MUSTACHE,

    getSelectedEngineType: () => ENGINE_TYPE_MUSTACHE, // override via mixin

    // Retrieve the engine before calling process() if not able to use promises
    getSelectedEngineAdapter: async function() {
      return this.getEnhancedEngineAdapter(this.getSelectedEngineType());
    },

    // Convenience method
    processTemplate: async function (template, data, measure = false) {
      const adapter = await this.getSelectedEngineAdapter();
      return (measure)
        ? adapter.processAndMeasure(template, data)
        : adapter.process(template, data);
    },

    getEnhancedEngineAdapter: async function (type) {
      const adapter = await this.getEngineAdapter(type);
      return {
        engineType: this.getSelectedEngineType(),
        ...trackingAdapter,
        ...adapter
      };
    },

    getEngineAdapter: async function (type) {
      switch (type) {
        case ENGINE_TYPE_HOGAN:
          return this.getHoganAdapter();
        case ENGINE_TYPE_MUSTACHE:
          return this.getMustacheAdapter();
        default:
          throw new Error(`Unknown template engine: ${type}`);
      }
    },

    getHoganAdapter: async function () {
      const Hogan = await this.getAdapterEngine('algoliaHoganLib');
      return {
        process: (template, data) => {
          return Hogan.compile(template).render(data);
        },
      };
    },

    getMustacheAdapter: async function () {
      const Mustache = await this.getAdapterEngine('algoliaMustacheLib');
      return {
        process: (template, data) => {
          return Mustache.render(template, data);
        },
      };
    },

    getAdapterEngine: async function (lib) {
      try {
        return await new Promise((resolve, reject) => {
          require([lib], resolve, reject);
        });
      } catch (err) {
        console.error(`Failed to load module ${lib} for template engine:`, err);
        throw err;
      }
    },
  };
});
