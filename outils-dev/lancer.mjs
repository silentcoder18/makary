// Lance une commande de la plateforme sur la base MySQL/MariaDB locale (décision D13) — MKY-P2-03, MKY-P3-01, MKY-P3-05.
//
// Usage (depuis plateforme/) :
//   node outils-dev/lancer.mjs test              suite de tests, sur la base makary_test (phpunit.xml), recréée à chaque exécution
//   node outils-dev/lancer.mjs serve             serveur de développement (port 8000, ou MAKARY_PORT), base de .env
//   node outils-dev/lancer.mjs artisan <args>    commande artisan sur la base de .env (ex. migrate)
//   node outils-dev/lancer.mjs reinitialiser     recrée la base de .env de zéro, puis migrations et données FICTIVES
//   node outils-dev/lancer.mjs recette           compile les styles, recharge les données fictives dans la base makary_recette,
//                                                sert le portail (port 8001) et lance la recette dans Chrome
//
// Préalable : un serveur MySQL 8.0.16+ ou MariaDB 10.4+ démarré, la connexion renseignée dans .env, puis
// « php artisan makary:creer-base » (bases de développement, de test et de recette, en utf8mb4).
import { spawn } from "node:child_process";
import { join, resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { createConnection } from "node:net";
import { randomBytes } from "node:crypto";

const ici = dirname(fileURLToPath(import.meta.url));
const racine = resolve(ici, "..");
const [mode = "test", ...reste] = process.argv.slice(2);

// La recette a sa propre base : elle est vidée à chaque passage, la base de développement n'est jamais touchée.
// Elle éprouve toujours la double authentification, même si le poste en est dispensé (MKY-P3-07).
const env = { ...process.env, ...(mode === "recette" ? { DB_DATABASE: "makary_recette", MAKARY_MFA_DISPENSEE_EN_LOCAL: "false" } : {}) };

// Processus lancés par ce script : eux seuls sont arrêtés à la fin.
const enfants = [];

function attendrePort(numero, tentatives = 120) {
  return new Promise((ok, ko) => {
    const essayer = (n) => {
      const s = createConnection({ port: numero, host: "127.0.0.1" }, () => { s.end(); ok(); });
      s.on("error", () => (n <= 0 ? ko(new Error(`Le port ${numero} ne répond pas`)) : setTimeout(() => essayer(n - 1), 250)));
    };
    essayer(tentatives);
  });
}

function executer(commande, args, { silencieux = false } = {}) {
  return new Promise((ok) => {
    // npm est un script .cmd sous Windows : il est lancé par le shell, en une seule commande sans argument extérieur.
    const parShell = commande === "npm";
    const p = spawn(parShell ? [commande, ...args].join(" ") : commande, parShell ? [] : args,
      { cwd: racine, env, stdio: silencieux ? ["ignore", "pipe", "pipe"] : "inherit", shell: parShell });
    enfants.push(p);
    let sortie = "";
    p.stdout?.on("data", (d) => { sortie += d; });
    p.stderr?.on("data", (d) => { sortie += d; });
    p.on("exit", (code) => { if (code && silencieux) process.stderr.write(sortie); ok(code ?? 1); });
  });
}

function arreter(code) {
  for (const p of enfants) p.kill();
  process.exit(code);
}

async function recette() {
  for (const [commande, args] of [
    ["npm", ["run", "build"]],
    ["php", ["artisan", "makary:creer-base", "makary_recette"]],
    ["php", ["artisan", "migrate:fresh", "--seed", "--drop-views", "--force"]],
  ]) {
    const code = await executer(commande, args, { silencieux: true });
    if (code) return code;
  }
  // Comptes de démonstration : mot de passe aléatoire propre à cette recette, secrets TOTP pour les rôles qui l'exigent.
  env.MAKARY_RECETTE_MDP = randomBytes(18).toString("base64url");
  const sortie = await new Promise((ok) => {
    const p = spawn("php", ["artisan", "tinker", "--execute=require base_path('outils-dev/recette-comptes.php');"], { cwd: racine, env });
    let texte = "";
    p.stdout.on("data", (d) => { texte += d; });
    p.stderr.on("data", (d) => process.stderr.write(d));
    p.on("exit", () => ok(texte));
  });
  env.MAKARY_RECETTE_SECRETS = sortie.slice(sortie.indexOf("{"), sortie.lastIndexOf("}") + 1) || "{}";
  if (env.MAKARY_RECETTE_SECRETS === "{}") console.error(`Préparation des comptes de recette : sortie inattendue :
${sortie.slice(0, 1500)}`);
  await servir(8001, "ignore");
  return executer(process.execPath, [join(ici, "recette.mjs"), "http://127.0.0.1:8001"]);
}

/** Vrai si un serveur répond déjà sur ce port local. */
function portOccupe(numero) {
  return new Promise((ok) => {
    const s = createConnection({ port: numero, host: "127.0.0.1" }, () => { s.end(); ok(true); });
    s.on("error", () => ok(false));
  });
}

/**
 * Serveur intégré de PHP avec le routeur de Laravel, comme « artisan serve », mais en un seul processus qui hérite
 * de l'environnement (DB_DATABASE de la recette) et qu'on peut arrêter à coup sûr.
 * Refuse un port déjà pris : sinon, un autre serveur serait testé à la place de celui-ci.
 */
async function servir(portHttp, sortie) {
  if (await portOccupe(portHttp)) {
    console.error(`Le port ${portHttp} est déjà utilisé par un autre programme : libérez-le ou choisissez MAKARY_PORT.`);
    arreter(2);
  }
  const routeur = join(racine, "vendor", "laravel", "framework", "src", "Illuminate", "Foundation", "resources", "server.php");
  const php = spawn("php", ["-S", `127.0.0.1:${portHttp}`, routeur], { cwd: join(racine, "public"), env, stdio: ["ignore", sortie, sortie] });
  enfants.push(php);
  await attendrePort(portHttp);
  return php;
}

try {
  if (mode === "recette") arreter(await recette());
  if (mode === "serve") {
    const portHttp = Number(process.env.MAKARY_PORT ?? 8000);
    const php = await servir(portHttp, "inherit");
    console.log(`Portail : http://127.0.0.1:${portHttp} · back-office : http://127.0.0.1:${portHttp}/admin (Ctrl+C pour arrêter)`);
    process.on("SIGINT", () => arreter(0));
    php.on("exit", (code) => arreter(code ?? 1));
  } else {
    const commande = mode === "test" ? ["artisan", "test", ...reste]
      : mode === "artisan" ? ["artisan", ...reste]
      // migrate:fresh supprime les tables (et leurs déclencheurs) ; --drop-views supprime la vue projet_public.
      // Les procédures stockées sont recréées par la migration (DROP PROCEDURE IF EXISTS).
      : mode === "reinitialiser" ? ["artisan", "migrate:fresh", "--seed", "--drop-views"]
      : null;
    if (!commande) { console.error(`Mode inconnu : ${mode}`); arreter(2); }
    const php = spawn("php", commande, { cwd: racine, env, stdio: "inherit" });
    enfants.push(php);
    process.on("SIGINT", () => php.kill("SIGINT"));
    php.on("exit", (code) => arreter(code ?? 1));
  }
} catch (e) {
  console.error(e.message);
  arreter(1);
}
