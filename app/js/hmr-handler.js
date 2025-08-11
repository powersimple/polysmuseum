if (import.meta.hot) {
    import.meta.hot.accept((newModule) => {
      // console.log('HMR update received');
      
      // Handle style updates
      if (newModule && newModule.default) {
        // If the module exports a default function, call it
        newModule.default();
      }
      
      // Force a style refresh
      const links = document.getElementsByTagName('link');
      for (let i = 0; i < links.length; i++) {
        const link = links[i];
        if (link.rel === 'stylesheet') {
          const url = new URL(link.href);
          url.searchParams.set('t', Date.now());
          link.href = url.toString();
        }
      }
    });
  }
  