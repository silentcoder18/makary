<?php

/*
 * Paramètres propres à la plateforme de la Commune de Makary.
 * Les valeurs sont des PROPOSITIONS (MKY-P1-05, MKY-P1-06) à valider par l'administrateur technique
 * et le référent juridique.
 */
return [

    // Mention affichée sur chaque page tant que les données ne sont pas réelles (règle 3 du §14).
    'donnees_fictives' => (bool) env('MAKARY_DONNEES_FICTIVES', false),

    'conservation' => [
        // Journal d'audit : durée à arbitrer avec les dix ans de la loi n° 2010/012 (E-04, E-08).
        'journal_jours' => (int) env('MAKARY_CONSERVATION_JOURNAL_JOURS', 365),
        // Demandes du public : durée par défaut, à remplacer par le registre des traitements (E-02).
        'demande_jours' => (int) env('MAKARY_CONSERVATION_DEMANDE_JOURS', 730),
    ],

    'session' => [
        // Durée maximale d'une session du back-office, quelle que soit l'activité (roles_permissions.md, §6).
        'duree_max_heures' => (int) env('MAKARY_SESSION_DUREE_MAX_HEURES', 8),
    ],

    // Mention d'information des formulaires (E-01) : PROPOSITION à valider par le référent juridique.
    // Toute modification du texte change la version, enregistrée avec chaque demande (preuve de l'information donnée).
    'mention_information' => [
        'version' => 'e01-proposition-2026-10-22',
        'responsable' => 'Commune de Makary, représentée par son Maire',
        'finalite' => 'traiter votre demande et vous répondre',
        'destinataires' => 'les seuls agents de la mairie chargés de votre demande ; aucune transmission à des tiers',
        'contact' => '[À fournir par la Commune : adresse du référent des données personnelles]',
    ],

    // Double authentification (TOTP 2FA) : désactivée définitivement (en local et en production/en ligne).
    'mfa_active' => (bool) env('MAKARY_MFA_ACTIVE', false),
    'mfa_dispensee_en_local' => (bool) env('MAKARY_MFA_DISPENSEE_EN_LOCAL', false),

    // Téléversement (architecture §5) : tailles maximales à confirmer par l'administrateur technique.
    'televersement' => [
        'image_mo' => (int) env('MAKARY_TELEVERSEMENT_IMAGE_MO', 10),
        'document_mo' => (int) env('MAKARY_TELEVERSEMENT_DOCUMENT_MO', 20),
        // Côté le plus long d'une image après recompression, en pixels.
        'image_cote_max' => 2400,
    ],

    // Antivirus des fichiers téléversés : exécutable ClamAV (clamdscan ou clamscan) et ses options.
    // En hébergement mutualisé où ClamAV n'est pas disponible, MAKARY_ANTIVIRUS_REQUIS=false permet d'accepter
    // les images recompressées (assainies par GD) et les documents PDF validés sans bloquer la publication.
    'antivirus' => [
        'executable' => env('MAKARY_ANTIVIRUS'),
        'requis' => (bool) env('MAKARY_ANTIVIRUS_REQUIS', true),
        'options' => env('MAKARY_ANTIVIRUS_OPTIONS', '--no-summary'),
        'delai_secondes' => 120,
    ],

    'connexion' => [
        // Tentatives autorisées par minute, par adresse électronique et par adresse IP.
        'tentatives_par_minute' => 5,
    ],
];
