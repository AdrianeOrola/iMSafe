(() => {
  const status = document.getElementById('sourceRefreshStatus');
  const refreshLink = document.getElementById('refreshSources');
  if (!status || !refreshLink) return;

  let refreshPromise;
  const fetchSource = async url => {
    const response = await fetch(url, { cache: 'no-store', credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const payload = await response.json();
    const source = payload && typeof payload === 'object' && payload.brief ? payload.brief : payload;
    return response.ok && source && !source.unavailable && !source.stale;
  };
  const checkSources = () => {
    status.textContent = 'Checking official feeds in the background. You can continue using this page.';
    refreshLink.setAttribute('aria-busy', 'true');
    return Promise.allSettled([
      fetchSource('api.php?action=pagasa'),
      fetchSource('api.php?action=advisories'),
    ]).then(results => {
      const completed = results.filter(result => result.status === 'fulfilled' && result.value === true).length;
      status.textContent = completed === 2
        ? 'Official-source check complete. Refresh this page to display any newer information.'
        : 'Some sources could not be refreshed. Saved information remains available; confirm it at the official portals.';
    }).catch(() => {
      status.textContent = 'Sources could not be refreshed. Saved information remains available; confirm it at the official portals.';
    }).finally(() => {
      refreshLink.removeAttribute('aria-busy');
    });
  };

  window.addEventListener('load', () => { refreshPromise = checkSources(); }, { once: true });
  refreshLink.addEventListener('click', event => {
    event.preventDefault();
    refreshPromise ??= checkSources();
    refreshPromise.finally(() => window.location.reload());
  });
})();
