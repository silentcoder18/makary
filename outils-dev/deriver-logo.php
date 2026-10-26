<?php

/*
 * Versions web du logo de la Commune (public/logo.jpg, fourni par la Commune ; le fichier d'origine n'est pas modifié).
 * Usage (depuis plateforme/) : php outils-dev/deriver-logo.php
 *
 * Le fichier fourni est une numérisation : on en retire seulement ce qui n'appartient pas au logo, puis on le réduit.
 *   - la mention de l'application de numérisation, sous la pointe de l'écusson (lignes 704 et suivantes) ;
 *   - des taches sur le fond blanc, à droite du cadre de l'écusson (zone x ≥ 610, y 60 à 245).
 * Le dessin du logo lui-même n'est ni redessiné ni recoloré. Le fichier vectoriel d'origine reste à obtenir (Q01, question 1.4).
 */

$racine = dirname(__DIR__);
$source = imagecreatefromjpeg("$racine/public/logo.jpg") ?: exit("public/logo.jpg illisible\n");
[$largeur, $hauteur] = [imagesx($source), imagesy($source)];
if ([$largeur, $hauteur] !== [660, 713]) {
    exit("Le logo a changé ($largeur × $hauteur) : vérifier les zones à nettoyer avant de régénérer.\n");
}

imagefilledrectangle($source, 610, 60, $largeur - 1, 245, imagecolorallocate($source, 255, 255, 255));
$logo = imagecrop($source, ['x' => 0, 'y' => 0, 'width' => $largeur, 'height' => 704]);

@mkdir("$racine/public/images", 0775, true);
$ecrire = function (GdImage $image, int $hauteurCible, string $fichier, ?int $cote = null) use ($racine) {
    $l = (int) round(imagesx($image) * $hauteurCible / imagesy($image));
    $reduite = imagescale($image, $l, $hauteurCible, IMG_BICUBIC);
    if ($cote !== null) {
        // Icône carrée : le logo centré sur fond blanc.
        $carre = imagecreatetruecolor($cote, $cote);
        imagefill($carre, 0, 0, imagecolorallocate($carre, 255, 255, 255));
        imagecopy($carre, $reduite, intdiv($cote - $l, 2), intdiv($cote - $hauteurCible, 2), 0, 0, $l, $hauteurCible);
        $reduite = $carre;
    }
    match (pathinfo($fichier, PATHINFO_EXTENSION)) {
        // Icônes : palette de 256 couleurs, bien plus légère qu'une image en couleurs vraies.
        'png' => imagetruecolortopalette($reduite, true, 256) && imagepng($reduite, "$racine/public/$fichier", 9),
        // Fiche PDF : JPEG de base (non progressif), intégré tel quel dans le PDF.
        'jpg' => imageinterlace($reduite, false) !== null && imagejpeg($reduite, "$racine/public/$fichier", 88),
        default => imagewebp($reduite, "$racine/public/$fichier", 86),
    };
    printf("%-30s %4d × %-4d %6d octets\n", $fichier, imagesx($reduite), imagesy($reduite), filesize("$racine/public/$fichier"));
};

// En-tête et pied de page (affiché sur 56 à 64 px de haut ; double densité).
$ecrire($logo, 64, 'images/logo-makary-64.webp');
$ecrire($logo, 128, 'images/logo-makary-128.webp');
// Page de connexion du back-office ; fiche de financement PDF.
$ecrire($logo, 256, 'images/logo-makary-256.webp');
$ecrire($logo, 256, 'images/logo-makary-pdf.jpg');
// Icônes du navigateur et de l'écran d'accueil des téléphones.
$ecrire($logo, 30, 'images/icone-32.png', 32);
$ecrire($logo, 170, 'images/icone-180.png', 180);
