async function loadComponent(id, path) {
  try {
    const res = await fetch(path);
    const html = await res.text();
    document.getElementById(id).innerHTML = html;
  } catch(e) {
    console.error('Erreur chargement composant:', path, e);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  const headerPath = body.classList.contains('rando')
    ? '/components/header-rando.html'
    : '/components/header-pro.html';

  loadComponent('header', headerPath).then(() => {
    const toggle = document.getElementById('headerToggle');
    const navWrap = document.querySelector('.header-nav-wrap');
    toggle?.addEventListener('click', () => {
      toggle.classList.toggle('open');
      navWrap?.classList.toggle('open');
    });
    navWrap?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
      toggle?.classList.remove('open');
      navWrap.classList.remove('open');
    }));
  });

  loadComponent('footer', '/components/footer.html');
  setTimeout(() => {
    const footer = document.getElementById('footer');
    if (footer && body.classList.contains('rando')) {
      footer.style.background = '#EAF3DE';
    }
  }, 100);
});
