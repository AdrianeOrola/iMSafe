(() => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.getElementById('primary-navigation');
  if (!toggle || !nav) return;
  toggle.hidden = false;
  document.documentElement.classList.add('nav-enhanced');
  const close = () => { toggle.setAttribute('aria-expanded', 'false'); nav.classList.remove('is-open'); };
  toggle.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded', String(open));
    nav.classList.toggle('is-open', open);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') { close(); toggle.focus(); }
  });
  document.addEventListener('click', event => { if (!event.target.closest('.app-header')) close(); });
  nav.addEventListener('click', event => { if (event.target.closest('a')) close(); });
  window.matchMedia('(min-width: 1121px)').addEventListener('change', close);
})();

(() => {
  const account = document.querySelector('.account-menu');
  if (!account) return;
  document.addEventListener('click', event => {
    if (account.open && !account.contains(event.target)) account.removeAttribute('open');
  });
  account.addEventListener('keydown', event => {
    if (event.key === 'Escape' && account.open) {
      event.preventDefault();
      account.removeAttribute('open');
      account.querySelector('summary').focus();
    }
  });
})();
