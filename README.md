# Plateforme de la Commune de Makary

Application Laravel 13 (PHP 8.5) : portail public, back-office et API publique. Elle met en œuvre le
[dossier de conception](../docs/conception/README.md) validé en P1 (MKY-P1-09) et la décision D12.

> **Données fictives.** Tant que la Commune n'a pas fourni et validé les contenus réels, l'application
> n'affiche que des données de démonstration, signalées comme telles (règle 3 du §14).

## Prérequis

- PHP 8.5 avec les extensions `pdo_mysql`, `bcmath` (montants exacts), `mbstring`, `openssl`, `intl` ; Composer 2.
- Node.js 22 ou plus récent (outillage de développement et, à partir de MKY-P3-01, compilation des styles).
- Un serveur MySQL 8.0.16+ ou MariaDB 10.4+ (décision D13) ; en développement, celui de XAMPP convient.
- En production : MariaDB 10.11 ou 11.4 (LTS), ou MySQL 8.4 (LTS), avec un compte dédié, jamais `root`
  ([architecture](../docs/conception/architecture.md), §4).

## Installation pour le développement

```sh
cd plateforme
composer install
cp .env.example .env                        # renseigner DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD
php artisan key:generate
php artisan makary:creer-base              # bases makary, makary_test et makary_recette (utf8mb4)
cd outils-dev && npm ci && cd ..
npm ci && npm run build                     # styles (Tailwind CSS) et scripts du portail
node outils-dev/lancer.mjs reinitialiser   # base neuve, migrations et données FICTIVES de démonstration
```

Trois bases distinctes : `makary` (développement, celle de `.env`), `makary_test` (tests, fixée par `phpunit.xml`,
recréée à chaque lancement) et `makary_recette` (recette, vidée à chaque passage). Le schéma fixe lui-même la
collation `utf8mb4_unicode_ci` de la base ; la connexion travaille en mode strict, à l'heure de Douala (`+01:00`).

| Commande | Effet |
| --- | --- |
| `node outils-dev/lancer.mjs test` | Suite de tests sur la base `makary_test`, recréée à chaque lancement (équivaut à `php artisan test`) |
| `node outils-dev/lancer.mjs serve` | Serveur de développement sur <http://127.0.0.1:8000> (ou `MAKARY_PORT`), base de `.env` ; refuse un port déjà occupé |
| `node outils-dev/lancer.mjs artisan <arguments>` | Commande artisan sur la base de `.env` |
| `node outils-dev/lancer.mjs reinitialiser` | Vide la base de `.env` (`migrate:fresh --drop-views`), applique les migrations et charge les données fictives (affiche le mot de passe des comptes de démonstration) |
| `node outils-dev/lancer.mjs recette` | Recette dans Chrome sans interface, sur la base `makary_recette` ([plan de tests](../docs/recette/plan_de_tests.md)) : axe-core à 375, 768 et 1 280 px, budget, ressources tierces, CSP, CSRF inter-sites, formulaire réel, 5 agents connectés (captures dans `storage/recette/`) |
| `npm run dev` | Serveur Vite (styles rechargés à chaud) ; la CSP l'autorise en local seulement |
| `php vendor/bin/pint --test` | Contrôle du style du code |
| `php artisan makary:permissions --verifier` | Vérifie que `config/permissions.php` reprend `specs/securite/permissions.yaml` |
| `php artisan makary:schema --verifier` | Vérifie que `database/sql/` reprend `specs/donnees/migrations/` |
| `php artisan makary:creer-base [bases…]` | Crée les bases manquantes en utf8mb4 (refusé en production) |
| `php artisan makary:purger` | Applique les durées de conservation (planifiée chaque nuit à 2 h 30) |
| `php artisan makary:analyser-fichiers` | Analyse par l'antivirus (`MAKARY_ANTIVIRUS`) les fichiers téléversés encore non analysés ; un fichier non analysé ne peut pas être publié (ou `--sans-antivirus` en mutualisé) |
| `php artisan makary:controle-production` | Refuse une configuration dangereuse avant toute mise en service (débogage, HTTPS, cookies, données fictives, mention non validée, base MySQL/MariaDB stricte sans compte `root`) |
| `php artisan makary:preparer-mutualise` | Vérifie et prépare l'application pour un hébergement mutualisé cPanel/OVH par FTP ou SSH |

## Sécurité (MKY-P2-03)

- **Comptes nominatifs** (`utilisateur`) ; aucun compte partagé, aucun jeton « se souvenir de moi ».
- **Rôles et permissions** : générés depuis [`specs/securite/permissions.yaml`](../specs/securite/permissions.yaml)
  par `php artisan makary:permissions` ; chaque permission est une *Gate* Laravel. Portées : `toutes`,
  `propre` (objets dont le compte est l'auteur), `perimetre` (modules M01 à M12 ou domaines). Un périmètre
  vide ne donne **aucun** droit.
- **Double authentification TOTP** imposée aux rôles qui l'exigent (`pragmarx/google2fa`), sans rejeu d'un
  code déjà utilisé ; le secret est chiffré en base.
  Sur le poste de développement seulement, `MAKARY_MFA_DISPENSEE_EN_LOCAL=true` dispense du code (MKY-P3-07) : sans
  effet si `APP_ENV` n'est pas `local`, bandeau dans le back-office, connexion tracée au journal, refus par
  `makary:controle-production` ; les tests et la recette exigent toujours le code.
- **Sessions** : 30 minutes d'inactivité, 8 heures au maximum, cookies `HttpOnly` et `SameSite=Strict`.
- **Limitation des tentatives** : 5 par minute (adresse saisie et adresse IP) pour la connexion et le code.
- **Journal d'audit** en ajout seul : la base refuse toute modification, et toute suppression avant la fin
  de conservation.
- **En-têtes** : CSP stricte sans script en ligne, `nosniff`, `frame-ancestors 'none'`, `noindex` sur le
  back-office.

Les durées et seuils sont des **propositions** à valider par l'administrateur technique et le référent
juridique (`config/makary.php`).

## Domaine (MKY-P2-04)

- **Schéma** : `database/sql/` est dérivé de [specs/donnees/migrations](../specs/donnees/migrations/) par
  `php artisan makary:schema` ; la spécification reste la source de vérité. Les règles du §8 (montants avec devise
  et source, statuts contrôlés, pièces exigées, séparation des tâches, historique) sont imposées par la base.
- **Services** (`app/Domaine/`), qui contrôlent les mêmes règles en amont, avec des messages destinés aux agents :
  - `Editorial\CircuitEditorial` : soumission (instantané figé), approbation ou renvoi par une autre personne,
    publication, éventuellement programmée, et retrait ;
  - `Editorial\Droits` : droit d'usage des médias, diffusion et archivage des documents ;
  - `Projets\Transitions` : changements de statut et conditions de la fiche projet (§3), validés par le
    référent compétent (`projets` ou `realisations`) ;
  - `Projets\Financement` : lignes recherchées et sollicitées ; une ligne n'est « acquise » que sur pièce,
    validée par un autre agent que celui qui l'a saisie ;
  - `Projets\Catalogue` : retrait d'une fiche du catalogue public, sans suppression ;
  - `Demandes\Demandes` : numéro de suivi, consentement, fin de conservation, purge.
- **Portail** : il ne lit que `Publication::courantes()`, c'est-à-dire les instantanés approuvés ; jamais les
  brouillons ni les données internes.
- **Données de démonstration** : `database/seeders/` crée des comptes et des contenus **fictifs** (« Village fictif A »,
  « Financeur fictif B »…) en passant par les services. Les informations que seule la Commune peut fournir sont
  marquées « [À fournir par la Commune] ».

## Portail public (MKY-P3-01)

- **Rendu côté serveur** (Blade), amélioration progressive : tout fonctionne sans JavaScript (menu mobile en
  `<details>`, filtres par formulaire). Le script ne pèse que 0,35 Ko compressé.
- **Direction artistique v2** ([direction_artistique.md](../docs/conception/direction_artistique.md)) : les jetons de
  couleur sont le thème Tailwind et la palette par défaut est retirée ; polices système ; icônes SVG dessinées
  pour le projet (`public/icones.svg`) ; aucune ressource tierce.
- **Lecture des seules publications** (`app/Portail/Lecture.php`) : le portail n'affiche que des instantanés
  approuvés. Les fichiers (`/fichiers/…`) ne sont servis que s'ils figurent dans une publication en ligne,
  avec une CSP `sandbox`.
- **Formulaires** (contact, investisseur, partenaire, diaspora) : mention d'information (E-01, proposition),
  consentement non précoché, champ piège, 5 envois par heure ; accusé avec numéro, suivi par numéro et
  adresse électronique.
- **Fiche de financement PDF** générée depuis la fiche publiée (`app/Portail/FichePdf.php`), sans dépendance.
- **Anciennes adresses** : redirections 301 et réponses 410 (`routes/redirections.php`).

## Back-office (MKY-P3-02)

Sous `/admin` (adresse distincte en production), après connexion. La plateforme est administrée par 2 rôles disposant de l’intégralité des 35 droits de gestion, d’administration et de publication : **Super Administrateur** (`superadmin`) et **Secrétaire Général** (`secretaire_general`).

| Section | Rôles | Contenu |
| --- | --- | --- |
| Tableau de bord | superadmin, secretaire_general | « À traiter », indicateurs calculés, activité récente |
| Rubriques du portail | superadmin, secretaire_general | Les 12 rubriques du portail : pages et fiches qui les alimentent, avec leur état |
| Pages et articles | superadmin, secretaire_general | Pages, actualités, événements, services, démarches, patrimoine : rédaction, publication directe, modification et suppression |
| Élus | superadmin, secretaire_general | Maire, exécutif, conseil, anciens élus |
| Partenaires | superadmin, secretaire_general | Partenaires techniques et financiers de la Commune |
| Documents | superadmin, secretaire_general | Centre documentaire, versions PDF, diffusion et archivage |
| Médias | superadmin, secretaire_general | Médiathèque : photos, recompression, droits d’usage, publication et suppression |
| Localités, Équipements | superadmin, secretaire_general | Liste officielle, géolocalisation et inventaire des équipements |
| Coordonnées | superadmin, secretaire_general | Adresse, téléphone, horaires et contacts des services municipaux |
| Validation | superadmin, secretaire_general | Circuit éditorial, revues, approbations et historique |
| Projets | superadmin, secretaire_general | Fiches projets, financements, transitions et catalogue |
| Demandes | superadmin, secretaire_general | Suivi citoyen, qualification, réponse et clôture |
| Utilisateurs | superadmin, secretaire_general | Création, modification, attribution des rôles, activation et suppression des comptes |
| Journal | superadmin, secretaire_general | Journal d’audit complet en lecture |

## API publique v1 (MKY-P3-03)

Les 15 opérations `/public/…` du [contrat OpenAPI](../specs/api/openapi.yaml), sous `/api/v1`. Elles couvrent :

- les projets et la fiche PDF ;
- les contenus, les élus et les documents ;
- les localités, la carte en GeoJSON, les partenaires, les coordonnées et la recherche ;
- le dépôt et le suivi des demandes.

Les erreurs suivent la RFC 9457 et les lectures sont en cache cinq minutes, avec `ETag`. Les tests de contrat
(`tests/Feature/Api/ContratTest.php`) valident chaque réponse contre le fichier OpenAPI.

## Mise en production
 
Aucune mise en production sans la validation du responsable technique autorisé (§14).
Pour les déploiements sur serveur partagé / mutualisé (cPanel, OVH, Plesk, Hostinger par FTP ou SSH),
consultez le [Guide de Déploiement sur Hébergement Mutualisé](../docs/exploitation/hebergement_mutualise.md)
et lancez `php artisan makary:preparer-mutualise`.

