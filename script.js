// Canonicalize the legacy static entrypoint while preserving the hash section.
if (window.location.protocol !== 'file:' && /\/index\.html$/i.test(window.location.pathname)) {
  const cleanPath = window.location.pathname.replace(/index\.html$/i, '');
  window.location.replace(`${cleanPath || '/'}${window.location.search}${window.location.hash}`);
}

const menuButton = document.querySelector('.menu-toggle');
const navigation = document.querySelector('.main-nav');
const desktopBreakpoint = window.matchMedia('(min-width: 901px)');

function getMenuFocusable() {
  return navigation?.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])') ?? [];
}

function setMenu(open, restoreFocus = false) {
  if (!menuButton || !navigation) return;
  menuButton.setAttribute('aria-expanded', String(open));
  menuButton.setAttribute('aria-label', open ? 'Zamknij menu' : 'Otwórz menu');
  navigation.classList.toggle('is-open', open);
  navigation.setAttribute('aria-hidden', String(!open));
  document.body.classList.toggle('menu-open', open);
  if (open) {
    window.requestAnimationFrame(() => getMenuFocusable()[0]?.focus());
  } else if (restoreFocus) {
    menuButton.focus();
  }
}

menuButton?.addEventListener('click', () => {
  setMenu(menuButton.getAttribute('aria-expanded') !== 'true');
});

navigation?.querySelectorAll('a').forEach((link) => {
  link.addEventListener('click', () => setMenu(false));
});

document.addEventListener('keydown', (event) => {
  if (menuButton?.getAttribute('aria-expanded') !== 'true') return;
  if (event.key === 'Escape') {
    event.preventDefault();
    setMenu(false, true);
    return;
  }
  if (event.key !== 'Tab') return;
  const focusable = [...getMenuFocusable()];
  if (!focusable.length) return;
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
});

document.addEventListener('click', (event) => {
  if (
    menuButton?.getAttribute('aria-expanded') === 'true'
    && !navigation?.contains(event.target)
    && !menuButton.contains(event.target)
  ) {
    setMenu(false);
  }
});

function syncBreakpoint(event) {
  if (event.matches) {
    if (menuButton?.getAttribute('aria-expanded') === 'true') setMenu(false);
    navigation?.setAttribute('aria-hidden', 'false');
  } else if (menuButton?.getAttribute('aria-expanded') !== 'true') {
    navigation?.setAttribute('aria-hidden', 'true');
  }
}

desktopBreakpoint.addEventListener?.('change', syncBreakpoint);
window.addEventListener('resize', () => syncBreakpoint(desktopBreakpoint));
syncBreakpoint(desktopBreakpoint);

document.querySelector('[data-year]')?.replaceChildren(String(new Date().getFullYear()));

const mobileContacts = [...document.querySelectorAll('.mobile-contact')];
const contactSection = document.querySelector('#kontakt');
const heroSection = document.querySelector('#start');
if (mobileContacts.length && 'IntersectionObserver' in window) {
  const contactObserver = new IntersectionObserver(([entry]) => {
    mobileContacts.forEach((contact) => contact.classList.toggle('is-hidden', entry.isIntersecting));
  }, { threshold: 0.08 });
  if (contactSection) contactObserver.observe(contactSection);

  const heroObserver = new IntersectionObserver(([entry]) => {
    mobileContacts.forEach((contact) => contact.classList.toggle('is-hidden', entry.isIntersecting));
  }, { threshold: 0.2 });
  if (heroSection) heroObserver.observe(heroSection);
}

const form = document.querySelector('[data-contact-form]');
const formStarted = form?.querySelector('[name="form_started"]');
if (formStarted) formStarted.value = String(Math.floor(Date.now() / 1000));

const phoneField = form?.querySelector('[name="phone"]');
const emailField = form?.querySelector('[name="email"]');
const contactHint = form?.querySelector('#contact-hint');

function validateContactMethod() {
  if (!phoneField || !emailField) return true;
  const hasPhone = phoneField.value.trim() !== '';
  const hasEmail = emailField.value.trim() !== '';
  const valid = hasPhone || hasEmail;
  const message = valid ? '' : 'Podaj telefon albo adres e-mail.';
  phoneField.setCustomValidity(message);
  emailField.setCustomValidity(message);
  contactHint?.classList.toggle('is-invalid', !valid);
  return valid;
}

phoneField?.addEventListener('input', validateContactMethod);
emailField?.addEventListener('input', validateContactMethod);
form?.addEventListener('submit', (event) => {
  if (!validateContactMethod()) {
    event.preventDefault();
    (phoneField?.value.trim() ? emailField : phoneField)?.focus();
  }
});

const contactStatus = new URLSearchParams(window.location.search).get('contact');
if (contactStatus && window.history.replaceState) {
  window.history.replaceState({}, '', `${window.location.pathname}#kontakt`);
}

document.querySelectorAll('[data-video]').forEach((frame) => {
  const poster = frame.querySelector('.video-poster');
  if (!poster) return;
  poster.addEventListener('click', () => {
    const videoId = frame.dataset.videoId;
    if (!videoId) return;
    const iframe = document.createElement('iframe');
    iframe.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(videoId)}?rel=0&modestbranding=1&autoplay=1`;
    iframe.title = frame.dataset.videoTitle || 'Film YouTube';
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';
    iframe.allowFullscreen = true;
    iframe.loading = 'eager';
    frame.replaceChildren(iframe);
  }, { once: true });
});

document.querySelectorAll('[data-before-after]').forEach((comparison) => {
  const range = comparison.querySelector('.comparison-range');
  if (!range) return;

  const updateComparison = () => {
    comparison.style.setProperty('--position', `${range.value}%`);
    range.setAttribute('aria-valuetext', `${range.value}% zdjęcia przed remontem`);
  };

  range.addEventListener('input', updateComparison);
  updateComparison();
});

