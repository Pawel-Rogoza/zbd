const menuButton = document.querySelector('.menu-toggle');
const navigation = document.querySelector('.main-nav');

function setMenu(open) {
  if (!menuButton || !navigation) return;
  menuButton.setAttribute('aria-expanded', String(open));
  menuButton.setAttribute('aria-label', open ? 'Zamknij menu' : 'Otwórz menu');
  navigation.classList.toggle('is-open', open);
  document.body.classList.toggle('menu-open', open);
}

menuButton?.addEventListener('click', () => {
  setMenu(menuButton.getAttribute('aria-expanded') !== 'true');
});

navigation?.querySelectorAll('a').forEach((link) => {
  link.addEventListener('click', () => setMenu(false));
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && menuButton?.getAttribute('aria-expanded') === 'true') {
    setMenu(false);
    menuButton.focus();
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

window.addEventListener('resize', () => {
  if (window.innerWidth > 900) setMenu(false);
});

document.querySelector('[data-year]')?.replaceChildren(String(new Date().getFullYear()));

const mobileContact = document.querySelector('.mobile-contact');
const contactSection = document.querySelector('#kontakt');
if (mobileContact && contactSection && 'IntersectionObserver' in window) {
  const contactObserver = new IntersectionObserver(([entry]) => {
    mobileContact.classList.toggle('is-hidden', entry.isIntersecting);
  }, { threshold: 0.08 });
  contactObserver.observe(contactSection);
}

const form = document.querySelector('[data-contact-form]');
const formStarted = form?.querySelector('[name="form_started"]');

if (formStarted) {
  formStarted.value = String(Math.floor(Date.now() / 1000));
}

const contactStatus = new URLSearchParams(window.location.search).get('contact');
const contactMessage = document.querySelector('.form-message');
const statusMessages = {
  sent: 'Dziękujemy. Zapytanie zostało wysłane.',
  error: 'Nie udało się wysłać wiadomości. Spróbuj ponownie później.',
  invalid: 'Sprawdź wymagane pola i spróbuj ponownie.',
  limit: 'Wysłano zbyt wiele zapytań. Spróbuj ponownie za kilkanaście minut.',
};

if (contactMessage && statusMessages[contactStatus]) {
  contactMessage.textContent = statusMessages[contactStatus];
  contactMessage.dataset.state = contactStatus === 'sent' ? 'success' : 'error';
  if (window.history.replaceState) {
    window.history.replaceState({}, '', `${window.location.pathname}#kontakt`);
  }
}

function getNestedValue(source, path) {
  return path.split('.').reduce((value, key) => value?.[key], source);
}

function setSafeText(element, value) {
  const lines = String(value).split('\n');
  element.replaceChildren();
  lines.forEach((line, index) => {
    if (index) element.append(document.createElement('br'));
    element.append(document.createTextNode(line));
  });
}

async function hydrateEditableContent() {
  if (window.location.protocol === 'file:') return;
  try {
    const response = await fetch('data/content.json', { cache: 'no-store' });
    if (!response.ok) return;
    const content = await response.json();
    document.querySelectorAll('[data-content]').forEach((element) => {
      const value = getNestedValue(content, element.dataset.content);
      if (typeof value === 'string' && value.trim()) setSafeText(element, value);
    });

    if (Array.isArray(content.practice?.items)) {
      document.querySelectorAll('[data-practice-index]').forEach((card) => {
        const item = content.practice.items[Number(card.dataset.practiceIndex)];
        if (!item) return;
        const image = card.querySelector('img');
        const source = card.querySelector('source');
        const label = card.querySelector('figcaption span');
        const title = card.querySelector('figcaption strong');
        if (typeof item.image === 'string' && item.image.trim() && image) {
          if (!image.currentSrc.endsWith(item.image)) source?.remove();
          image.src = item.image;
        }
        if (typeof item.alt === 'string' && image) image.alt = item.alt;
        if (typeof item.label === 'string' && label) label.textContent = item.label;
        if (typeof item.title === 'string' && title) title.textContent = item.title;
      });
    }
  } catch {
    // Treść osadzona w HTML pozostaje bezpiecznym wariantem zapasowym.
  }
}

hydrateEditableContent();
