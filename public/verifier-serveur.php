<?php
/**
 * Outil de diagnostic et de vérification pré-vol pour hébergement mutualisé.
 * Permet de vérifier la compatibilité du serveur (PHP, extensions, permissions, base de données)
 * sans avoir besoin d'accès SSH ou FTP.
 *
 * SÉCURITÉ : Supprimez ce fichier une fois votre installation validée.
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);

// 1. Contrôle PHP et Extensions
$phpVersion = PHP_VERSION;
$phpOk = version_compare($phpVersion, '8.3.0', '>=');

$extensionsRequises = [
    'pdo_mysql' => 'Connexion à la base de données MySQL / MariaDB',
    'bcmath' => 'Calculs financiers et montants exacts (§8)',
    'mbstring' => 'Gestion des chaînes de caractères UTF-8',
    'openssl' => 'Chiffrement des secrets TOTP et sessions HTTPS',
    'intl' => 'Formatage des dates et monnaies locales',
    'gd' => 'Recompression et assainissement des images sans ClamAV',
    'fileinfo' => 'Détection du type MIME des pièces jointes',
    'json' => 'Traitement des instantanés JSON',
];

$extensionsStatut = [];
foreach ($extensionsRequises as $ext => $desc) {
    $extensionsStatut[$ext] = [
        'charge' => extension_loaded($ext),
        'desc' => $desc,
    ];
}

// 2. Contrôle des dossiers et permissions
$dossiers = [
    'storage/app/private/televersements' => 'Stockage des justificatifs et pièces',
    'storage/app/public' => 'Stockage public',
    'storage/framework/cache/data' => 'Cache applicatif',
    'storage/framework/sessions' => 'Sessions sécurisées',
    'storage/framework/views' => 'Compilation des vues Blade',
    'storage/logs' => 'Journaux d’erreurs Laravel',
    'bootstrap/cache' => 'Cache des services et configurations',
];

$dossiersStatut = [];
foreach ($dossiers as $rel => $desc) {
    $chemin = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $existe = is_dir($chemin);
    $inscriptible = $existe && is_writable($chemin);
    $dossiersStatut[$rel] = [
        'existe' => $existe,
        'inscriptible' => $inscriptible,
        'desc' => $desc,
        'chemin' => $chemin,
    ];
}

// 3. Contrôle du fichier .env
$envPath = $baseDir . DIRECTORY_SEPARATOR . '.env';
$envExiste = file_exists($envPath);
$envConfig = [];
if ($envExiste) {
    $lignes = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lignes as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || str_starts_with($ligne, '#')) {
            continue;
        }
        if (str_contains($ligne, '=')) {
            [$cle, $valeur] = explode('=', $ligne, 2);
            $cle = trim($cle);
            $valeur = trim($valeur, " \t\n\r\0\x0B\"'");
            $envConfig[$cle] = $valeur;
        }
    }
}

$appKeyOk = !empty($envConfig['APP_KEY']);
$appEnv = $envConfig['APP_ENV'] ?? 'non défini';
$appDebug = ($envConfig['APP_DEBUG'] ?? 'false') === 'true';

// 4. Test de connexion Base de Données
$dbOk = false;
$dbMessage = '';
$dbDetails = [];

if ($envExiste && !empty($envConfig['DB_DATABASE'])) {
    $driver = $envConfig['DB_CONNECTION'] ?? 'mysql';
    $host = $envConfig['DB_HOST'] ?? '127.0.0.1';
    $port = $envConfig['DB_PORT'] ?? '3306';
    $dbname = $envConfig['DB_DATABASE'];
    $user = $envConfig['DB_USERNAME'] ?? '';
    $pass = $envConfig['DB_PASSWORD'] ?? '';

    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $dbOk = true;
        $dbMessage = "Connexion réussie à la base « {$dbname} » sur {$host}:{$port}.";

        // Vérification des tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $dbDetails['tables_count'] = count($tables);
        $dbDetails['has_utilisateur'] = in_array('utilisateur', $tables, true);
        $dbDetails['has_publication'] = in_array('publication', $tables, true);
        $dbDetails['has_migrations'] = in_array('migrations', $tables, true);

        if ($dbDetails['has_utilisateur']) {
            $stmt = $pdo->query("SELECT count(*) FROM utilisateur WHERE actif = 1");
            $dbDetails['utilisateurs_actifs'] = (int) $stmt->fetchColumn();
        }
        if ($dbDetails['has_publication']) {
            $stmt = $pdo->query("SELECT count(*) FROM publication WHERE retire_le IS NULL");
            $dbDetails['publications_en_ligne'] = (int) $stmt->fetchColumn();
        }
    } catch (Throwable $e) {
        $dbOk = false;
        $dbMessage = $e->getMessage();
    }
} else {
    $dbMessage = 'Fichier .env absent ou paramètres DB_* non renseignés.';
}

// 5. Assets frontend
$manifestPath = __DIR__ . '/build/manifest.json';
$assetsOk = file_exists($manifestPath);

$toutEstPret = $phpOk && !in_array(false, array_column($extensionsStatut, 'charge'), true)
    && !in_array(false, array_column($dossiersStatut, 'inscriptible'), true)
    && $envExiste && $appKeyOk && $dbOk && ($dbDetails['has_utilisateur'] ?? false) && $assetsOk;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Hébergement Mutualisé — Commune de Makary</title>
    <style>
        :root {
            --primary: #1e3a8a;
            --success: #15803d;
            --success-bg: #f0fdf4;
            --warning: #b45309;
            --warning-bg: #fffbeb;
            --danger: #b91c1c;
            --danger-bg: #fef2f2;
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 840px;
            margin: 0 auto;
        }
        .header {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.75rem 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .header h1 {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .header p {
            color: var(--muted);
            font-size: 0.95rem;
        }
        .alert-security {
            background: var(--warning-bg);
            border-left: 4px solid var(--warning);
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
            font-size: 0.9rem;
            color: #78350f;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .card h2 {
            font-size: 1.15rem;
            margin-bottom: 1rem;
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid #bbf7d0; }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid #fecaca; }
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid #fde68a; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        th, td {
            padding: 0.65rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th { color: var(--muted); font-weight: 600; background: #f8fafc; }
        tr:last-child td { border-bottom: none; }
        
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .status-pill.ok { color: var(--success); }
        .status-pill.ko { color: var(--danger); }
        
        .code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.85rem;
            background: #f1f5f9;
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
        }
        .cta-box {
            text-align: center;
            padding: 2rem;
            background: #eff6ff;
            border: 2px dashed #93c5fd;
            border-radius: 12px;
            margin-top: 2rem;
        }
        .cta-btn {
            display: inline-block;
            background: var(--primary);
            color: #fff;
            padding: 0.75rem 1.75rem;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            margin-top: 1rem;
            transition: background 0.2s;
        }
        .cta-btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Plateforme Commune de Makary — Contrôle Pré-Vol</h1>
        <p>Vérification des prérequis d'hébergement mutualisé sans SSH ni FTP (cPanel / Plesk / OVH).</p>
    </div>

    <div class="alert-security">
        <strong>⚠️ Avertissement de sécurité important :</strong> Ce fichier sert uniquement à vérifier votre mise en service. Dès que votre site fonctionne, supprimez immédiatement le fichier <span class="code">public/verifier-serveur.php</span> via votre Gestionnaire de fichiers cPanel / Plesk.
    </div>

    <!-- 1. PHP & Extensions -->
    <div class="card">
        <h2>
            1. Version PHP & Extensions requises
            <span class="badge <?= $phpOk ? 'badge-success' : 'badge-danger' ?>">
                PHP <?= htmlspecialchars($phpVersion) ?>
            </span>
        </h2>
        <table>
            <thead>
                <tr>
                    <th>Extension</th>
                    <th>Rôle pour la plateforme</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($extensionsStatut as $ext => $info): ?>
                <tr>
                    <td><span class="code"><?= htmlspecialchars($ext) ?></span></td>
                    <td><?= htmlspecialchars($info['desc']) ?></td>
                    <td>
                        <?php if ($info['charge']): ?>
                            <span class="status-pill ok">✔ Chargée</span>
                        <?php else: ?>
                            <span class="status-pill ko">✘ Manquante</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 2. Droits d'écriture (CHMOD) -->
    <div class="card">
        <h2>
            2. Permissions d'écriture des dossiers
            <span class="badge badge-success">CHMOD</span>
        </h2>
        <table>
            <thead>
                <tr>
                    <th>Dossier</th>
                    <th>Usage</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dossiersStatut as $rel => $info): ?>
                <tr>
                    <td><span class="code"><?= htmlspecialchars($rel) ?></span></td>
                    <td><?= htmlspecialchars($info['desc']) ?></td>
                    <td>
                        <?php if ($info['inscriptible']): ?>
                            <span class="status-pill ok">✔ Inscriptible (OK)</span>
                        <?php elseif ($info['existe']): ?>
                            <span class="status-pill ko">✘ Non inscriptible (CHMOD 775/755 requis)</span>
                        <?php else: ?>
                            <span class="status-pill ko">✘ Dossier absent (à créer)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 3. Configuration .env -->
    <div class="card">
        <h2>
            3. Fichier de Configuration (.env)
            <span class="badge <?= $envExiste && $appKeyOk ? 'badge-success' : 'badge-danger' ?>">
                <?= $envExiste ? 'Présent' : 'Manquant' ?>
            </span>
        </h2>
        <table>
            <tbody>
                <tr>
                    <td>Fichier <span class="code">.env</span></td>
                    <td>
                        <?= $envExiste ? '<span class="status-pill ok">✔ Détecté à la racine</span>' : '<span class="status-pill ko">✘ Non trouvé (copiez .env.production vers .env)</span>' ?>
                    </td>
                </tr>
                <tr>
                    <td>Clé d'application (<span class="code">APP_KEY</span>)</td>
                    <td>
                        <?= $appKeyOk ? '<span class="status-pill ok">✔ Configurée</span>' : '<span class="status-pill ko">✘ Manquante</span>' ?>
                    </td>
                </tr>
                <tr>
                    <td>Environnement (<span class="code">APP_ENV</span>)</td>
                    <td><span class="code"><?= htmlspecialchars($appEnv) ?></span></td>
                </tr>
                <tr>
                    <td>Débogage (<span class="code">APP_DEBUG</span>)</td>
                    <td>
                        <?= !$appDebug ? '<span class="status-pill ok">✔ Désactivé (production)</span>' : '<span class="status-pill ko">✘ Activé (doit être false)</span>' ?>
                    </td>
                </tr>
                <tr>
                    <td>Antivirus mutualisé (<span class="code">MAKARY_ANTIVIRUS_REQUIS</span>)</td>
                    <td>
                        <?= ($envConfig['MAKARY_ANTIVIRUS_REQUIS'] ?? '') === 'false' ? '<span class="status-pill ok">✔ false (médias recompressés par GD)</span>' : '<span class="status-pill ko">Recommandé : false</span>' ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- 4. Base de données -->
    <div class="card">
        <h2>
            4. Base de Données MySQL / MariaDB
            <span class="badge <?= $dbOk ? 'badge-success' : 'badge-danger' ?>">
                <?= $dbOk ? 'Connecté' : 'Erreur' ?>
            </span>
        </h2>
        <p style="margin-bottom: 0.75rem; font-size: 0.9rem; color: <?= $dbOk ? 'var(--success)' : 'var(--danger)' ?>;">
            <?= htmlspecialchars($dbMessage) ?>
        </p>
        <?php if ($dbOk): ?>
        <table>
            <tbody>
                <tr>
                    <td>Nombre de tables détectées</td>
                    <td><strong><?= (int) ($dbDetails['tables_count'] ?? 0) ?> table(s)</strong></td>
                </tr>
                <tr>
                    <td>Table <span class="code">utilisateur</span></td>
                    <td>
                        <?php if (!empty($dbDetails['has_utilisateur'])): ?>
                            <span class="status-pill ok">✔ Présente (<?= (int) ($dbDetails['utilisateurs_actifs'] ?? 0) ?> compte(s) actif(s))</span>
                        <?php else: ?>
                            <span class="status-pill ko">✘ Manquante (Importez <span class="code">database/makary_installation_mutualise.sql</span> dans phpMyAdmin)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Table <span class="code">publication</span></td>
                    <td>
                        <?php if (!empty($dbDetails['has_publication'])): ?>
                            <span class="status-pill ok">✔ Présente (<?= (int) ($dbDetails['publications_en_ligne'] ?? 0) ?> publication(s) en ligne)</span>
                        <?php else: ?>
                            <span class="status-pill ko">✘ Manquante</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Table <span class="code">migrations</span></td>
                    <td>
                        <?= !empty($dbDetails['has_migrations']) ? '<span class="status-pill ok">✔ Présente</span>' : '<span class="status-pill ko">✘ Manquante</span>' ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php else: ?>
        <p style="font-size: 0.85rem; color: var(--muted); margin-top: 0.5rem;">
            💡 Vérifiez les accès <span class="code">DB_HOST</span>, <span class="code">DB_DATABASE</span>, <span class="code">DB_USERNAME</span> et <span class="code">DB_PASSWORD</span> dans votre <span class="code">.env</span> via le Gestionnaire de fichiers cPanel.
        </p>
        <?php endif; ?>
    </div>

    <!-- 5. Assets Frontend -->
    <div class="card">
        <h2>
            5. Assets Frontend Compilés (CSS & JS)
            <span class="badge <?= $assetsOk ? 'badge-success' : 'badge-danger' ?>">
                <?= $assetsOk ? 'Compilés' : 'Absents' ?>
            </span>
        </h2>
        <p style="font-size: 0.9rem;">
            <?= $assetsOk ? '<span class="status-pill ok">✔ Assets Tailwind CSS et JS présents dans <span class="code">public/build/</span>.</span>' : '<span class="status-pill ko">✘ Fichier manifest.json absent.</span>' ?>
        </p>
    </div>

    <!-- Conclusion & Liens -->
    <?php if ($toutEstPret): ?>
    <div class="cta-box" style="background: #f0fdf4; border-color: #86efac;">
        <h3 style="color: var(--success); font-size: 1.25rem; margin-bottom: 0.5rem;">🎉 Félicitations ! Votre plateforme est prête.</h3>
        <p style="color: var(--muted); font-size: 0.95rem;">Tous les prérequis techniques sont validés avec succès.</p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="/" class="cta-btn" style="background: #15803d;">Accéder au Portail Public</a>
            <a href="/admin" class="cta-btn" style="background: var(--primary);">Accéder au Back-office</a>
        </div>
    </div>
    <?php else: ?>
    <div class="cta-box" style="background: #fffbeb; border-color: #fde68a;">
        <h3 style="color: var(--warning); font-size: 1.25rem; margin-bottom: 0.5rem;">⚙️ Configuration en cours</h3>
        <p style="color: var(--muted); font-size: 0.95rem;">Consultez les points signalés ci-dessus pour finaliser votre déploiement.</p>
    </div>
    <?php endif; ?>

    <p style="text-align: center; color: var(--muted); font-size: 0.8rem; margin-top: 2rem;">
        Commune de Makary &bull; Plateforme Numérique Municipale &bull; <?= date('Y') ?>
    </p>
</div>
</body>
</html>
