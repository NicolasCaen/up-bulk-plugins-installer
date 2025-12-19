# Changelog

## [1.5.2] - 2025-12-19
- Ajout / mise à jour de dépôts GitHub dans la configuration (plugins, thèmes, fonctionnalités) pour l’installation et la mise à jour.
- UI : réorganisation des onglets avec « Plugins GitHub » en premier et « Plugins WordPress.org » en second.

## [1.5.1] - 2025-10-30
- Harmonisation de la version du plugin et de la documentation pour la release 1.5.1.
- Préparation du package en vue de la distribution.

## [1.5.0] - 2025-10-29
- Normalisation du dossier d’installation GitHub: les archives sont désormais renommées en `wp-content/plugins/<repo>` (ou `wp-content/themes/<repo>`), au lieu de `Owner-Repo-<hash>`.
- Détection renforcée du dossier extrait après dézippage (prend en compte les variations de nom GitHub et renomme correctement).
- Correction de configuration: validation des clés `user/repo` dans `config/plugins-github.php` pour un slug fiable et la détection du fichier principal.
- Confirmation des statuts d’état: affichage « Actif » pour les extensions activées et « Mettre à jour » quand une release GitHub plus récente est disponible.
- Petites améliorations et messages plus clairs pendant les étapes d’installation/mise à jour.

## [1.4] - 2025-10-21
- Gestion des sets intégrée dans tous les tableaux (plugins, thèmes, fonctionnalités, patterns) avec destinations et chemins personnalisés.
- Ajustements UI : colonne « Add set » uniformisée, regroupement des contrôles d’actions et styles revus.
- Documentation enrichie (sous-menu « Doc API REST », fichiers `API.md` et README mis à jour).
- Améliorations JavaScript : collecte des destinations/chemins dans les sets, rechargement cohérent des formulaires.

## [1.3] - 2025-09-15
- Ajout du support des manifests de patterns multiples via `config/manifest-tabs.php`.
- Meilleure détection des fichiers principaux pour les plugins GitHub et indicateurs d’état (installé/actif, etc.).
- Optimisations diverses sur l’installation depuis GitHub (gestion des mises à jour et fallback de branches).
