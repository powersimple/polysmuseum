// HMR Client Handler
if (import.meta.hot) {
  import.meta.hot.on('style-update', (data) => {
    console.log('Style update received:', data);
    const links = document.getElementsByTagName('link');
    for (let i = 0; i < links.length; i++) {
      const link = links[i];
      if (link.rel === 'stylesheet' && link.href.includes(data.path)) {
        const url = new URL(link.href);
        url.searchParams.set('t', data.timestamp);
        link.href = url.toString();
        break;
      }
    }
  });

  import.meta.hot.on('script-update', (data) => {
    console.log('Script update received:', data);
    // Reload the page for script changes
    window.location.reload();
  });
} 