# Up Bulk Plugin Installer

Installe, active et met à jour automatiquement une sélection de plugins et de thèmes essentiels depuis WordPress.org ou GitHub, via une page d’administration dédiée.

## Sommaire
- [Fonctionnalités](#fonctionnalités)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Utilisation](#utilisation)
  - [Plugins WordPress.org](#plugins-wordpressorg)
  - [Plugins GitHub](#plugins-github)
  - [Thèmes GitHub](#thèmes-github)
- [Mises à jour depuis GitHub](#mises-à-jour-depuis-github)
- [Détection automatique du fichier principal](#détection-automatique-du-fichier-principal)
- [Gestion des versions locales](#gestion-des-versions-locales)
- [Permissions et sécurité](#permissions-et-sécurité)
- [Limitations connues](#limitations-connues)
- [Dépannage](#dépannage)

## Fonctionnalités
- Installation et activation de plugins depuis WordPress.org.
- Installation, activation et mise à jour de plugins et thèmes depuis GitHub (via la dernière release ou la branche principale si aucune release n’existe).
- Détection automatique du fichier principal d’un plugin GitHub.
- Indication de l’état actuel: installé/actif, installé/inactif, non installé.
- Comparaison de version locale vs dernière release GitHub et bouton de mise à jour.
- Activation rapide d’un plugin installé ou d’un thème installé.

La page d’administration est disponible dans: `Tableau de bord > Installer Plugins`.

## Prérequis
- WordPress 6.x (recommandé).
- Rôle administrateur (capacité `manage_options`).
- Accès réseau sortant depuis le serveur (pour `api.github.com` et les téléchargements ZIP).

## Installation
1. Copier le dossier du plugin dans `wp-content/plugins/up-bulk-plugins-installer/`.
2. Activer le plugin depuis `Extensions > Extensions installées`.

## Utilisation
Accédez au menu `Installer Plugins` pour voir trois sections avec des tableaux d’actions.

### Plugins WordPress.org
- Le tableau liste des plugins prédéfinis.
- Pour chaque plugin:
  - Actif: indicateur ✅.
  - Installé mais inactif: bouton Activer.
  - Non installé: bouton Installer qui télécharge et active le plugin.

### Plugins GitHub
- Le tableau liste des dépôts GitHub configurés.
- Pour chaque dépôt:
  - Le plugin est détecté par son slug (nom du repo) et son fichier principal (auto).
  - États: ✅ actif, ⚠️ installé mais inactif, ou non installé.
  - Boutons: Installer, Activer, Mettre à jour (si une version plus récente est disponible).

### Thèmes GitHub
- Le tableau liste les dépôts GitHub configurés en tant que thèmes.
- États: ✅ actif, ⚠️ installé mais inactif, ou non installé.
- Boutons: Installer, Activer, Mettre à jour (si une version plus récente est disponible).

## Mises à jour depuis GitHub
- Le plugin interroge `https://api.github.com/repos/{user}/{repo}/releases/latest`.
- Si une release existe: téléchargement via `zipball_url` de la release la plus récente.
- Sinon: fallback sur `main.zip`, puis `master.zip`.
- En mode mise à jour, l’ancienne version du dossier ciblé est supprimée puis remplacée par le contenu extrait.

Conseils:
- Publiez des tags sémantiques (ex: `v1.2.3`) sur GitHub pour permettre la comparaison automatique des versions.

## Détection automatique du fichier principal
Pour les plugins GitHub, si le fichier principal n’est pas spécifié, le plugin scanne les fichiers PHP à la racine du slug et détecte celui qui contient un en-tête WordPress valide (`Plugin Name`).

## Gestion des versions locales
- Plugins: lecture de la `Version` depuis l’en-tête du fichier principal.
- Thèmes: lecture de la version depuis les métadonnées du thème installé.
- La comparaison s’appuie sur `version_compare()`.

## Permissions et sécurité
- La page est accessible aux administrateurs (`manage_options`).
- Téléchargements et dézippage sont délégués aux APIs WordPress (`download_url`, `unzip_file`, `WP_Filesystem`).
- Les chemins et entrées utilisateurs sont assainis via les fonctions WordPress standard.

## Limitations connues
- Les dépôts GitHub privés nécessitent une autre méthode d’authentification (non prise en charge nativement ici).
- La structure ZIP doit contenir le projet dans un dossier unique à la racine (convention GitHub). Un renommage automatique tente d’aligner le nom de dossier avec le slug.
- Les dépendances spécifiques de certains plugins/thèmes ne sont pas gérées automatiquement (composer, npm, build, etc.).

## Dépannage
- Erreur de téléchargement GitHub: vérifier la connectivité serveur et les limitations réseau/pare-feu.
- Aucune release détectée: publier une release sur GitHub ou s’assurer que la branche `main`/`master` existe.
- Fichier principal introuvable: vérifier que l’en-tête du plugin contient bien `Plugin Name`.
- Problème de droits d’écriture: vérifier les permissions du système de fichiers sur `wp-content/plugins/` et `wp-content/themes/`.

---

Auteur: GEHIN Nicolas
Version du plugin: 2.1
