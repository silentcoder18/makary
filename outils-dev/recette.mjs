// Recette du portail et du back-office dans Chrome sans interface (MKY-P3-01, MKY-P3-02) : accessibilité axe-core,
// débordement à 375 px, budget de performance (architecture §9), absence de ressource tierce et d'erreur de console
// (CSP), menu mobile, envoi d'un formulaire, parcours d'agents fictifs (connexion avec double authentification).
// Usage : node outils-dev/lancer.mjs recette (démarre la base, prépare les comptes et sert le portail).
// Chrome est piloté avec un profil temporaire : le navigateur de l'utilisateur n'est pas touché. CHROME_PATH pour un autre exécutable.
import { readFileSync, readdirSync, existsSync, mkdirSync, mkdtempSync, rmSync } from "node:fs";
import { join, dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { tmpdir } from "node:os";
import { gzipSync } from "node:zlib";
import { createHmac } from "node:crypto";
import { createRequire } from "node:module";
import puppeteer from "puppeteer-core";

const ici = dirname(fileURLToPath(import.meta.url));
const racine = resolve(ici, "..");
const base = process.argv[2] ?? "http://127.0.0.1:8001";
const origine = new URL(base).origin;
const captures = join(racine, "storage", "recette");
mkdirSync(captures, { recursive: true });
const axeSource = readFileSync(createRequire(import.meta.url).resolve("axe-core/axe.min.js"), "utf8");

const BUDGET = { htmlGzip: 30 * 1024, cssJsGzip: 60 * 1024, jsGzip: 30 * 1024, pageTotal: 500 * 1024 };
// Ordinateur, tablette et mobile (cahier des charges, §11).
const LARGEURS = [{ nom: "mobile", width: 375, height: 812 }, { nom: "tablette", width: 768, height: 1024 }, { nom: "ordinateur", width: 1280, height: 900 }];
const annee = new Date().getFullYear();
const PAGES = [
  "/", "/decouvrir", "/decouvrir/histoire", "/decouvrir/localites", "/decouvrir/localites/village-fictif-a", "/decouvrir/equipements",
  "/le-maire", "/le-maire/bilans", "/mairie/executif", "/mairie/conseil-municipal", "/mairie/anciens-maires", "/mairie/services", "/mairie/services/etat-civil",
  "/projets", "/projets?statut=en-execution", "/projets/realisations", "/projets/forage-du-village-fictif-a", "/projets/piste-rurale-du-village-fictif-d",
  "/projets/maternite-fictive-du-village-e", "/investir", "/investir/secteurs", "/investir/secteurs/eau", "/investir/s-implanter", "/investir/manifester-mon-interet",
  "/partenaires", "/partenaires/besoins-de-financement", "/partenaires/nos-partenaires", "/partenaires/devenir-partenaire",
  "/diaspora", "/diaspora/contribuer", "/diaspora/declarer-mon-interet", "/diaspora/suivre-ma-demande",
  "/patrimoine-tourisme", "/patrimoine-tourisme/site-patrimonial-fictif-a", "/actualites", `/actualites/${annee}/actualite-fictive-reunion-de-quartier`,
  "/evenements", "/evenements/evenement-fictif-journee-portes-ouvertes", "/documents", "/documents/budget-primitif-fictif-2027",
  "/carte", "/contact", "/demarches", "/demarches/acte-de-naissance", "/recherche?q=forage", "/plan-du-site", "/accessibilite", "/adresse-inconnue",
  "/admin/connexion",
];
const CAPTURES = new Set(["/", "/projets", "/projets/forage-du-village-fictif-a", "/le-maire", "/partenaires", "/contact", "/documents", "/evenements",
  "/admin", "/admin/contenus", "/admin/validation", "/admin/projets", "/admin/demandes", "/admin/rubriques", "/admin/elus/nouveau", "/admin/coordonnees"]);

const erreurs = [];
const notes = [];
const poids = [];
const echec = (m) => erreurs.push(m);

// ---------------------------------------------------------------- 1. Poids des styles et scripts compilés
const assets = join(racine, "public", "build", "assets");
const gz = (f) => gzipSync(readFileSync(join(assets, f))).length;
const fichiers = readdirSync(assets);
const cssGz = fichiers.filter((f) => f.endsWith(".css")).reduce((s, f) => s + gz(f), 0);
const jsGz = fichiers.filter((f) => f.endsWith(".js")).reduce((s, f) => s + gz(f), 0);
if (cssGz + jsGz > BUDGET.cssJsGzip) echec(`CSS + JS compressés : ${cssGz + jsGz} o > ${BUDGET.cssJsGzip} o`);
if (jsGz > BUDGET.jsGzip) echec(`JS compressé : ${jsGz} o > ${BUDGET.jsGzip} o`);

// ---------------------------------------------------------------- 2. Contrôle d'une page
async function controler(contexte, chemin, l, { attendu = 200, mesurer = false } = {}) {
  const onglet = await contexte.newPage();
  await onglet.setViewport({ width: l.width, height: l.height });
  let octets = 0;
  const tiers = [];
  const consoles = [];
  onglet.on("console", (m) => { if (m.type() === "error") consoles.push(m.text()); });
  onglet.on("pageerror", (e) => consoles.push(e.message));
  onglet.on("request", (r) => { if (!r.url().startsWith(origine) && !r.url().startsWith("data:")) tiers.push(r.url()); });
  onglet.on("response", async (r) => { try { octets += (await r.buffer()).length; } catch { /* redirection */ } });
  const reponse = await onglet.goto(base + chemin, { waitUntil: "networkidle0" });
  if (reponse.status() !== attendu) {
    const extrait = (await onglet.evaluate(() => document.body?.innerText ?? "")).replace(/\s+/g, " ").slice(0, 300);
    echec(`${chemin} (${l.nom}) : statut ${reponse.status()} au lieu de ${attendu} — ${extrait}`);
  }
  const debord = await onglet.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  if (debord > 1) echec(`${chemin} (${l.nom}) : défilement horizontal de ${debord} px`);
  for (const t of tiers) echec(`${chemin} : ressource tierce ${t}`);
  // Le statut 404 attendu de la page introuvable apparaît aussi dans la console : ce n'est pas une erreur.
  for (const c of consoles) if (!(attendu === 404 && /status of 404/.test(c))) echec(`${chemin} (${l.nom}) : erreur de console : ${c}`);

  // Évalué par le protocole de débogage : la CSP de la page (sans script en ligne) reste active et contrôlée.
  await onglet.evaluate(axeSource);
  const violations = await onglet.evaluate(async () => {
    const r = await window.axe.run(document, { runOnly: { type: "tag", values: ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "best-practice"] } });
    return r.violations.map((v) => ({ id: v.id, impact: v.impact, n: v.nodes.length, cible: v.nodes[0]?.target?.join(" ") }));
  });
  for (const v of violations) {
    const ligne = `${chemin} (${l.nom}) : ${v.id} [${v.impact}] × ${v.n} — ${v.cible}`;
    if (v.impact === "serious" || v.impact === "critical") echec(`axe-core : ${ligne}`); else notes.push(`axe-core : ${ligne}`);
  }
  if (mesurer && l.nom === "mobile") poids.push({ chemin, htmlGz: gzipSync(Buffer.from(await reponse.text())).length, total: octets });
  if (CAPTURES.has(chemin)) {
    // Les images chargées à la demande sont forcées avant la capture pleine page.
    await onglet.evaluate(() => document.querySelectorAll("img[loading=lazy]").forEach((i) => { i.loading = "eager"; }));
    await onglet.waitForNetworkIdle({ idleTime: 300 }).catch(() => {});
    const nom = chemin === "/" ? "accueil" : chemin.slice(1).replaceAll("/", "_").replace(/\?.*/, "");
    await onglet.screenshot({ path: join(captures, `${nom}_${l.nom}.png`), fullPage: true });
  }
  return onglet;
}

// ---------------------------------------------------------------- 3. Double authentification (RFC 6238)
function totp(secret) {
  const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
  let bits = "";
  for (const c of secret.replace(/=+$/, "")) bits += alphabet.indexOf(c).toString(2).padStart(5, "0");
  const cle = Buffer.from(bits.match(/.{8}/g).map((o) => parseInt(o, 2)));
  const compteur = Buffer.alloc(8);
  compteur.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
  const h = createHmac("sha1", cle).update(compteur).digest();
  const d = h[h.length - 1] & 0xf;
  return String((h.readUInt32BE(d) & 0x7fffffff) % 1_000_000).padStart(6, "0");
}

async function connecter(contexte, identifiant) {
  const courriel = `${identifiant}@demo.makary.test`;
  const onglet = await contexte.newPage();
  await onglet.goto(base + "/admin/connexion", { waitUntil: "networkidle0" });
  await onglet.type("#champ-courriel", courriel);
  await onglet.type("#champ-mot_de_passe", process.env.MAKARY_RECETTE_MDP ?? "");
  await Promise.all([onglet.waitForNavigation({ waitUntil: "networkidle0" }), onglet.click("form button[type=submit]")]);
  if (onglet.url().includes("/admin/connexion/mfa")) {
    const secret = JSON.parse(process.env.MAKARY_RECETTE_SECRETS ?? "{}")[courriel];
    await onglet.type("#champ-code", totp(secret ?? ""));
    await Promise.all([onglet.waitForNavigation({ waitUntil: "networkidle0" }), onglet.click("form button[type=submit]")]);
  }
  const connecte = new URL(onglet.url()).pathname === "/admin";
  if (!connecte) echec(`${identifiant} : connexion impossible (${onglet.url()})`);
  await onglet.close();
  return connecte;
}

/** Première adresse d'un lien de la page (pages de détail du back-office, dont l'identifiant n'est pas connu d'avance). */
async function premierLien(contexte, chemin, selecteur) {
  const onglet = await contexte.newPage();
  await onglet.goto(base + chemin, { waitUntil: "networkidle0" });
  const href = await onglet.evaluate((s) => document.querySelector(s)?.getAttribute("href"), selecteur);
  await onglet.close();
  return href ? new URL(href, base).pathname : null;
}

async function verifierMenu(onglet) {
  // Menu mobile (<details>) : replié au départ, déplié par un clic, fermé par Échap.
  const etat = await onglet.evaluate(() => {
    const m = document.querySelector("details.menu-mobile");
    if (!m) return { absent: true };
    const avant = m.open; m.querySelector("summary").click();
    return { avant, apres: m.open, visible: !!m.querySelector("nav a")?.offsetParent };
  });
  await onglet.keyboard.press("Escape");
  const ferme = await onglet.evaluate(() => document.querySelector("details.menu-mobile")?.open === false);
  if (etat.absent || etat.avant || !etat.apres || !etat.visible || !ferme) echec(`menu mobile défaillant : ${JSON.stringify({ ...etat, ferme })}`);
}

async function envoyerUneDemande(contexte) {
  // Envoi réel d'un formulaire : accusé avec numéro de suivi, puis suivi.
  const onglet = await contexte.newPage();
  await onglet.goto(base + "/contact", { waitUntil: "networkidle0" });
  await onglet.select("#champ-objet", "Demande de renseignement");
  await onglet.type("#champ-courriel", "recette@exemple.invalid");
  await onglet.type("#champ-message", "Message fictif de recette automatisée.");
  await onglet.click("#champ-consentement");
  await Promise.all([onglet.waitForNavigation({ waitUntil: "networkidle0" }), onglet.click("form button[type=submit]")]);
  const numero = await onglet.evaluate(() => document.body.innerText.match(/DEM-\d{4}-[A-Z0-9]{6}/)?.[0]);
  if (!numero) echec("formulaire de contact : aucun numéro de suivi dans l'accusé");
  else {
    await onglet.goto(base + "/diaspora/suivre-ma-demande", { waitUntil: "networkidle0" });
    await onglet.type("#champ-numero", numero);
    await onglet.type("#champ-courriel", "recette@exemple.invalid");
    await Promise.all([onglet.waitForNavigation({ waitUntil: "networkidle0" }), onglet.click("form button[type=submit]")]);
    if (!(await onglet.evaluate((n) => document.body.innerText.includes(`Demande ${n}`), numero))) echec("suivi : la demande envoyée est introuvable");
  }
  await onglet.close();
}

async function verifierCsrf(contexte) {
  // Attaque CSRF : une page d'un autre site soumet un formulaire au portail, puis à la connexion du back-office.
  // Attendu : refus 419. (Une requête de même origine est acceptée par Laravel d'après l'en-tête Sec-Fetch-Site,
  // fixé par le navigateur et impossible à falsifier par un script : ce n'est pas une falsification inter-sites.)
  for (const [cible, champs] of [["/contact", { objet: "Autre", courriel: "csrf@exemple.invalid", message: "Message forgé.", consentement: "1" }],
    ["/admin/connexion", { courriel: "redacteur@demo.makary.test", mot_de_passe: "x" }]]) {
    const onglet = await contexte.newPage();
    const entrees = Object.entries(champs).map(([k, v]) => `<input name="${k}" value="${v}">`).join("");
    await onglet.setContent(`<form method="post" action="${base}${cible}">${entrees}</form>`);
    const [reponse] = await Promise.all([onglet.waitForNavigation({ waitUntil: "load" }), onglet.evaluate(() => document.forms[0].submit())]);
    if (reponse?.status() !== 419) echec(`CSRF : formulaire d'un autre site accepté sur ${cible} (statut ${reponse?.status()} au lieu de 419)`);
    await onglet.close();
  }
  notes.push("CSRF : formulaires soumis depuis un autre site refusés (419) sur /contact et /admin/connexion");
}

async function mesurerReponses(contexte) {
  // Temps de réponse du serveur (pour information : serveur PHP de développement, sans cache ni proxy).
  const mesures = [];
  for (const chemin of ["/", "/projets", "/projets/forage-du-village-fictif-a", "/api/v1/public/projets"]) {
    const onglet = await contexte.newPage();
    await onglet.goto(base + chemin, { waitUntil: "load" });
    const ms = await onglet.evaluate(() => { const n = performance.getEntriesByType("navigation")[0]; return Math.round(n.responseStart - n.requestStart); });
    mesures.push(`${chemin} ${ms} ms`);
    await onglet.close();
  }
  notes.push(`Temps de réponse du serveur de développement (indicatif) : ${mesures.join(" ; ")}`);
}

async function soumettreUnContenu(contexte) {
  // Le rédacteur soumet une actualité : elle alimente la file de validation examinée ensuite.
  const page = await premierLien(contexte, "/admin/contenus?type=actualite", "table a");
  if (!page) return echec("rédacteur : aucune actualité à soumettre");
  const onglet = await contexte.newPage();
  await onglet.goto(base + page, { waitUntil: "networkidle0" });
  const bouton = await onglet.$("form[action$='/soumettre'] button");
  if (!bouton) echec("rédacteur : bouton « Soumettre » absent");
  else await Promise.all([onglet.waitForNavigation({ waitUntil: "networkidle0" }), bouton.click()]);
  await onglet.close();
}

// ---------------------------------------------------------------- 4. Exécution
const executable = process.env.CHROME_PATH ?? [
  "C:/Program Files/Google/Chrome/Application/chrome.exe",
  "C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe",
  "/usr/bin/google-chrome", "/usr/bin/chromium",
].find((p) => existsSync(p));
if (!executable) { console.log("Navigateur introuvable : définir CHROME_PATH"); process.exit(2); }
const profil = mkdtempSync(join(tmpdir(), "makary-chrome-"));
const navigateur = await puppeteer.launch({
  executablePath: executable, headless: true, userDataDir: profil,
  args: ["--no-first-run", "--no-default-browser-check", "--disable-extensions"],
});
let pagesAdmin = 0;
let agentsConnectes = 0;
try {
  // Portail public
  const anonyme = navigateur.defaultBrowserContext();
  for (const chemin of PAGES) {
    for (const l of LARGEURS) {
      const onglet = await controler(anonyme, chemin, l, { attendu: chemin === "/adresse-inconnue" ? 404 : 200, mesurer: true });
      if (chemin === "/" && l.nom === "mobile") await verifierMenu(onglet);
      await onglet.close();
    }
  }
  await envoyerUneDemande(anonyme).catch((e) => echec(`formulaire de contact : ${e.message}`));
  await verifierCsrf(anonyme);
  await mesurerReponses(anonyme);

  // Back-office : chaque agent fictif dans un contexte isolé (cookies distincts), avec ses pages permises.
  const parcours = [
    ["redacteur", async (c) => ["/admin", "/admin/contenus", "/admin/contenus/nouveau", await premierLien(c, "/admin/contenus?type=actualite", "table a"),
      "/admin/medias/nouveau", "/admin/documents/nouveau"]],
    // Gestion du portail par rubrique (MKY-P3-08)
    ["admin.editorial", async (c) => ["/admin/rubriques", "/admin/coordonnees",
      "/admin/elus", "/admin/elus/nouveau", await premierLien(c, "/admin/elus", "table a"),
      "/admin/localites", await premierLien(c, "/admin/localites", "table a"), "/admin/equipements", "/admin/partenaires",
      "/admin/documents", await premierLien(c, "/admin/documents", "table a"), "/admin/medias", await premierLien(c, "/admin/medias", "table a")]],
    ["responsable.editorial", async (c) => ["/admin/validation", await premierLien(c, "/admin/validation", "table a")]],
    ["responsable.projets", async (c) => ["/admin/projets", "/admin/projets/nouveau", await premierLien(c, "/admin/projets?statut=financement_recherche", "table a")]],
    ["gestionnaire.partenaires", async (c) => ["/admin/demandes", await premierLien(c, "/admin/demandes", "table a")]],
    ["lecteur.auditeur", async () => ["/admin", "/admin/journal"]],
  ];
  for (const [agent, pages] of parcours) {
    const contexte = await navigateur.createBrowserContext();
    // Sans connexion, les pages renverraient la page de connexion : elles ne sont pas comptées comme contrôlées.
    if (!(await connecter(contexte, agent))) { await contexte.close(); continue; }
    agentsConnectes++;
    if (agent === "redacteur") await soumettreUnContenu(contexte);
    const liste = await pages(contexte);
    if (liste.includes(null)) echec(`${agent} : page de détail introuvable (liste vide)`);
    for (const chemin of liste.filter(Boolean)) {
      for (const l of LARGEURS) await (await controler(contexte, chemin, l)).close();
      pagesAdmin++;
    }
    await contexte.close();
  }
} finally {
  await navigateur.close();
  rmSync(profil, { recursive: true, force: true });
}

// ---------------------------------------------------------------- 5. Budget par page et bilan
for (const p of poids) {
  if (p.htmlGz > BUDGET.htmlGzip) echec(`${p.chemin} : HTML compressé ${p.htmlGz} o > ${BUDGET.htmlGzip} o`);
  if (p.total > BUDGET.pageTotal) echec(`${p.chemin} : poids total ${p.total} o > ${BUDGET.pageTotal} o`);
}
const max = (cle) => poids.reduce((m, p) => (p[cle] > m[cle] ? p : m));
console.log(`Portail : ${PAGES.length} pages × ${LARGEURS.length} largeurs et un envoi de formulaire ; back-office : ${pagesAdmin} pages × ${LARGEURS.length} largeurs, ${agentsConnectes} agents`);
console.log(`CSS compressé : ${cssGz} o ; JS compressé : ${jsGz} o (budget CSS + JS : ${BUDGET.cssJsGzip} o)`);
console.log(`HTML compressé le plus lourd : ${max("htmlGz").htmlGz} o (${max("htmlGz").chemin}) ; page la plus lourde : ${max("total").total} o (${max("total").chemin})`);
console.log("Captures : storage/recette/");
notes.forEach((n) => console.log("  note  " + n));
if (erreurs.length) { console.log(`NON CONFORME (${erreurs.length}) :`); erreurs.forEach((e) => console.log("  - " + e)); process.exit(1); }
console.log("RÉSULTAT : conforme (aucune violation grave ou critique)");
