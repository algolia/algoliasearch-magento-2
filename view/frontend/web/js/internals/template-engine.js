define([], function () {
  const ENGINE_TYPE_HOGAN = 'hogan';
  const ENGINE_TYPE_MUSTACHE = 'mustache';

  return {
    getSelectedEngine: () => ENGINE_TYPE_HOGAN, // override via mixin

    processTemplate: async function (template, data, measure = false) {
      const adapter = await this.getEngineAdapter(this.getSelectedEngine());
      if (measure) {
        const start = performance.now();
        const result = adapter.process(template, data);
        const end = performance.now();
        console.log(
          `### Template execution time with "${this.getSelectedEngine()}": %s ms`,
          end - start
        );
        return result;
      }

      return adapter.process(template, data);
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
