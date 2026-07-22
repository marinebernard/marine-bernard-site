async function loadComponent(id, path) {
  const res = await fetch(path);
  const html = await res.text();
  document.getElementById(id).innerHTML = html;
}

document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  if (body.classList.contains('pro')) {
    loadComponent('header', '/components/header-pro.html');
  } else if (body.classList.contains('rando')) {
    loadComponent('header', '/components/header-rando.html');
  }
  loadComponent('footer', '/components/footer.html');
});
