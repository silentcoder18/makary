<?php

/*
 * Versions web de la photo du hero de l'accueil (public/images/commune.jpg, fournie par le chef de projet ;
 * le fichier d'origine n'est pas modifié). Usage (depuis plateforme/) : php outils-dev/deriver-hero.php
 *
 * WebP recompressé, sans métadonnées, en deux largeurs : 1024 px (la largeur d'origine : la photo n'est jamais agrandie)
 * et 640 px pour les téléphones.
 */

$racine = dirname(__DIR__);
$sourcePath = file_exists("$racine/public/images/hero.jpg") ? "$racine/public/images/hero.jpg" : "$racine/public/images/commune.jpg";
$photo = imagecreatefromjpeg($sourcePath) ?: exit("Image source illisible\n");

foreach ([1536, 1024, 640] as $largeur) {
    $reduite = $largeur >= imagesx($photo) ? $photo : imagescale($photo, $largeur, -1, IMG_BICUBIC);
    $fichier = "images/hero-$largeur.webp";
    imagewebp($reduite, "$racine/public/$fichier", 80);
    printf("%-32s %4d × %-4d %6d octets\n", $fichier, imagesx($reduite), imagesy($reduite), filesize("$racine/public/$fichier"));
}
