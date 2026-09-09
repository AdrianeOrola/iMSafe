(() => {
  const config = window.imSafeAssistConfig;
  const launcher = document.getElementById('imassistLauncher');
  const dialog = document.getElementById('imassistDialog');
  const close = document.getElementById('imassistClose');
  const messages = document.getElementById('imassistMessages');
  const form = document.getElementById('imassistForm');
  const input = document.getElementById('imassistInput');
  const send = document.getElementById('imassistSend');
  const activity = document.getElementById('imassistActivity');
  const quick = document.getElementById('imassistQuickPrompts');
  if (!config || !launcher || !dialog || !form) return;

  const history = [];
  let busy = false;

  const open = () => {
    if (!dialog.open) dialog.show();
    document.body.classList.add('imassist-open');
    window.setTimeout(() => input.focus(), 0);
  };
  const dismiss = () => {
    if (dialog.open) dialog.close();
    document.body.classList.remove('imassist-open');
    launcher.focus();
  };
  const safeUrl = value => {
    try {
      const url = new URL(value, window.location.href);
      return ['http:', 'https:', 'tel:'].includes(url.protocol) ? url.href : '';
    } catch (_) { return ''; }
  };
  const selectedLocation = () => ['regionName', 'provinceName', 'municipalityName', 'barangayName']
    .map(id => document.getElementById(id)?.value?.trim() || '')
    .filter(Boolean).join(', ').slice(0, 320);

  const linkGroup = (className, items) => {
    const valid = Array.isArray(items) ? items : [];
    if (!valid.length) return null;
    const group = document.createElement('div');
    group.className = className;
    valid.forEach(item => {
      if (!item || typeof item.label !== 'string' || typeof item.url !== 'string') return;
      const href = safeUrl(item.url);
      if (!href) return;
      const link = document.createElement('a');
      link.href = href;
      link.textContent = item.label;
      if (item.type === 'emergency') link.classList.add('emergency');
      if (new URL(href).origin !== window.location.origin && !href.startsWith('tel:')) {
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.referrerPolicy = 'no-referrer';
      }
      group.append(link);
    });
    return group.childElementCount ? group : null;
  };
  const addMessage = (role, text, data = {}) => {
    const article = document.createElement('article');
    article.className = `imassist-message ${role}` + (data.urgent ? ' urgent' : '');
    const label = document.createElement('span');
    label.className = 'imassist-message-label';
    label.textContent = role === 'user' ? 'You' : (data.statusLabel || 'iMAssist');
    const body = document.createElement('p');
    body.textContent = text;
    article.append(label, body);
    const sources = linkGroup('imassist-sources', data.sources);
    const actions = linkGroup('imassist-actions', data.actions);
    if (sources) article.append(sources);
    if (actions) article.append(actions);
    messages.append(article);
    messages.scrollTop = messages.scrollHeight;
  };
  const submitMessage = async value => {
    const message = value.trim();
    if (!message || busy) return;
    busy = true;
    send.disabled = true;
    input.disabled = true;
    activity.textContent = 'iMAssist is checking available information...';
    addMessage('user', message);
    input.value = '';
    try {
      const response = await fetch(config.endpoint, {
        method: 'POST',
        cache: 'no-store',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': config.csrf },
        body: JSON.stringify({ message, history: history.slice(-8), location: selectedLocation() }),
      });
      const data = await response.json();
      if (!response.ok && typeof data.reply !== 'string') throw new Error(typeof data.error === 'string' ? data.error : 'iMAssist is temporarily unavailable.');
      const reply = typeof data.reply === 'string' ? data.reply : 'I could not prepare an answer. Please check Announcements.';
      addMessage('assistant', reply, data);
      history.push({ role: 'user', content: message }, { role: 'assistant', content: reply });
      while (history.length > 8) history.shift();
    } catch (error) {
      addMessage('assistant', error instanceof Error ? error.message : 'iMAssist is temporarily unavailable.', { statusLabel: 'Connection problem', actions: [{ type: 'announcements', label: 'View announcements', url: 'announcements.php' }] });
    } finally {
      busy = false;
      send.disabled = false;
      input.disabled = false;
      activity.textContent = '';
      input.focus();
    }
  };

  launcher.addEventListener('click', open);
  close.addEventListener('click', dismiss);
  dialog.addEventListener('close', () => document.body.classList.remove('imassist-open'));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && dialog.open) { event.preventDefault(); dismiss(); }
  });
  form.addEventListener('submit', event => { event.preventDefault(); void submitMessage(input.value); });
  input.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); void submitMessage(input.value); }
  });
  quick.addEventListener('click', event => {
    const button = event.target.closest('[data-prompt]');
    if (button) { open(); void submitMessage(button.dataset.prompt || ''); }
  });
})();
