# Générateurs de fichiers

Ce module permet de générer du code à partir d'un dossier source vers un fichier cible, selon un **type** de générateur. Les générateurs sont enregistrés au format JSON dans `config/file-generators/` pour pouvoir être rejoués.

## Types disponibles

- **php_include**
  - Action: ajoute des `require_once` vers tous les fichiers PHP trouvés.
  - Exemple source: `inc/parts`
  - Exemple cible: `inc/includes.php`
- **scss_import**
  - Action: ajoute des `@import` pour chaque partiel SCSS (`_*.scss`) trouvé, relatifs au fichier cible.
  - Exemple source: `assets/scss/blocks`
  - Exemple cible: `assets/scss/root.scss`
- **js_register**
  - Action: génère un `add_action('wp_enqueue_scripts', ...)` avec `wp_register_script` pour chaque fichier JS.
  - Exemple source: `assets/js/modules`
  - Exemple cible: `functions/enqueue-scripts.php`
- **js_enqueue**
  - Action: génère un `add_action('wp_enqueue_scripts', ...)` avec `wp_enqueue_script` pour chaque fichier JS.
  - Exemple source: `assets/js/entries`
  - Exemple cible: `functions/enqueue-scripts.php`

## Champs du formulaire

- **Type**: choisit la logique de génération (voir ci-dessus). Les champs inutiles sont automatiquement cachés.
- **Nom**: label lisible pour retrouver le générateur.
- **Slug**: identifiant unique (a-z0-9-). Sert de nom de fichier JSON.
- **Destination**: base du chemin (Thème actif, MU-Plugins, Plugins).
- **Dossier source**: chemin relatif à la destination, scanné récursivement.
- **Fichier cible**: fichier à créer/mettre à jour (relatif à la destination).
- **Handle (JS)**: préfixe du handle par fichier JS (uniquement pour js_*).
- **Dépendances (JS)**: liste CSV (ex: `jquery,wp-element`).
- **Charger en footer (JS)**: coche pour placer les scripts en footer.

Sous chaque champ, un exemple contextuel et des aides sont affichés en fonction du type sélectionné. Une **description latérale** résume ce que fait le générateur.

## Fonctionnement

1. Sélectionnez le type, puis renseignez les champs. Les exemples s'adaptent.
2. Enregistrez: un fichier JSON est écrit dans `config/file-generators/<slug>.json`.
3. Cliquez sur "Générer":
   - Le dossier source est scanné (extensions selon le type).
   - Les lignes correspondantes sont calculées et insérées dans le fichier cible entre des marqueurs:
     - `/* ----- <type> ----- */` ... `/* ----- <type> fin ----- */`
   - Pour les types JS, les lignes sont encapsulées dans `add_action('wp_enqueue_scripts', ...)`.

## Bonnes pratiques

- Pour SCSS, n'importe que des partiels (`_nom.scss`).
- Évitez de mettre le fichier cible dans le dossier source.
- Pour JS, utilisez un handle base simple (ex: `theme-scripts`).

## Dépannage

- Rien ne s'insère: vérifiez que le dossier source existe et contient des fichiers de l'extension attendue.
- Chemins: toujours relatifs à la destination sélectionnée.
- Conflits d'édition: le plugin protège par blocs marqués. Si besoin, supprimez manuellement le bloc puis régénérez.
