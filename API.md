# API REST des sets

## Authentification
- Les requêtes doivent être effectuées avec un compte possédant la capacité `manage_options`.
- Utilisez un nonce WordPress REST en l’envoyant dans l’en-tête `X-WP-Nonce`.
- En environnement de test sécurisé, vous pouvez recourir à l’authentification basique.

## Namespace
Toutes les routes sont exposées via `up-bulk-plugins-installer/v1`.

## Endpoints
- `GET /wp-json/up-bulk-plugins-installer/v1/sets`
  - Retourne un tableau de sets sauvegardés (`slug`, `name`, `items`, `meta`).
- `POST /wp-json/up-bulk-plugins-installer/v1/sets/<slug>/install`
  - Lance l’installation du set correspondant au `slug`.
  - Paramètre JSON optionnel : `manifest_target` (`theme`, `mu-plugins`, `plugins`) pour forcer la destination.

## Exemples `curl`
```bash
curl https://example.com/wp-json/up-bulk-plugins-installer/v1/sets \
  -H "X-WP-Nonce: <nonce>"

curl https://example.com/wp-json/up-bulk-plugins-installer/v1/sets/mu-plugins-cpt/install \
  -X POST \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: <nonce>" \
  -d '{"manifest_target":"mu-plugins"}'
```

## Réponses
- **GET**: tableau JSON listant chaque set complet.
- **POST**: objet JSON indiquant le nombre d’éléments traités, la destination effective et les messages d’exécution.

## Conseils
- Générez le nonce côté WordPress via `wp_create_nonce('wp_rest')`.
- Vérifiez que le `slug` transmis correspond exactement au fichier JSON de set.
- Utilisez `manifest_target` seulement si vous souhaitez outrepasser la destination par défaut définie dans le set.

---

# Onglet Clean — Documentation

## Types d’actions
- **remove_comments**: supprime les commentaires `/* ... */` et `// ...` des fichiers ciblés.
- **inline_php**: remplace `require/include(_once)` par le contenu des fichiers inclus, avec option de suppression des originaux.
- **inline_scss**: remplace `@import "...";` par le contenu des fichiers importés, avec option de suppression des originaux.
- **minify_js**: minification simple (retrait commentaires et espaces multiples) sur fichiers `.js`.
- **purge_directories**: supprime récursivement les sous-dossiers listés (ex: `node_modules`, `.git`).

## Schéma d’une action (JSON)
```json
{
  "slug": "nettoyage-js",
  "name": "Nettoyage JS",
  "type": "minify_js",
  "target_dir": "mu-plugins/gsap",
  "target_file": "",
  "extensions": ["js"],
  "directories": [],
  "recursive": true,
  "delete_originals": false
}
```

### Champs
- **slug**: identifiant unique (génère `config/clean-actions/<slug>.json`).
- **name**: libellé affiché.
- **type**: `remove_comments | inline_php | inline_scss | minify_js | purge_directories`.
- **target_dir**: dossier relatif à `wp-content/` (obligatoire si `target_file` vide).
- **target_file**: fichier cible relatif à `target_dir`. Si vide, l’action s’applique à tous les fichiers admissibles du dossier.
- **extensions**: liste d’extensions filtrantes (utile pour `remove_comments`).
- **directories**: liste des dossiers à supprimer (pour `purge_directories`).
- **recursive**: si vrai, parcourt les sous-dossiers (là où pertinent).
- **delete_originals**: si vrai, supprime les fichiers inclus/importés après inline.

## Règles d’application
- Si `target_file` est vide, la collecte se fait dans `target_dir` selon `extensions` et le type d’action.
- Sécurité: les chemins doivent rester sous `wp-content/` (contrôle appliqué lors de l’exécution).

## Ajout à un Set
- Dans l’onglet `Clean`, cochez la colonne “Add set” pour inclure l’action dans un Set.
- À l’installation du Set, les **générateurs** sont exécutés en premier, puis les **cleaners**.

## Exemples d’usage
- Supprimer commentaires `.php` et `.scss` dans `theme/assets` récursivement:
```json
{
  "slug": "strip-comments-assets",
  "name": "Strip comments assets",
  "type": "remove_comments",
  "target_dir": "themes/your-theme/assets",
  "extensions": ["php", "scss"],
  "recursive": true
}
```

- Inline imports SCSS dans `assets/scss/root.scss` et supprimer les partiels:
```json
{
  "slug": "inline-root-scss",
  "name": "Inline root SCSS",
  "type": "inline_scss",
  "target_dir": "themes/your-theme/assets/scss",
  "target_file": "root.scss",
  "delete_originals": true
}
```

- Purger `node_modules` et `.git` dans un dossier de travail:
```json
{
  "slug": "purge-node-git",
  "name": "Purge node & git",
  "type": "purge_directories",
  "target_dir": "plugins/test-sandbox",
  "directories": ["node_modules", ".git"]
}
