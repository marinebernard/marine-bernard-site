#!/usr/bin/env node
/**
 * Régénère la grille "derniers parcours" de la page Carnet de randonnée
 * (pages/parcours-komoot.html) à partir de l'API publique Komoot (aucune
 * authentification requise). Ajoute une vraie photo de la sortie quand
 * Komoot en propose une.
 *
 * Ne touche jamais aux blocs rédigés à la main (coups de cœur, bons plans…) —
 * seul le contenu entre les marqueurs AUTO-KOMOOT:START / AUTO-KOMOOT:END
 * est remplacé.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SITE_DIR = path.resolve(__dirname, '..');

const KOMOOT_USER_ID = '4969537527049';
const FETCH_COUNT = 10;
const INITIAL_VISIBLE = 4;
const START_MARKER = '<!-- AUTO-KOMOOT:START (généré automatiquement, ne pas éditer à la main) -->';
const END_MARKER = '<!-- AUTO-KOMOOT:END -->';

const TARGET_FILE = { path: path.join(SITE_DIR, 'pages', 'parcours-komoot.html'), headingTag: 'h3', indent: '          ' };

const FETCH_HEADERS = { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) marine-bernard-site-bot/1.0' };

async function fetchTours() {
  const url = `https://www.komoot.com/api/v007/users/${KOMOOT_USER_ID}/tours/?limit=${FETCH_COUNT}&status=public&type=tour_recorded&sort_field=date&sort_direction=desc`;
  const res = await fetch(url, { headers: FETCH_HEADERS });
  if (!res.ok) {
    throw new Error(`Komoot API a répondu ${res.status} ${res.statusText}`);
  }
  const data = await res.json();
  const tours = data?._embedded?.tours;
  if (!Array.isArray(tours) || tours.length === 0) {
    throw new Error('Aucun parcours trouvé dans la réponse Komoot — structure API peut-être changée');
  }
  return tours;
}

async function fetchCoverPhoto(tourId) {
  try {
    const res = await fetch(`https://www.komoot.com/api/v007/tours/${tourId}/cover_images/`, { headers: FETCH_HEADERS });
    if (!res.ok) return null;
    const data = await res.json();
    const items = data?._embedded?.items || [];
    if (items.length === 0) return null;
    const chosen = items.find((it) => it.cover === 1) || items[0];
    if (!chosen?.src) return null;
    return chosen.src.replace('{width}', '640').replace('{height}', '360').replace('{crop}', 'true');
  } catch {
    return null; // une photo indisponible ne doit jamais faire échouer la mise à jour
  }
}

function formatDuration(seconds) {
  const h = Math.floor(seconds / 3600);
  const m = Math.round((seconds % 3600) / 60);
  return `${h}h${String(m).padStart(2, '0')}`;
}

function formatDate(iso) {
  const date = new Date(iso);
  const now = new Date();
  const diffMs = now - date;
  const diffH = diffMs / 3_600_000;
  const diffDays = Math.floor(diffH / 24);
  if (diffH < 1) return "à l'instant";
  if (diffH < 24) return `il y a ${Math.round(diffH)}h`;
  if (diffDays === 1) return 'il y a 1 jour';
  if (diffDays < 7) return `il y a ${diffDays} jours`;
  return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }).format(date);
}

function guessCategory(name) {
  const n = name.toLowerCase();
  const cote = ['cap ', 'côte', "d'opale", 'opale', 'plage', 'littoral', 'blanc-nez', 'gris-nez', 'wissant', 'mer'];
  const foret = ['forêt', 'foret', 'bois '];
  if (cote.some((k) => n.includes(k))) return { cat: 'cote', label: "Côte d'Opale", icon: 'icon-waves' };
  if (foret.some((k) => n.includes(k))) return { cat: 'foret', label: 'Forêt', icon: 'icon-tree' };
  return { cat: 'campagne', label: 'Campagne', icon: 'icon-wheat' };
}

function guessDifficulty(distanceKm, elevationUp) {
  return distanceKm > 12 || elevationUp > 150
    ? { cls: 'diff-modere', label: 'Modéré' }
    : { cls: 'diff-facile', label: 'Facile' };
}

function escapeHtml(str) {
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function buildCard(tour, photoUrl, { headingTag, indent }, visible) {
  const distanceKm = tour.distance / 1000;
  const distanceStr = distanceKm.toFixed(distanceKm < 10 ? 2 : 1).replace('.', ',');
  const durationStr = formatDuration(tour.duration);
  const upStr = `${Math.round(tour.elevation_up)}m`;
  const downStr = `${Math.round(tour.elevation_down)}m`;
  const dateStr = formatDate(tour.date);
  const { cat, label, icon } = guessCategory(tour.name);
  const diff = guessDifficulty(distanceKm, tour.elevation_up);
  const name = escapeHtml(tour.name?.trim() || 'Randonnée — Pas-de-Calais');
  const i = indent;
  const cardClass = `parcours-card reveal${visible ? '' : ' hidden'}`;

  const photoBlock = photoUrl
    ? `${i}  <div class="parcours-card-photo"><img src="${photoUrl}" alt="${name}" loading="lazy"></div>\n`
    : '';

  const mapOrTrace = photoUrl
    ? ''
    : `${i}  <iframe class="komoot-embed" src="https://www.komoot.com/tour/${tour.id}/embed?profile=1" height="${headingTag === 'h2' ? 380 : 320}" scrolling="no"></iframe>\n`;

  return `${i}<div class="${cardClass}" data-cat="${cat}">
${photoBlock}${i}  <div class="parcours-card-header">
${i}    <div>
${i}      <div class="parcours-card-cat"><svg class="icon"><use href="#${icon}"/></svg> ${label}</div>
${i}      <${headingTag} class="parcours-card-title">${name}</${headingTag}>
${i}    </div>
${i}    <div class="parcours-card-date">${dateStr}</div>
${i}  </div>
${i}  <div class="parcours-meta">
${i}    <div class="meta-item"><span class="meta-icon"><svg class="icon"><use href="#icon-map"/></svg></span> ${distanceStr} km</div>
${i}    <div class="meta-item"><span class="meta-icon"><svg class="icon"><use href="#icon-clock"/></svg></span> ${durationStr}</div>
${i}    <div class="meta-item"><span class="meta-icon"><svg class="icon"><use href="#icon-arrow-up"/></svg></span> ${upStr}</div>
${i}    <div class="meta-item"><span class="meta-icon"><svg class="icon"><use href="#icon-arrow-down"/></svg></span> ${downStr}</div>
${i}  </div>
${mapOrTrace}${i}  <div class="parcours-card-footer">
${i}    <a href="https://www.komoot.com/fr-fr/tour/${tour.id}" target="_blank" rel="noopener" class="komoot-link">${photoUrl ? 'Voir le tracé sur Komoot' : 'Ouvrir sur Komoot'}</a>
${i}    <span class="diff-badge ${diff.cls}">${diff.label}</span>
${i}  </div>
${i}</div>`;
}

function updateFile(filePath, cardsHtml) {
  const content = readFileSync(filePath, 'utf8');
  const startIdx = content.indexOf(START_MARKER);
  const endIdx = content.indexOf(END_MARKER);
  if (startIdx === -1 || endIdx === -1) {
    throw new Error(`Marqueurs AUTO-KOMOOT introuvables dans ${filePath}`);
  }
  const before = content.slice(0, startIdx + START_MARKER.length);
  const after = content.slice(endIdx);
  const newContent = `${before}\n${cardsHtml}\n${after}`;
  if (newContent === content) return false;
  writeFileSync(filePath, newContent, 'utf8');
  return true;
}

async function main() {
  console.log('Récupération des derniers parcours Komoot…');
  const tours = await fetchTours();
  console.log(`${tours.length} parcours récupérés.`);

  console.log('Recherche de photos de couverture par parcours…');
  const photos = await Promise.all(tours.map((t) => fetchCoverPhoto(t.id)));
  console.log(`${photos.filter(Boolean).length}/${tours.length} parcours avec photo.`);

  const cardsHtml = tours
    .map((t, idx) => buildCard(t, photos[idx], TARGET_FILE, idx < INITIAL_VISIBLE))
    .join('\n\n');

  const changed = updateFile(TARGET_FILE.path, cardsHtml);
  console.log(`${path.basename(TARGET_FILE.path)} : ${changed ? 'mis à jour' : 'déjà à jour'}`);
  if (!changed) console.log('Aucun changement — rien à publier aujourd’hui.');
}

main().catch((err) => {
  console.error('Échec de la mise à jour des parcours Komoot :', err);
  process.exit(1);
});
