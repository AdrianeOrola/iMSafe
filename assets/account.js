const signupForm = document.getElementById('signupForm');

if (signupForm) {
  const savedProfile = window.imSafeSignupValues || {};
  const byId = id => document.getElementById(id);
  const selects = ['signupRegion', 'signupProvince', 'signupMunicipality', 'signupBarangay'].map(byId);
  const names = ['signupRegionName', 'signupProvinceName', 'signupMunicipalityName', 'signupBarangayName'].map(byId);
  const fields = ['region', 'province', 'city_municipality', 'barangay'];
  const actions = ['regions', 'provinces', 'municipalities', 'barangays'];
  const prompts = ['Select region', 'Select province', 'Select city / municipality', 'Select barangay'];
  const parentPrompts = ['Loading regions...', 'Select region first', 'Select province first', 'Select city / municipality first'];
  const meta = byId('signupLocationMeta');
  const errorBox = byId('signupLocationError');
  const retry = byId('retrySignupLocations');
  const submit = signupForm.querySelector('button[type="submit"]');
  const requests = new Map();
  let revision = 0;
  let failedLevel = null;

  const dataUrl = (action, params = {}) => `api.php?action=${encodeURIComponent(action)}&${new URLSearchParams(params)}`;
  const fill = (select, values, prompt) => {
    select.replaceChildren(new Option(prompt, ''));
    values.forEach(value => select.add(new Option(value.name, value.code)));
  };
  const choose = (select, value) => {
    if (typeof value === 'string' && [...select.options].some(option => option.value === value)) select.value = value;
  };
  const syncName = index => {
    names[index].value = selects[index].value ? selects[index].selectedOptions[0].textContent : '';
  };
  const ready = () => {
    const locationReady = selects.every(select => !select.disabled && select.value);
    submit.disabled = requests.size > 0;
    if (requests.size) meta.textContent = 'Loading Philippine geographic reference data...';
    return locationReady;
  };
  const resetFrom = level => {
    for (let index = level; index < selects.length; index++) {
      requests.get(index)?.abort();
      requests.delete(index);
      selects[index].replaceChildren(new Option(parentPrompts[index], ''));
      selects[index].disabled = true;
      selects[index].removeAttribute('aria-busy');
      selects[index].removeAttribute('aria-invalid');
      names[index].value = '';
    }
    errorBox.hidden = true;
    retry.hidden = true;
    failedLevel = null;
    ready();
  };
  const paramsFor = level => level === 1 ? { region: selects[0].value }
    : level === 2 ? { region: selects[0].value, province: selects[1].value }
    : level === 3 ? { municipality: selects[2].value } : {};
  const loadLevel = async level => {
    const select = selects[level];
    const controller = new AbortController();
    requests.get(level)?.abort();
    requests.set(level, controller);
    select.disabled = true;
    select.setAttribute('aria-busy', 'true');
    select.replaceChildren(new Option('Loading...', ''));
    ready();
    const timeout = window.setTimeout(() => controller.abort(), 45000);
    try {
      const response = await fetch(dataUrl(actions[level], paramsFor(level)), { cache: 'no-store', signal: controller.signal, headers: { Accept: 'application/json' } });
      const json = await response.json();
      if (requests.get(level) !== controller) return false;
      if (!response.ok || !Array.isArray(json.items) || !json.items.length) throw new Error('Location list unavailable.');
      fill(select, json.items, prompts[level]);
      select.disabled = false;
      select.removeAttribute('aria-invalid');
      errorBox.hidden = true;
      retry.hidden = true;
      failedLevel = null;
      meta.textContent = (json.source || 'Philippine geographic reference')
        + (json.stale ? ' - using saved data while providers are unavailable.' : json.fromCache ? ' - cached reference data.' : ' - location list loaded.');
      return true;
    } catch (error) {
      if (requests.get(level) !== controller) return false;
      select.replaceChildren(new Option('List unavailable', ''));
      select.setAttribute('aria-invalid', 'true');
      errorBox.textContent = 'We could not load ' + actions[level] + '. Check your connection, then retry.';
      errorBox.hidden = false;
      retry.hidden = false;
      failedLevel = level;
      meta.textContent = 'Location selection is incomplete. Retry the list to continue.';
      return false;
    } finally {
      window.clearTimeout(timeout);
      if (requests.get(level) === controller) {
        requests.delete(level);
        select.removeAttribute('aria-busy');
        ready();
      }
    }
  };
  const continueCascade = async (level, restoreValues = false, ticket = revision) => {
    for (let index = level; index < selects.length; index++) {
      if (ticket !== revision || !await loadLevel(index) || ticket !== revision) return;
      if (restoreValues) choose(selects[index], savedProfile[fields[index]]);
      if (index === 1 && selects[index].options.length === 2 && selects[index].options[1].value === 'none') selects[index].value = 'none';
      syncName(index);
      if (!selects[index].value) return;
    }
  };

  selects.forEach((select, index) => select.addEventListener('change', () => {
    revision++;
    syncName(index);
    resetFrom(index + 1);
    if (select.value && index < selects.length - 1) void continueCascade(index + 1);
  }));
  retry.addEventListener('click', async () => {
    if (failedLevel === null) return;
    const level = failedLevel;
    revision++;
    resetFrom(level);
    retry.disabled = true;
    await continueCascade(level);
    retry.disabled = false;
  });
  signupForm.addEventListener('submit', event => {
    if (selects.some(select => select.disabled || !select.value) || requests.size) {
      event.preventDefault();
      errorBox.textContent = 'Complete all four location selections before creating your account.';
      errorBox.hidden = false;
      selects.find(select => !select.disabled && !select.value)?.focus();
      return;
    }
    submit.disabled = true;
    submit.textContent = 'Creating account...';
  });

  resetFrom(0);
  void continueCascade(0, true);
}
