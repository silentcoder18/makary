<?php

// Fichier GÉNÉRÉ par « php artisan makary:permissions » depuis specs/securite/permissions.yaml.
// Ne pas modifier à la main : modifier le YAML, puis régénérer.

return array (
  'roles' => 
  array (
    'superadmin' => 
    array (
      'libelle' => 'Super Administrateur',
      'mfa' => false,
    ),
    'secretaire_general' => 
    array (
      'libelle' => 'Secrétaire Général',
      'mfa' => false,
    ),
    'admin_technique' => 
    array (
      'libelle' => 'Administrateur technique',
      'mfa' => false,
    ),
    'admin_editorial' => 
    array (
      'libelle' => 'Administrateur éditorial',
      'mfa' => true,
    ),
    'redacteur' => 
    array (
      'libelle' => 'Rédacteur',
      'mfa' => false,
    ),
    'validateur' => 
    array (
      'libelle' => 'Validateur',
      'mfa' => true,
    ),
    'responsable_projets' => 
    array (
      'libelle' => 'Responsable projets',
      'mfa' => true,
    ),
    'gestionnaire_partenaires' => 
    array (
      'libelle' => 'Gestionnaire des partenaires',
      'mfa' => true,
    ),
    'lecteur_auditeur' => 
    array (
      'libelle' => 'Lecteur / auditeur',
      'mfa' => false,
    ),
  ),
  'permissions' => 
  array (
    'contenu.lire_brouillons' => 
    array (
      'description' => 'Lire les brouillons et les éléments en revue',
      'journalisee' => false,
    ),
    'contenu.creer' => 
    array (
      'description' => 'Créer un brouillon',
      'journalisee' => false,
    ),
    'contenu.modifier' => 
    array (
      'description' => 'Modifier un brouillon',
      'journalisee' => false,
    ),
    'contenu.soumettre' => 
    array (
      'description' => 'Soumettre un élément à la revue (contenu, projet, document, partenaire, référentiel)',
      'journalisee' => true,
    ),
    'contenu.approuver' => 
    array (
      'description' => 'Approuver ou renvoyer un élément soumis',
      'journalisee' => true,
    ),
    'contenu.publier' => 
    array (
      'description' => 'Publier ou programmer un élément approuvé',
      'journalisee' => true,
    ),
    'contenu.retirer' => 
    array (
      'description' => 'Retirer un élément du site public (avec motif)',
      'journalisee' => true,
    ),
    'media.televerser' => 
    array (
      'description' => 'Téléverser un média (auteur et droit d\'usage obligatoires)',
      'journalisee' => false,
    ),
    'media.valider_droits' => 
    array (
      'description' => 'Confirmer le droit d\'usage d\'un média',
      'journalisee' => true,
    ),
    'projet.lire_interne' => 
    array (
      'description' => 'Lire les fiches et leurs données internes',
      'journalisee' => false,
    ),
    'projet.creer' => 
    array (
      'description' => 'Créer une fiche projet (statut « idée identifiée »)',
      'journalisee' => true,
    ),
    'projet.modifier' => 
    array (
      'description' => 'Modifier une fiche projet',
      'journalisee' => false,
    ),
    'projet.demander_transition' => 
    array (
      'description' => 'Demander un changement de statut',
      'journalisee' => true,
    ),
    'projet.valider_transition' => 
    array (
      'description' => 'Approuver ou refuser un changement de statut',
      'journalisee' => true,
    ),
    'projet.retirer_catalogue' => 
    array (
      'description' => 'Retirer une fiche du catalogue public (avec motif)',
      'journalisee' => true,
    ),
    'financement.modifier' => 
    array (
      'description' => 'Saisir les lignes recherché et sollicité',
      'journalisee' => true,
    ),
    'financement.valider_acquis' => 
    array (
      'description' => 'Valider une ligne de financement acquise, avec sa pièce',
      'journalisee' => true,
    ),
    'referentiel.modifier' => 
    array (
      'description' => 'Modifier localités, équipements, secteurs et catégories',
      'journalisee' => true,
    ),
    'partenaire.modifier' => 
    array (
      'description' => 'Modifier la fiche d\'un partenaire',
      'journalisee' => false,
    ),
    'document.gerer' => 
    array (
      'description' => 'Créer un document et ajouter une version',
      'journalisee' => false,
    ),
    'document.valider_diffusion' => 
    array (
      'description' => 'Accorder la diffusion publique d\'un document',
      'journalisee' => true,
    ),
    'document.archiver' => 
    array (
      'description' => 'Archiver un document (jamais de destruction)',
      'journalisee' => true,
    ),
    'demande.lire' => 
    array (
      'description' => 'Lire les demandes et leurs données personnelles',
      'journalisee' => true,
    ),
    'demande.traiter' => 
    array (
      'description' => 'Qualifier, affecter, répondre, clôturer',
      'journalisee' => true,
    ),
    'demande.exporter_personne' => 
    array (
      'description' => 'Exporter ou corriger les données d\'une personne (droits E-03)',
      'journalisee' => true,
    ),
    'demande.anonymiser' => 
    array (
      'description' => 'Anonymiser une demande avant son échéance (droit d\'effacement)',
      'journalisee' => true,
    ),
    'validation.affecter_relecteurs' => 
    array (
      'description' => 'Affecter les relecteurs et les périmètres de validation',
      'journalisee' => true,
    ),
    'parametre.modifier_editorial' => 
    array (
      'description' => 'Modifier menus, coordonnées officielles affichées, catégories',
      'journalisee' => true,
    ),
    'utilisateur.gerer' => 
    array (
      'description' => 'Créer, désactiver, réinitialiser un compte',
      'journalisee' => true,
    ),
    'role.attribuer' => 
    array (
      'description' => 'Attribuer ou retirer un rôle et un périmètre',
      'journalisee' => true,
    ),
    'parametre.modifier_technique' => 
    array (
      'description' => 'Modifier la configuration technique et les durées de conservation',
      'journalisee' => true,
    ),
    'traitement.modifier' => 
    array (
      'description' => 'Tenir le registre des traitements (E-02)',
      'journalisee' => true,
    ),
    'journal.consulter' => 
    array (
      'description' => 'Consulter le journal d\'audit et l\'historique',
      'journalisee' => true,
    ),
    'journal.exporter' => 
    array (
      'description' => 'Exporter le journal d\'audit',
      'journalisee' => true,
    ),
    'statistiques.consulter' => 
    array (
      'description' => 'Consulter les indicateurs du §12',
      'journalisee' => false,
    ),
  ),
  'attributions' => 
  array (
    'superadmin' => 
    array (
      'contenu.lire_brouillons' => 'toutes',
      'contenu.creer' => 'toutes',
      'contenu.modifier' => 'toutes',
      'contenu.soumettre' => 'toutes',
      'contenu.approuver' => 'toutes',
      'contenu.publier' => 'toutes',
      'contenu.retirer' => 'toutes',
      'media.televerser' => 'toutes',
      'media.valider_droits' => 'toutes',
      'projet.lire_interne' => 'toutes',
      'projet.creer' => 'toutes',
      'projet.modifier' => 'toutes',
      'projet.demander_transition' => 'toutes',
      'projet.valider_transition' => 'toutes',
      'projet.retirer_catalogue' => 'toutes',
      'financement.modifier' => 'toutes',
      'financement.valider_acquis' => 'toutes',
      'referentiel.modifier' => 'toutes',
      'partenaire.modifier' => 'toutes',
      'document.gerer' => 'toutes',
      'document.valider_diffusion' => 'toutes',
      'document.archiver' => 'toutes',
      'demande.lire' => 'toutes',
      'demande.traiter' => 'toutes',
      'demande.exporter_personne' => 'toutes',
      'demande.anonymiser' => 'toutes',
      'validation.affecter_relecteurs' => 'toutes',
      'parametre.modifier_editorial' => 'toutes',
      'utilisateur.gerer' => 'toutes',
      'role.attribuer' => 'toutes',
      'parametre.modifier_technique' => 'toutes',
      'traitement.modifier' => 'toutes',
      'journal.consulter' => 'toutes',
      'journal.exporter' => 'toutes',
      'statistiques.consulter' => 'toutes',
    ),
    'secretaire_general' => 
    array (
      'contenu.lire_brouillons' => 'toutes',
      'contenu.creer' => 'toutes',
      'contenu.modifier' => 'toutes',
      'contenu.soumettre' => 'toutes',
      'contenu.approuver' => 'toutes',
      'contenu.publier' => 'toutes',
      'contenu.retirer' => 'toutes',
      'media.televerser' => 'toutes',
      'media.valider_droits' => 'toutes',
      'projet.lire_interne' => 'toutes',
      'projet.creer' => 'toutes',
      'projet.modifier' => 'toutes',
      'projet.demander_transition' => 'toutes',
      'projet.valider_transition' => 'toutes',
      'projet.retirer_catalogue' => 'toutes',
      'financement.modifier' => 'toutes',
      'financement.valider_acquis' => 'toutes',
      'referentiel.modifier' => 'toutes',
      'partenaire.modifier' => 'toutes',
      'document.gerer' => 'toutes',
      'document.valider_diffusion' => 'toutes',
      'document.archiver' => 'toutes',
      'demande.lire' => 'toutes',
      'demande.traiter' => 'toutes',
      'demande.exporter_personne' => 'toutes',
      'demande.anonymiser' => 'toutes',
      'validation.affecter_relecteurs' => 'toutes',
      'parametre.modifier_editorial' => 'toutes',
      'utilisateur.gerer' => 'toutes',
      'role.attribuer' => 'toutes',
      'parametre.modifier_technique' => 'toutes',
      'traitement.modifier' => 'toutes',
      'journal.consulter' => 'toutes',
      'journal.exporter' => 'toutes',
      'statistiques.consulter' => 'toutes',
    ),
    'admin_technique' => 
    array (
      'utilisateur.gerer' => 'toutes',
      'role.attribuer' => 'toutes',
      'parametre.modifier_technique' => 'toutes',
      'traitement.modifier' => 'toutes',
      'journal.consulter' => 'toutes',
      'journal.exporter' => 'toutes',
      'statistiques.consulter' => 'toutes',
    ),
    'admin_editorial' => 
    array (
      'contenu.lire_brouillons' => 'toutes',
      'contenu.creer' => 'toutes',
      'contenu.modifier' => 'toutes',
      'contenu.soumettre' => 'toutes',
      'contenu.retirer' => 'toutes',
      'media.televerser' => 'toutes',
      'validation.affecter_relecteurs' => 'toutes',
      'parametre.modifier_editorial' => 'toutes',
      'referentiel.modifier' => 'toutes',
      'statistiques.consulter' => 'toutes',
    ),
    'redacteur' => 
    array (
      'contenu.lire_brouillons' => 'toutes',
      'contenu.creer' => 'toutes',
      'contenu.modifier' => 'propre',
      'contenu.soumettre' => 'propre',
      'media.televerser' => 'toutes',
      'document.gerer' => 'propre',
    ),
    'validateur' => 
    array (
      'contenu.lire_brouillons' => 'toutes',
      'projet.lire_interne' => 'toutes',
      'contenu.approuver' => 'perimetre',
      'contenu.publier' => 'perimetre',
      'contenu.retirer' => 'perimetre',
      'media.valider_droits' => 'perimetre',
      'projet.valider_transition' => 'perimetre',
      'projet.retirer_catalogue' => 'perimetre',
      'financement.valider_acquis' => 'perimetre',
      'document.valider_diffusion' => 'perimetre',
      'document.archiver' => 'perimetre',
      'statistiques.consulter' => 'toutes',
    ),
    'responsable_projets' => 
    array (
      'contenu.lire_brouillons' => 'toutes',
      'projet.lire_interne' => 'toutes',
      'projet.creer' => 'toutes',
      'projet.modifier' => 'toutes',
      'projet.demander_transition' => 'toutes',
      'projet.retirer_catalogue' => 'toutes',
      'financement.modifier' => 'toutes',
      'partenaire.modifier' => 'toutes',
      'media.televerser' => 'toutes',
      'document.gerer' => 'toutes',
      'contenu.soumettre' => 'propre',
      'statistiques.consulter' => 'toutes',
    ),
    'gestionnaire_partenaires' => 
    array (
      'demande.lire' => 'toutes',
      'demande.traiter' => 'toutes',
      'demande.exporter_personne' => 'toutes',
      'demande.anonymiser' => 'toutes',
      'partenaire.modifier' => 'toutes',
      'contenu.soumettre' => 'propre',
      'projet.lire_interne' => 'toutes',
      'statistiques.consulter' => 'toutes',
    ),
    'lecteur_auditeur' => 
    array (
      'journal.consulter' => 'toutes',
      'statistiques.consulter' => 'toutes',
      'projet.lire_interne' => 'toutes',
    ),
  ),
  'regles' => 
  array (
    'perimetres' => 
    array (
      0 => 'M01',
      1 => 'M02',
      2 => 'M03',
      3 => 'M04',
      4 => 'M05',
      5 => 'M06',
      6 => 'M07',
      7 => 'M08',
      8 => 'M09',
      9 => 'M10',
      10 => 'M11',
      11 => 'M12',
      12 => 'projets',
      13 => 'realisations',
      14 => 'juridique',
    ),
    'referents' => 
    array (
      'superadmin' => 
      array (
        'role' => 'superadmin',
        'perimetre' => 
        array (
        ),
      ),
      'secretaire_general' => 
      array (
        'role' => 'secretaire_general',
        'perimetre' => 
        array (
        ),
      ),
      'responsable_validation' => 
      array (
        'role' => 'validateur',
        'perimetre' => 
        array (
          0 => 'M01',
          1 => 'M02',
          2 => 'M04',
          3 => 'M06',
          4 => 'M08',
        ),
      ),
      'responsable_editorial' => 
      array (
        'role' => 'validateur',
        'perimetre' => 
        array (
          0 => 'M10',
        ),
      ),
      'referent_realisations' => 
      array (
        'role' => 'validateur',
        'perimetre' => 
        array (
          0 => 'realisations',
        ),
      ),
      'referent_projets_financements' => 
      array (
        'role' => 'validateur',
        'perimetre' => 
        array (
          0 => 'projets',
          1 => 'M03',
          2 => 'M05',
        ),
      ),
      'referent_juridique_donnees' => 
      array (
        'role' => 'validateur',
        'perimetre' => 
        array (
          0 => 'juridique',
          1 => 'M07',
        ),
      ),
      'administrateur_technique' => 
      array (
        'role' => 'admin_technique',
        'perimetre' => 
        array (
        ),
      ),
    ),
  ),
);
