<?php
/*
Plugin Name: Up Bulk Plugin Installer
Description: Installe, active et met à jour automatiquement une sélection de plugins et thèmes essentiels depuis WordPress.org ou GitHub.
Version: 1.2
Author: GEHIN Nicolas
*/

if (!defined('ABSPATH')) {
    exit;
}

function pubpi_render_manifest_docs_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    echo '<div class="wrap">';
    echo '<h1>Ajouter un manifest.json de patterns</h1>';
    echo '<p>Cette section explique comment déclarer un dépôt GitHub contenant un fichier <code>manifest.json</code> afin d&#8217;afficher et d&#8217;installer ses patterns dans l&#8217;onglet ad hoc.</p>';

    echo '<h2>1. Préparer le manifeste GitHub</h2>';
    echo '<ol>';
    echo '<li>Créez un fichier <code>manifest.json</code> à la racine de votre dépôt ou dans un répertoire dédié.</li>';
    echo '<li>Ajoutez-y une clé <code>patterns</code> contenant un tableau d&#8217;objets. Chaque objet doit au minimum définir <code>slug</code>, <code>name</code>, <code>files</code> et <code>install</code>.</li>';
    echo '<li>Référencez les fichiers à copier (JSON, CSS, JS, PHP…) dans <code>files</code> et indiquez le dossier de destination dans <code>install</code> (ex : <code>patterns</code>, <code>assets/css</code>).</li>';
    echo '<li>Optionnel : ajoutez <code>description</code>, <code>categories</code> et <code>preview</code> pour enrichir l&#8217;affichage.</li>';
    echo '</ol>';

    echo '<h2>2. Déclarer le dépôt dans le plugin</h2>';
    echo '<ol>';
    echo '<li>Ouvrez le fichier <code>up-bulk-plugins-installer.php</code>.</li>';
    echo '<li>Dans le tableau <code>$github_manifest_tabs</code>, ajoutez ou complétez un onglet en définissant une clé (slug de l&#8217;onglet), son <code>label</code> et la liste des dépôts.</li>';
    echo '<li>Pour chaque dépôt, précisez <code>name</code>, <code>manifest</code> (chemin relatif du manifest) et <code>branch</code> si nécessaire.</li>';
    echo '</ol>';

    echo '<pre style="background:#f6f7f7; border:1px solid #ccd0d4; padding:12px;">$github_manifest_tabs = [
    ' . htmlspecialchars("patterns" , ENT_QUOTES) . ' => [
        ' . htmlspecialchars("label" , ENT_QUOTES) . ' => ' . htmlspecialchars("'Patterns (manifest)'", ENT_QUOTES) . ',
        ' . htmlspecialchars("manifests" , ENT_QUOTES) . ' => [
            ' . htmlspecialchars("'Utilisateur/mon-repo'" , ENT_QUOTES) . ' => [
                ' . htmlspecialchars("'name' => 'Ma bibliothèque'" , ENT_QUOTES) . ',
                ' . htmlspecialchars("'manifest' => 'manifest.json'" , ENT_QUOTES) . ',
                ' . htmlspecialchars("'branch' => 'main'" , ENT_QUOTES) . ',
            ],
        ],
    ],
];</pre>';

    $manifest_example = <<<JSON
{
    "patterns": [
        {
            "slug": "hero-cta",
            "name": "Hero avec CTA",
            "description": "Bloc principal avec image de fond et appel à l'action",
            "categories": ["Hero", "Landing"],
            "preview": "previews/hero-cta.jpg",
            "files": {
                "pattern": "patterns/hero-cta.json",
                "style": "assets/css/hero-cta.css",
                "script": "assets/js/hero-cta.js",
                "template": "templates/hero-cta.php"
            },
            "install": {
                "pattern": "patterns",
                "style": "assets/css",
                "script": "assets/js",
                "template": "templates"
            }
        },
        {
            "slug": "faq-accordion",
            "name": "FAQ accordéon",
            "description": "Liste de questions/réponses repliables",
            "categories": ["FAQ", "Contenu"],
            "files": {
                "pattern": "patterns/faq-accordion.json",
                "style": "assets/css/faq.css"
            },
            "install": {
                "pattern": "patterns",
                "style": "assets/css"
            }
        }
    ]
}
JSON;

    echo '<h2>Exemple de manifest complet</h2>';
    echo '<p>Cet exemple montre deux patterns avec plusieurs fichiers associés et des répertoires de destination distincts dans le thème actif.</p>';
    echo '<pre style="background:#1e1e1e; color:#f5f5f5; border:1px solid #111; padding:12px; overflow:auto;"><code>' . esc_html($manifest_example) . '</code></pre>';

    echo '<h2>3. Ajouter des dépôts via le filtre <code>pubpi_manifest_tabs</code></h2>';
    echo '<p>Vous pouvez étendre la configuration par défaut depuis un thème ou un plugin en utilisant le filtre <code>pubpi_manifest_tabs</code>. L&#8217;exemple suivant ajoute un nouvel onglet et un dépôt supplémentaire :</p>';

    $filter_snippet = "add_filter('pubpi_manifest_tabs', function (\n" .
        "    array \$tabs\n" .
        ") {\n" .
        "    \$tabs['custom-library'] = [\n" .
        "        'label' => 'Bibliothèque interne',\n" .
        "        'manifests' => [\n" .
        "            'organisation/patterns-repo' => [\n" .
        "                'name' => 'Patterns internes',\n" .
        "                'manifest' => 'build/manifest.json',\n" .
        "                'branch' => 'main',\n" .
        "            ],\n" .
        "        ],\n" .
        "    ];\n\n" .
        "    return \$tabs;\n" .
        "});";

    echo '<pre style="background:#23282d; color:#f5f7f9; border:1px solid #111; padding:12px; overflow:auto;"><code>' . esc_html($filter_snippet) . '</code></pre>';

    echo '<h2>4. Vérifier depuis l&#8217;interface</h2>';
    echo '<ol>';
    echo '<li>Rechargez la page principale « Installer Plugins ».</li>';
    echo '<li>Un nouvel onglet apparaît avec les patterns référencés.</li>';
    echo '<li>Cliquez sur « Installer dans le thème » pour déployer un pattern dans le thème actif.</li>';
    echo '</ol>';

    echo '<p>Pour chaque ajout ou modification du manifest, videz le cache navigateur si nécessaire et vérifiez que les chemins indiqués dans <code>files</code> existent bien dans l&#8217;archive GitHub.</p>';
    echo '</div>';
}

add_action('admin_menu', 'pubpi_add_admin_page');

function pubpi_add_admin_page() {
    add_menu_page(
        'Bulk Plugin Installer',
        'Installer Plugins',
        'manage_options',
        'bulk-plugin-installer',
        'pubpi_render_admin_page',
        'dashicons-download',
        99
    );

    add_submenu_page(
        'bulk-plugin-installer',
        'Documentation manifest',
        'Doc manifest',
        'manage_options',
        'bulk-plugin-installer-manifest-docs',
        'pubpi_render_manifest_docs_page'
    );
}

function pubpi_render_admin_page() {
    // Plugins WordPress.org
    $plugins = [
        'contact-form-7/wp-contact-form-7.php' => [
            'name' => 'Contact Form 7',
            'description' => 'pour les formulaires de contact',
            'categories' => ['Formulaire', 'Marketing'],
        ],
        'wp-umbrella/wp-umbrella.php' => [
            'name' => 'WP Umbrella',
            'description' => 'pour la maintenance',
            'categories' => ['Maintenance'],
        ],
        'wordpress-seo/wp-seo.php' => [
            'name' => 'Yoast SEO',
            'description' => 'pour le référencement',
            'categories' => ['SEO'],
        ],
        'updraftplus/updraftplus.php' => [
            'name' => 'UpdraftPlus',
            'description' => 'pour la sauvegarde',
            'categories' => ['Sauvegarde'],
        ],
        'wp-super-cache/wp-cache.php' => [
            'name' => 'WP Super Cache',
            'description' => 'pour la performance',
            'categories' => ['Performance'],
        ],
        'advanced-custom-fields/acf.php' => [
            'name' => 'Advanced Custom Fields (free)',
            'description' => 'pour les champs personnalisés',
            'categories' => ['Gutenberg', 'Champs personnalisés'],
        ],
        'admin-menu-editor/admin-menu-editor.php' => [
            'name' => 'Admin Menu Editor (free)',
            'description' => 'pour personnaliser le menu',
            'categories' => ['Administration'],
        ],
        'post-types-order/post-types-order.php' => [
            'name' => 'Post Types Order',
            'description' => 'pour personnaliser l\'ordre des types de contenu',
            'categories' => ['Contenu'],
        ],
        'safe-svg/safe-svg.php' => [
            'name' => 'Safe SVG',
            'description' => '',
            'categories' => ['Médias'],
        ],
        'wp-media-folder/wp-media-folder.php' => [
            'name' => 'WP Media Folder (JoomUnited)',
            'description' => '',
            'categories' => ['Médias'],
        ],
        'advanced-custom-fields-pro/acf.php' => [
            'name' => 'Advanced Custom Fields PRO',
            'description' => '',
            'categories' => ['Gutenberg', 'Champs personnalisés'],
        ],
        'admin-menu-editor-pro/admin-menu-editor.php' => [
            'name' => 'Admin Menu Editor PRO',
            'description' => '',
            'categories' => ['Administration'],
        ],
        'gravityforms/gravityforms.php' => [
            'name' => 'Gravity Forms',
            'description' => '',
            'categories' => ['Formulaire'],
        ],
        'wp-media-folder-pro/wp-media-folder.php' => [
            'name' => 'WP Media Folder PRO',
            'description' => '',
            'categories' => ['Médias'],
        ],
    ];

    // Plugins GitHub (format: 'user/repo' => ['name' => 'Nom', 'main_file' => 'fichier-principal.php'])
    $github_plugins = [
        'NicolasCaen/up-gutenberg-query-filter' => [
            'name' => 'Up Gutenberg Query Filter',
            'description' => 'Plugin permettant de filtrer les boucles Query Loop.',
            'categories' => ['Gutenberg', 'Filtres'],
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-binding-lorem' => [
            'name' => 'Up Binding Lorem',
            'description' => 'AJoute le block binding Lorem.',
            'categories' => ['Gutenberg', 'Bindings'],
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-variation-generator' => [
            'name' => 'Up Variation Generator',
            'description' => '',
            'categories' => ['Gutenberg', 'Variations'],
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-gutenberg-binding-collection' => [
            'name' => 'Up Gutenberg Binding Collection',
            'description' => '',
            'categories' => ['Gutenberg', 'Bindings'],
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-gutenberg-bindings-interface' => [
            'name' => 'Up Gutenberg Bindings Interface',
            'description' => '',
            'categories' => ['Gutenberg', 'Interface'],
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-gutenberg-metabox' => [
            'name' => 'Up Gutenberg Metabox',
            'description' => '',
            'categories' => ['Gutenberg', 'Metabox'],
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-library-generator' => [
            'name' => 'Up Library Generator',
            'description' => '',
            'categories' => ['Gutenberg', 'Outils'],
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-shortcodes-library' => [
            'name' => 'Up Shortcodes Library',
            'description' => '',
            'categories' => ['Shortcodes'],
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-section-styles' => [
            'name' => 'Up Section Styles',
            'description' => '',
            'categories' => ['Gutenberg', 'Styles'],
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-theme-generator' => [
            'name' => 'Up Theme Generator',
            'description' => '',
            'categories' => ['Thème'],
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-gsap-animate' => [
            'name' => 'Up GSAP Animate',
            'description' => '',
            'categories' => ['Animation'],
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-gsap-animate-2' => [
            'name' => 'Up GSAP Animate 2',
            'description' => '',
            'categories' => ['Animation'],
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-wp-resize-admin-aside' => [
            'name' => 'Up WP Resize Admin Aside',
            'description' => '',
            'categories' => ['Administration'],
            'main_file' => '' // Auto-détection
        ],
    ];

    $github_features = [
        'NicolasCaen/up-feature-custom-hooks' => [
            'name' => 'Hooks personnalisés',
            'description' => 'Ajoute des hooks utiles pour les thèmes.',
            'file' => 'feature-hooks.php',
            'categories' => ['Hooks', 'Thème'],
            'branch' => 'Master',
        ],
        'NicolasCaen/up-feature-media-enhancements' => [
            'name' => 'Améliorations médias',
            'description' => 'Fonctions utilitaires pour la gestion des médias.',
            'file' => 'media-enhancements.php',
            'categories' => ['Médias'],
            'branch' => 'Master',
        ],
    ];

    // Bibliothèques de patterns via manifest.json (groupées par onglet)
    $manifest_tabs_config = __DIR__ . '/config/manifest-tabs.php';
    $github_manifest_tabs = file_exists( $manifest_tabs_config ) ? include $manifest_tabs_config : [];

    $github_manifest_tabs = apply_filters('pubpi_manifest_tabs', $github_manifest_tabs);

    // Thèmes GitHub (format: 'user/repo' => ['name' => 'Nom du thème'])
    $github_themes = [
        'NicolasCaen/ng1-base' => [
            'name' => 'NG1 Base Theme',
            'categories' => ['Thème', 'Starter'],
        ],
    ];

    $wp_categories = pubpi_extract_categories($plugins);
    $github_plugin_categories = pubpi_extract_categories($github_plugins);
    $github_theme_categories = pubpi_extract_categories($github_themes);
    $github_feature_categories = pubpi_extract_categories($github_features);
    $manifest_tabs = pubpi_prepare_manifest_tabs($github_manifest_tabs);

    echo '<div class="wrap"><h1>Installer & Mettre à jour des plugins et thèmes</h1>';
    echo '<h2 class="nav-tab-wrapper pubpi-tabs-nav">';
    echo '<a href="#pubpi-tab-wp" class="nav-tab nav-tab-active">Plugins WordPress.org</a>';
    echo '<a href="#pubpi-tab-github-plugins" class="nav-tab">Plugins GitHub</a>';
    echo '<a href="#pubpi-tab-github-themes" class="nav-tab">Thèmes GitHub</a>';
    foreach ($manifest_tabs as $manifest_key => $manifest_tab) {
        $manifest_tab_id = 'pubpi-tab-manifest-' . esc_attr(sanitize_title($manifest_key));
        echo '<a href="#' . esc_attr($manifest_tab_id) . '" class="nav-tab">' . esc_html($manifest_tab['label']) . '</a>';
    }
    echo '<a href="#pubpi-tab-features" class="nav-tab">Fonctionnalités</a>';
    echo '</h2>';

    // Tab: WordPress.org
    echo '<div id="pubpi-tab-wp" class="pubpi-tab-panel is-active">';
    if (!empty($wp_categories)) {
        echo '<div class="pubpi-category-filters" data-target="#pubpi-wp-plugins">';
        echo '<button type="button" class="button pubpi-filter-btn active" data-category="__all">Tous</button>';
        foreach ($wp_categories as $category) {
            echo '<button type="button" class="button pubpi-filter-btn" data-category="' . esc_attr(pubpi_category_slug($category)) . '">' . esc_html($category) . '</button>';
        }
        echo '</div>';
    }
    echo '<table id="pubpi-wp-plugins" class="widefat fixed striped"><thead><tr><th>Nom du plugin</th><th>Description</th><th>Catégories</th><th>Action</th></tr></thead><tbody>';

    foreach ($plugins as $plugin_path => $plugin_data) {
        if (is_array($plugin_data)) {
            $plugin_name = $plugin_data['name'];
            $plugin_description = $plugin_data['description'] ?? '';
        } else {
            $plugin_name = $plugin_data;
            $plugin_description = '';
        }
        $plugin_categories = pubpi_get_item_categories(is_array($plugin_data) ? $plugin_data : []);
        $category_slugs = array_map('pubpi_category_slug', $plugin_categories);
        $row_categories_attr = empty($category_slugs) ? '' : implode(' ', $category_slugs);
        $is_installed = file_exists(WP_PLUGIN_DIR . '/' . dirname($plugin_path));
        $is_active = is_plugin_active($plugin_path);

        echo '<tr data-categories="' . esc_attr($row_categories_attr) . '">';
        echo '<td><strong>' . esc_html($plugin_name) . '</strong></td>';
        if (!empty($plugin_description)) {
            echo '<td>' . esc_html($plugin_description) . '</td>';
        } else {
            echo '<td>&mdash;</td>';
        }
        if (!empty($plugin_categories)) {
            echo '<td>' . esc_html(implode(', ', $plugin_categories)) . '</td>';
        } else {
            echo '<td>&mdash;</td>';
        }
        echo '<td>';

        if ($is_active) {
            echo '<span style="color:green;">✅ Actif</span>';
        } elseif ($is_installed) {
            echo '<span style="color:orange;">⚠️ Installé mais inactif</span>';
            echo '<form method="post" style="display:inline; margin-left:10px;">';
            echo '<input type="hidden" name="pubpi_activate_plugin" value="' . esc_attr($plugin_path) . '" />';
            submit_button('Activer', 'secondary small', 'pubpi_activate', false);
            echo '</form>';
        } else {
            echo '<form method="post" style="display:inline;">';
            echo '<input type="hidden" name="pubpi_plugin_path" value="' . esc_attr($plugin_path) . '" />';
            echo '<input type="hidden" name="pubpi_plugin_name" value="' . esc_attr($plugin_name) . '" />';
            submit_button('Installer', 'primary small', 'pubpi_install', false);
            echo '</form>';
        }

        echo '</td></tr>';
    }

    echo '</tbody></table>';
    echo '</div>';

    // Tab: GitHub Plugins
    echo '<div id="pubpi-tab-github-plugins" class="pubpi-tab-panel">';
    if (!empty($github_plugin_categories)) {
        echo '<div class="pubpi-category-filters" data-target="#pubpi-github-plugins">';
        echo '<button type="button" class="button pubpi-filter-btn active" data-category="__all">Tous</button>';
        foreach ($github_plugin_categories as $category) {
            echo '<button type="button" class="button pubpi-filter-btn" data-category="' . esc_attr(pubpi_category_slug($category)) . '">' . esc_html($category) . '</button>';
        }
        echo '</div>';
    }
    echo '<table id="pubpi-github-plugins" class="widefat fixed striped"><thead><tr><th>Nom du plugin</th><th>Description</th><th>Catégories</th><th>Repository</th><th>Action</th></tr></thead><tbody>';

    // Section Plugins GitHub

    foreach ($github_plugins as $repo => $plugin_data) {
        $plugin_name = $plugin_data['name'];
        $plugin_description = $plugin_data['description'] ?? '';
        $main_file = $plugin_data['main_file'];
        $slug = basename($repo);
        $plugin_categories = pubpi_get_item_categories($plugin_data);
        $category_slugs = array_map('pubpi_category_slug', $plugin_categories);
        $row_categories_attr = empty($category_slugs) ? '' : implode(' ', $category_slugs);

        if (empty($main_file)) {
            $main_file = pubpi_find_plugin_main_file($slug);
        }

        $plugin_path = $slug . '/' . $main_file;
        $is_installed = file_exists(WP_PLUGIN_DIR . '/' . $slug);
        $is_active = $is_installed && !empty($main_file) && is_plugin_active($plugin_path);

        echo '<tr data-categories="' . esc_attr($row_categories_attr) . '">';
        echo '<td><strong>' . esc_html($plugin_name) . '</strong></td>';
        if (!empty($plugin_description)) {
            echo '<td>' . esc_html($plugin_description) . '</td>';
        } else {
            echo '<td>&mdash;</td>';
        }
        if (!empty($plugin_categories)) {
            echo '<td>' . esc_html(implode(', ', $plugin_categories)) . '</td>';
        } else {
            echo '<td>&mdash;</td>';
        }
        echo '<td><code>' . esc_html($repo) . '</code></td>';
        echo '<td>';

        if ($is_active) {
            echo '<span style="color:green;">✅ Actif</span>';
        } elseif ($is_installed) {
            echo '<span style="color:orange;">⚠️ Installé mais inactif</span>';
            echo '<form method="post" style="display:inline; margin-left:10px;">';
            echo '<input type="hidden" name="pubpi_activate_plugin" value="' . esc_attr($plugin_path) . '" />';
            submit_button('Activer', 'secondary small', 'pubpi_activate', false);
            echo '</form>';
        }

        // AJOUTÉ : Logique de mise à jour
        if ($is_installed) {
            $local_version = pubpi_get_local_version($slug, 'plugin');
            $remote_release = pubpi_get_latest_release_info($repo);
            if ($remote_release && version_compare($remote_release['version'], $local_version, '>')) {
                echo '<div style="display:inline-block; margin-left:10px; color:#c66900;">';
                echo '🚀 Version ' . esc_html($remote_release['version']) . ' disponible ! (actuelle: ' . esc_html($local_version) . ')';
                echo '<form method="post" style="display:inline; margin-left:10px;">';
                echo '<input type="hidden" name="pubpi_github_repo" value="' . esc_attr($repo) . '" />';
                echo '<input type="hidden" name="pubpi_github_name" value="' . esc_attr($plugin_name) . '" />';
                echo '<input type="hidden" name="pubpi_type" value="plugin" />';
                submit_button('Mettre à jour', 'primary small', 'pubpi_update_github', false);
                echo '</form></div>';
            }
        }

        if (!$is_installed) {
            echo '<form method="post" style="display:inline;">';
            echo '<input type="hidden" name="pubpi_github_repo" value="' . esc_attr($repo) . '" />';
            echo '<input type="hidden" name="pubpi_github_name" value="' . esc_attr($plugin_name) . '" />';
            echo '<input type="hidden" name="pubpi_github_main_file" value="' . esc_attr($main_file) . '" />';
            echo '<input type="hidden" name="pubpi_type" value="plugin" />';
            submit_button('Installer', 'primary small', 'pubpi_install_github', false);
            echo '</form>';
        }

        echo '</td></tr>';
    }

    echo '</tbody></table>';
    echo '</div>';

    // Tab: GitHub Themes
    echo '<div id="pubpi-tab-github-themes" class="pubpi-tab-panel">';
    if (!empty($github_theme_categories)) {
        echo '<div class="pubpi-category-filters" data-target="#pubpi-github-themes">';
        echo '<button type="button" class="button pubpi-filter-btn active" data-category="__all">Tous</button>';
        foreach ($github_theme_categories as $category) {
            echo '<button type="button" class="button pubpi-filter-btn" data-category="' . esc_attr(pubpi_category_slug($category)) . '">' . esc_html($category) . '</button>';
        }
        echo '</div>';
    }
    echo '<table id="pubpi-github-themes" class="widefat fixed striped"><thead><tr><th>Nom du thème</th><th>Catégories</th><th>Repository</th><th>Action</th></tr></thead><tbody>';

    foreach ($github_themes as $repo => $theme_data) {
        if (is_array($theme_data)) {
            $theme_name = $theme_data['name'];
            $theme_categories = pubpi_get_item_categories($theme_data);
        } else {
            $theme_name = $theme_data;
            $theme_categories = [];
        }
        $slug = basename($repo);
        $theme = wp_get_theme($slug);
        $is_installed = $theme->exists();
        $is_active = (get_stylesheet() === $slug);
        $category_slugs = array_map('pubpi_category_slug', $theme_categories);
        $row_categories_attr = empty($category_slugs) ? '' : implode(' ', $category_slugs);

        echo '<tr data-categories="' . esc_attr($row_categories_attr) . '">';
        echo '<td><strong>' . esc_html($theme_name) . '</strong></td>';
        if (!empty($theme_categories)) {
            echo '<td>' . esc_html(implode(', ', $theme_categories)) . '</td>';
        } else {
            echo '<td>&mdash;</td>';
        }
        echo '<td><code>' . esc_html($repo) . '</code></td>';
        echo '<td>';

        if ($is_active) {
            echo '<span style="color:green;">✅ Actif</span>';
        } elseif ($is_installed) {
            echo '<span style="color:orange;">⚠️ Installé mais inactif</span>';
            echo '<form method="post" style="display:inline; margin-left:10px;">';
            echo '<input type="hidden" name="pubpi_activate_theme" value="' . esc_attr($slug) . '" />';
            submit_button('Activer', 'secondary small', 'pubpi_activate_theme_btn', false);
            echo '</form>';
        }

        // AJOUTÉ : Logique de mise à jour pour les thèmes
        if ($is_installed) {
            $local_version = pubpi_get_local_version($slug, 'theme');
            $remote_release = pubpi_get_latest_release_info($repo);
            if ($remote_release && version_compare($remote_release['version'], $local_version, '>')) {
                echo '<div style="display:inline-block; margin-left:10px; color:#c66900;">';
                echo '🚀 Version ' . esc_html($remote_release['version']) . ' disponible ! (actuelle: ' . esc_html($local_version) . ')';
                echo '<form method="post" style="display:inline; margin-left:10px;">';
                echo '<input type="hidden" name="pubpi_github_repo" value="' . esc_attr($repo) . '" />';
                echo '<input type="hidden" name="pubpi_github_name" value="' . esc_attr($theme_name) . '" />';
                echo '<input type="hidden" name="pubpi_type" value="theme" />';
                submit_button('Mettre à jour', 'primary small', 'pubpi_update_github', false);
                echo '</form></div>';
            }
        }

        if (!$is_installed) {
            echo '<form method="post" style="display:inline;">';
            echo '<input type="hidden" name="pubpi_github_repo" value="' . esc_attr($repo) . '" />';
            echo '<input type="hidden" name="pubpi_github_name" value="' . esc_attr($theme_name) . '" />';
            echo '<input type="hidden" name="pubpi_type" value="theme" />';
            submit_button('Installer', 'primary small', 'pubpi_install_github', false);
            echo '</form>';
        }

        echo '</td></tr>';
    }

    echo '</tbody></table>';
    echo '</div>';

    foreach ($manifest_tabs as $manifest_key => $manifest_tab) {
        $manifest_tab_id = 'pubpi-tab-manifest-' . sanitize_title($manifest_key);
        $table_id = 'pubpi-manifest-patterns-' . sanitize_title($manifest_key);
        $definitions = $manifest_tab['definitions'];
        $categories = $manifest_tab['categories'];

        echo '<div id="' . esc_attr($manifest_tab_id) . '" class="pubpi-tab-panel">';
        foreach ($definitions as $manifest_entry) {
            if (!empty($manifest_entry['error'])) {
                echo '<div class="notice notice-error"><p>❌ ' . esc_html($manifest_entry['name']) . ' : ' . esc_html($manifest_entry['error']) . '</p></div>';
            }
        }
        if (!empty($categories)) {
            echo '<div class="pubpi-category-filters" data-target="#' . esc_attr($table_id) . '">';
            echo '<button type="button" class="button pubpi-filter-btn active" data-category="__all">Tous</button>';
            foreach ($categories as $category) {
                echo '<button type="button" class="button pubpi-filter-btn" data-category="' . esc_attr(pubpi_category_slug($category)) . '">' . esc_html($category) . '</button>';
            }
            echo '</div>';
        }
        echo '<table id="' . esc_attr($table_id) . '" class="widefat fixed striped"><thead><tr><th>Preview</th><th>Nom</th><th>Description</th><th>Catégories</th><th>Repository</th><th>Actions</th></tr></thead><tbody>';

        $has_manifest_pattern = false;
        foreach ($definitions as $manifest_entry) {
            $patterns = $manifest_entry['patterns'];
            if (empty($patterns)) {
                continue;
            }
            $has_manifest_pattern = true;
            $repo = $manifest_entry['repo'];
            $branch = $manifest_entry['branch'];
            $manifest_path = $manifest_entry['manifest_path'];
            $source_name = $manifest_entry['name'];

            foreach ($patterns as $pattern) {
                $pattern_slug = $pattern['slug'] ?? '';
                if (empty($pattern_slug)) {
                    continue;
                }
                $pattern_name = $pattern['name'] ?? $pattern_slug;
                $pattern_description = $pattern['description'] ?? '';
                $pattern_categories = pubpi_manifest_pattern_categories($pattern);
                $pattern_category_slugs = array_map('pubpi_category_slug', $pattern_categories);
                $row_categories_attr = empty($pattern_category_slugs) ? '' : implode(' ', $pattern_category_slugs);
                $preview_url = !empty($pattern['preview']) ? pubpi_build_manifest_asset_url($repo, $branch, $pattern['preview']) : '';

                echo '<tr data-categories="' . esc_attr($row_categories_attr) . '">';
                if (!empty($preview_url)) {
                    echo '<td><img src="' . esc_url($preview_url) . '" alt="' . esc_attr($pattern_name) . '" class="pubpi-manifest-preview" /></td>';
                } else {
                    echo '<td>&mdash;</td>';
                }
                $installation_status = pubpi_manifest_install_status($pattern, 'theme');

                echo '<td><strong>' . esc_html($pattern_name) . '</strong><br /><span style="color:#666;">' . esc_html($source_name) . '</span>';
                if (!empty($installation_status['installed'])) {
                    echo '<br /><span class="pubpi-status-active" style="color:green;">✅ Déjà présent&nbsp;: ' . esc_html(implode(', ', $installation_status['installed'])) . '</span>';
                }
                echo '</td>';
                echo '<td>' . (!empty($pattern_description) ? esc_html($pattern_description) : '&mdash;') . '</td>';
                echo '<td>' . (!empty($pattern_categories) ? esc_html(implode(', ', $pattern_categories)) : '&mdash;') . '</td>';
                echo '<td><code>' . esc_html($repo) . '</code></td>';
                echo '<td>';
                echo '<form method="post" class="pubpi-manifest-install-form" style="display:inline;">';
                echo '<input type="hidden" name="pubpi_manifest_repo" value="' . esc_attr($repo) . '" />';
                echo '<input type="hidden" name="pubpi_manifest_name" value="' . esc_attr($source_name) . '" />';
                echo '<input type="hidden" name="pubpi_manifest_branch" value="' . esc_attr($branch) . '" />';
                echo '<input type="hidden" name="pubpi_manifest_path" value="' . esc_attr($manifest_path) . '" />';
                echo '<input type="hidden" name="pubpi_manifest_pattern" value="' . esc_attr($pattern_slug) . '" />';
                echo '<input type="hidden" name="pubpi_manifest_table" value="' . esc_attr($manifest_key) . '" />';
                echo '<details class="pubpi-manifest-override" style="display:inline-block;margin-left:6px;">';
                echo '<summary>Options avancées</summary>';
                echo '<div class="pubpi-manifest-override-content">';
                echo '<p class="description">Par défaut, le pattern est installé aux emplacements prévus par le manifest. Utilisez les options ci-dessous pour forcer une destination globale.</p>';
                echo '<label for="pubpi_manifest_target_' . esc_attr($pattern_slug) . '">Destination globale</label>';
                echo '<select id="pubpi_manifest_target_' . esc_attr($pattern_slug) . '" name="pubpi_manifest_target" class="pubpi-manifest-target-select">';
                echo '<option value="">Défaut (manifest)</option>';
                echo '<option value="theme">Thème actif</option>';
                echo '<option value="mu-plugins">MU-Plugins</option>';
                echo '<option value="plugins">Plugins</option>';
                echo '</select>';
                echo '<label for="pubpi_manifest_custom_path_' . esc_attr($pattern_slug) . '">Chemin personnalisé</label>';
                echo '<input type="text" id="pubpi_manifest_custom_path_' . esc_attr($pattern_slug) . '" name="pubpi_manifest_custom_path" class="pubpi-manifest-custom-path" placeholder="/ressources/mon-plugin" />';
                echo '</div>';
                echo '</details>';
                submit_button('Installer', 'secondary small', 'pubpi_install_manifest_pattern', false);
                echo '</form>';
                echo '</td></tr>';
            }
        }

        if (!$has_manifest_pattern) {
            echo '<tr><td colspan="6">Aucun pattern disponible pour le moment.</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    // Tab: Features
    echo '<div id="pubpi-tab-features" class="pubpi-tab-panel">';
    if (!empty($github_feature_categories)) {
        echo '<div class="pubpi-category-filters" data-target="#pubpi-github-features">';
        echo '<button type="button" class="button pubpi-filter-btn active" data-category="__all">Tous</button>';
        foreach ($github_feature_categories as $category) {
            echo '<button type="button" class="button pubpi-filter-btn" data-category="' . esc_attr(pubpi_category_slug($category)) . '">' . esc_html($category) . '</button>';
        }
        echo '</div>';
    }
    echo '<table id="pubpi-github-features" class="widefat fixed striped"><thead><tr><th>Nom</th><th>Description</th><th>Catégories</th><th>Repository</th><th>Actions</th></tr></thead><tbody>';
    foreach ($github_features as $repo => $feature_data) {
        $feature_name = $feature_data['name'];
        $feature_description = $feature_data['description'] ?? '';
        $feature_file = $feature_data['file'] ?? '';
        $feature_categories = pubpi_get_item_categories($feature_data);
        $feature_branch = $feature_data['branch'] ?? 'main';
        $slug = basename($repo);
        $category_slugs = array_map('pubpi_category_slug', $feature_categories);
        $row_categories_attr = empty($category_slugs) ? '' : implode(' ', $category_slugs);

        echo '<tr data-categories="' . esc_attr($row_categories_attr) . '">';
        echo '<td><strong>' . esc_html($feature_name) . '</strong></td>';
        echo '<td>' . (!empty($feature_description) ? esc_html($feature_description) : '&mdash;') . '</td>';
        echo '<td>' . (!empty($feature_categories) ? esc_html(implode(', ', $feature_categories)) : '&mdash;') . '</td>';
        echo '<td><code>' . esc_html($repo) . '</code></td>';
        echo '<td>';
        echo '<form method="post" style="display:inline;">';
        echo '<input type="hidden" name="pubpi_feature_repo" value="' . esc_attr($repo) . '" />';
        echo '<input type="hidden" name="pubpi_feature_name" value="' . esc_attr($feature_name) . '" />';
        echo '<input type="hidden" name="pubpi_feature_file" value="' . esc_attr($feature_file) . '" />';
        echo '<input type="hidden" name="pubpi_feature_branch" value="' . esc_attr($feature_branch) . '" />';
        echo '<input type="hidden" name="pubpi_feature_target" value="theme" />';
        submit_button('Installer dans functions.php', 'secondary small', 'pubpi_install_feature', false);
        echo '</form>';
        echo '<form method="post" style="display:inline; margin-left:8px;">';
        echo '<input type="hidden" name="pubpi_feature_repo" value="' . esc_attr($repo) . '" />';
        echo '<input type="hidden" name="pubpi_feature_name" value="' . esc_attr($feature_name) . '" />';
        echo '<input type="hidden" name="pubpi_feature_file" value="' . esc_attr($feature_file) . '" />';
        echo '<input type="hidden" name="pubpi_feature_branch" value="' . esc_attr($feature_branch) . '" />';
        echo '<input type="hidden" name="pubpi_feature_target" value="mu" />';
        submit_button('Installer dans MU-Plugins', 'secondary small', 'pubpi_install_feature', false);
        echo '</form>';
        echo '</td></tr>';
    }
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="pubpi-category-style-script"></div>';
    echo '<style>
.pubpi-tabs-nav { margin-bottom: 12px; }
.pubpi-tab-panel { display: none; }
.pubpi-tab-panel.is-active { display: block; }
.pubpi-category-filters { margin: 12px 0 8px; }
.pubpi-category-filters .button { margin-right: 6px; margin-bottom: 6px; }
.pubpi-category-filters .button.active { background-color: #2271b1; border-color: #2271b1; color: #ffffff; }
</style>';
    echo '<script type="text/javascript">
(function() {
    document.addEventListener("DOMContentLoaded", function() {
        var tabLinks = document.querySelectorAll(".pubpi-tabs-nav .nav-tab");
        tabLinks.forEach(function(link) {
            link.addEventListener("click", function(event) {
                event.preventDefault();
                var target = link.getAttribute("href");
                if (!target) {
                    return;
                }
                tabLinks.forEach(function(l) { l.classList.remove("nav-tab-active"); });
                document.querySelectorAll(".pubpi-tab-panel").forEach(function(panel) {
                    panel.classList.remove("is-active");
                });
                link.classList.add("nav-tab-active");
                var panel = document.querySelector(target);
                if (panel) {
                    panel.classList.add("is-active");
                }
            });
        });

        document.querySelectorAll(".pubpi-category-filters").forEach(function(container) {
            container.addEventListener("click", function(event) {
                var button = event.target.closest(".pubpi-filter-btn");
                if (!button) {
                    return;
                }
                event.preventDefault();
                container.querySelectorAll(".pubpi-filter-btn").forEach(function(btn) {
                    btn.classList.remove("active");
                });
                button.classList.add("active");
                var category = button.getAttribute("data-category");
                var tableSelector = container.getAttribute("data-target");
                var table = document.querySelector(tableSelector);
                if (!table) {
                    return;
                }
                table.querySelectorAll("tbody tr").forEach(function(row) {
                    var rowCats = (row.getAttribute("data-categories") || "").split(" ").filter(Boolean);
                    row.style.display = (category === "__all" || rowCats.includes(category)) ? "" : "none";
                });
            });
        });
    });
})();
</script>';
    echo '</div>';

    // Traitement des actions
    if (isset($_POST['pubpi_install'])) {
        $plugin_path = sanitize_text_field($_POST['pubpi_plugin_path']);
        $plugin_name = sanitize_text_field($_POST['pubpi_plugin_name']);
        pubpi_install_and_activate_plugin($plugin_path, $plugin_name);
    }

    if (isset($_POST['pubpi_install_github'])) {
        $repo = sanitize_text_field($_POST['pubpi_github_repo']);
        $name = sanitize_text_field($_POST['pubpi_github_name']);
        $type = sanitize_text_field($_POST['pubpi_type']);
        $main_file = isset($_POST['pubpi_github_main_file']) ? sanitize_text_field($_POST['pubpi_github_main_file']) : '';
        pubpi_install_from_github($repo, $name, $type, $main_file, false); // false = is not an update
    }

    // AJOUTÉ : Handler pour la mise à jour
    if (isset($_POST['pubpi_update_github'])) {
        $repo = sanitize_text_field($_POST['pubpi_github_repo']);
        $name = sanitize_text_field($_POST['pubpi_github_name']);
        $type = sanitize_text_field($_POST['pubpi_type']);
        pubpi_install_from_github($repo, $name, $type, '', true); // true = is an update
    }

    if (isset($_POST['pubpi_activate'])) {
        $plugin_path = sanitize_text_field($_POST['pubpi_activate_plugin']);
        pubpi_activate_existing_plugin($plugin_path);
    }

    if (isset($_POST['pubpi_activate_theme_btn'])) {
        $theme_slug = sanitize_text_field($_POST['pubpi_activate_theme']);
        pubpi_activate_theme($theme_slug);
    }

    if (isset($_POST['pubpi_install_feature'])) {
        $feature_repo = sanitize_text_field($_POST['pubpi_feature_repo']);
        $feature_name = sanitize_text_field($_POST['pubpi_feature_name']);
        $feature_file = sanitize_text_field($_POST['pubpi_feature_file']);
        $feature_branch = isset($_POST['pubpi_feature_branch']) ? sanitize_text_field($_POST['pubpi_feature_branch']) : 'main';
        $feature_target = sanitize_text_field($_POST['pubpi_feature_target']);
        pubpi_install_feature_from_github($feature_repo, $feature_name, $feature_file, $feature_target, $feature_branch);
    }

    if (isset($_POST['pubpi_install_manifest_pattern'])) {
        $manifest_repo = sanitize_text_field($_POST['pubpi_manifest_repo']);
        $manifest_name = sanitize_text_field($_POST['pubpi_manifest_name']);
        $manifest_branch = isset($_POST['pubpi_manifest_branch']) ? sanitize_text_field($_POST['pubpi_manifest_branch']) : 'main';
        $manifest_path = sanitize_text_field($_POST['pubpi_manifest_path']);
        $pattern_slug = sanitize_text_field($_POST['pubpi_manifest_pattern']);
        $target_location = isset($_POST['pubpi_manifest_target']) ? sanitize_text_field($_POST['pubpi_manifest_target']) : 'theme';
        $custom_path = isset($_POST['pubpi_manifest_custom_path']) ? sanitize_text_field($_POST['pubpi_manifest_custom_path']) : '';
        pubpi_install_manifest_pattern($manifest_repo, $manifest_name, $manifest_branch, $manifest_path, $pattern_slug, $target_location, $custom_path);
    }
}

function pubpi_get_item_categories($item) {
    if (!is_array($item) || !isset($item['categories'])) {
        return [];
    }
    $categories = $item['categories'];
    if (is_string($categories)) {
        $categories = array_map('trim', explode(',', $categories));
    }
    if (!is_array($categories)) {
        return [];
    }
    $normalized = [];
    foreach ($categories as $category) {
        $category = trim((string) $category);
        if ($category !== '') {
            $normalized[] = $category;
        }
    }
    return $normalized;
}

function pubpi_manifest_install_status($pattern, $target_location = 'theme') {
    $status = [
        'installed' => [],
        'missing' => [],
    ];

    if (empty($pattern['files']) || !is_array($pattern['files'])) {
        return $status;
    }

    $base_dir = pubpi_resolve_install_base_dir($target_location, false);
    if (is_wp_error($base_dir)) {
        return $status;
    }

    $installs = isset($pattern['install']) && is_array($pattern['install']) ? $pattern['install'] : [];

    foreach ($pattern['files'] as $key => $source_rel_path) {
        if (!is_string($source_rel_path) || $source_rel_path === '') {
            continue;
        }

        $target_dir_rel = '';
        if (isset($installs[$key]) && $installs[$key] !== '') {
            $target_dir_rel = trim($installs[$key], '/');
        }

        $target_dir = trailingslashit($base_dir);
        if ($target_dir_rel !== '') {
            $target_dir .= trailingslashit($target_dir_rel);
        }

        $candidate_path = $target_dir . basename($source_rel_path);
        $display_path = ($target_dir_rel !== '' ? trailingslashit($target_dir_rel) : '') . basename($source_rel_path);

        if (file_exists($candidate_path) || is_dir($candidate_path)) {
            $status['installed'][] = $display_path;
        } else {
            $status['missing'][] = $display_path;
        }
    }

    return $status;
}

function pubpi_extract_categories($items) {
    $found = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $categories = pubpi_get_item_categories($item);
        foreach ($categories as $category) {
            $found[$category] = true;
        }
    }
    $categories = array_keys($found);
    natcasesort($categories);
    return array_values($categories);
}

function pubpi_category_slug($category) {
    return sanitize_title($category);
}

function pubpi_manifest_debug($repo, $message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[pubpi manifest][' . $repo . '] ' . $message);
    }
}

function pubpi_fetch_manifest_definitions($manifests) {
    $definitions = [];

    foreach ($manifests as $repo => $data) {
        $name = $data['name'] ?? $repo;
        $branch = $data['branch'] ?? 'main';
        $manifest_path = ltrim($data['manifest'] ?? 'manifest.json', '/');

        $entry = [
            'repo' => $repo,
            'name' => $name,
            'branch' => $branch,
            'manifest_path' => $manifest_path,
            'patterns' => [],
            'error' => '',
        ];

        $manifest_url = 'https://raw.githubusercontent.com/' . $repo . '/' . $branch . '/' . $manifest_path;
        pubpi_manifest_debug($repo, 'GET ' . $manifest_url);
        $response = wp_remote_get($manifest_url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'WordPress-Plugin-Installer'
            ],
        ]);

        if (is_wp_error($response)) {
            $entry['error'] = $response->get_error_message();
            pubpi_manifest_debug($repo, 'Request error: ' . $entry['error']);
        } else {
            $status = (int) wp_remote_retrieve_response_code($response);
            if ($status !== 200) {
                $entry['error'] = sprintf('Manifest introuvable (HTTP %d).', $status);
                pubpi_manifest_debug($repo, 'HTTP status ' . $status);
            } else {
                $body = wp_remote_retrieve_body($response);
                $decoded = json_decode($body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $entry['error'] = 'Manifest JSON invalide.';
                    pubpi_manifest_debug($repo, 'JSON decode error: ' . json_last_error_msg());
                } elseif (!empty($decoded['patterns']) && is_array($decoded['patterns'])) {
                    $entry['patterns'] = $decoded['patterns'];
                    pubpi_manifest_debug($repo, 'Patterns loaded: ' . count($decoded['patterns']));
                } else {
                    pubpi_manifest_debug($repo, 'No patterns key or empty array');
                }
            }
        }

        $definitions[] = $entry;
    }

    return $definitions;
}

function pubpi_collect_manifest_categories($definitions) {
    $found = [];
    foreach ($definitions as $entry) {
        if (empty($entry['patterns']) || !is_array($entry['patterns'])) {
            continue;
        }
        foreach ($entry['patterns'] as $pattern) {
            $categories = pubpi_manifest_pattern_categories($pattern);
            foreach ($categories as $category) {
                $found[$category] = true;
            }
        }
    }

    $categories = array_keys($found);
    natcasesort($categories);
    return array_values($categories);
}

function pubpi_manifest_pattern_categories($pattern) {
    $categories = [];

    if (isset($pattern['categories'])) {
        $categories = $pattern['categories'];
    } elseif (isset($pattern['category'])) {
        $categories = $pattern['category'];
    }

    if (is_string($categories)) {
        $categories = array_map('trim', explode(',', $categories));
    }

    if (!is_array($categories)) {
        return [];
    }

    $normalized = [];
    foreach ($categories as $category) {
        $category = trim((string) $category);
        if ($category !== '') {
            $normalized[] = $category;
        }
    }

    return $normalized;
}

function pubpi_build_manifest_asset_url($repo, $branch, $path) {
    if (empty($path)) {
        return '';
    }
    $path = ltrim($path, '/');
    return sprintf('https://raw.githubusercontent.com/%s/%s/%s', $repo, $branch, $path);
}

function pubpi_prepare_manifest_tabs($tabs_config) {
    $prepared = [];

    foreach ($tabs_config as $key => $tab) {
        if (!is_array($tab)) {
            continue;
        }

        $label = isset($tab['label']) ? (string) $tab['label'] : (string) $key;
        $manifests = $tab['manifests'] ?? [];
        if (empty($manifests) || !is_array($manifests)) {
            $prepared[$key] = [
                'label' => $label,
                'definitions' => [],
                'categories' => [],
            ];
            continue;
        }

        $definitions = pubpi_fetch_manifest_definitions($manifests);
        $categories = pubpi_collect_manifest_categories($definitions);

        $prepared[$key] = [
            'label' => $label,
            'definitions' => $definitions,
            'categories' => $categories,
        ];
    }

    return $prepared;
}

function pubpi_install_manifest_pattern($repo, $name, $branch, $manifest_path, $pattern_slug, $target_location = 'theme', $custom_path = '') {
    include_once ABSPATH . 'wp-admin/includes/file.php';
    include_once ABSPATH . 'wp-admin/includes/misc.php';
    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $download = pubpi_download_repo_archive($repo, $branch);
    if (is_wp_error($download)) {
        echo '<div class="error notice"><p>❌ ' . esc_html($download->get_error_message()) . '</p></div>';
        return;
    }

    $temp_dir = $download['temp_dir'];
    $repo_root = $download['root_dir'];
    $manifest_file = trailingslashit($repo_root) . ltrim($manifest_path, '/');

    if (!file_exists($manifest_file)) {
        pubpi_delete_directory($temp_dir);
        echo '<div class="error notice"><p>❌ Manifest introuvable dans le dépôt ' . esc_html($repo) . '.</p></div>';
        return;
    }

    $manifest_content = file_get_contents($manifest_file);
    if ($manifest_content === false) {
        pubpi_delete_directory($temp_dir);
        echo '<div class="error notice"><p>❌ Impossible de lire le manifest pour ' . esc_html($name) . '.</p></div>';
        return;
    }

    $manifest_data = json_decode($manifest_content, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($manifest_data['patterns']) || !is_array($manifest_data['patterns'])) {
        pubpi_delete_directory($temp_dir);
        echo '<div class="error notice"><p>❌ Manifest JSON invalide pour ' . esc_html($name) . '.</p></div>';
        return;
    }

    $pattern = null;
    foreach ($manifest_data['patterns'] as $entry) {
        if (!empty($entry['slug']) && $entry['slug'] === $pattern_slug) {
            $pattern = $entry;
            break;
        }
    }

    if (!$pattern) {
        pubpi_delete_directory($temp_dir);
        echo '<div class="error notice"><p>❌ Le pattern "' . esc_html($pattern_slug) . '" est introuvable dans le manifest.</p></div>';
        return;
    }

    $files = $pattern['files'] ?? [];
    $installs = $pattern['install'] ?? [];

    if (empty($files) || !is_array($files)) {
        pubpi_delete_directory($temp_dir);
        echo '<div class="error notice"><p>❌ Aucun fichier à installer pour "' . esc_html($pattern_slug) . '".</p></div>';
        return;
    }

    if ($target_location === '') {
        $target_location = 'theme';
    }

    $base_dir = pubpi_resolve_install_base_dir($target_location);
    if (is_wp_error($base_dir)) {
        pubpi_delete_directory($temp_dir);
        echo '<div class="error notice"><p>❌ ' . esc_html($base_dir->get_error_message()) . '</p></div>';
        return;
    }

    $custom_path = trim($custom_path);
    if ($custom_path !== '') {
        $custom_path = ltrim($custom_path, '/');
    }

    $installed_items = [];

    foreach ($files as $key => $source_rel_path) {
        if (!is_string($source_rel_path) || $source_rel_path === '') {
            continue;
        }
        $source_path = trailingslashit($repo_root) . ltrim($source_rel_path, '/');

        if (!file_exists($source_path)) {
            pubpi_delete_directory($temp_dir);
            echo '<div class="error notice"><p>❌ Le fichier ' . esc_html($source_rel_path) . ' est introuvable dans le dépôt ' . esc_html($repo) . '.</p></div>';
            return;
        }

        $target_dir_rel = '';
        if ($custom_path !== '') {
            $target_dir_rel = $custom_path;
        } elseif (isset($installs[$key]) && $installs[$key] !== '') {
            $target_dir_rel = trim($installs[$key], '/');
        }

        $target_dir = trailingslashit($base_dir);
        if ($target_dir_rel !== '') {
            $target_dir .= trailingslashit($target_dir_rel);
        }

        if (!wp_mkdir_p($target_dir)) {
            pubpi_delete_directory($temp_dir);
            echo '<div class="error notice"><p>❌ Impossible de créer le dossier cible ' . esc_html($target_dir_rel) . '.</p></div>';
            return;
        }

        if (is_dir($source_path)) {
            $destination_dir = trailingslashit($target_dir) . basename($source_path);
            if (!pubpi_copy_directory_with_fallback($source_path, $destination_dir)) {
                pubpi_delete_directory($temp_dir);
                echo '<div class="error notice"><p>❌ Erreur lors de la copie du dossier ' . esc_html($source_rel_path) . '.</p></div>';
                return;
            }
            $installed_items[] = ($target_dir_rel !== '' ? trailingslashit($target_dir_rel) : '') . basename($source_path) . '/';
        } else {
            $destination_path = trailingslashit($target_dir) . basename($source_path);
            if (!pubpi_copy_file_with_fallback($source_path, $destination_path, true)) {
                pubpi_delete_directory($temp_dir);
                echo '<div class="error notice"><p>❌ Erreur lors de la copie de ' . esc_html($source_rel_path) . '.</p></div>';
                return;
            }
            $installed_items[] = ($target_dir_rel !== '' ? trailingslashit($target_dir_rel) : '') . basename($source_path);
        }
    }

    pubpi_delete_directory($temp_dir);

    if (!empty($installed_items)) {
        $items_list = '<ul style="margin-top:8px;">';
        foreach ($installed_items as $item) {
            $items_list .= '<li>' . esc_html($item) . '</li>';
        }
        $items_list .= '</ul>';
    } else {
        $items_list = '';
    }

    echo '<div class="updated notice"><p>✅ Le pattern "' . esc_html($pattern_slug) . '" de ' . esc_html($name) . ' a été installé dans ' . esc_html(pubpi_install_target_label($target_location, $custom_path)) . '.' . $items_list . '</p></div>';
}

function pubpi_resolve_install_base_dir($target_location, $create = true) {
    switch ($target_location) {
        case 'mu-plugins':
            $dir = WP_CONTENT_DIR . '/mu-plugins';
            break;
        case 'plugins':
            $dir = WP_PLUGIN_DIR;
            break;
        case 'theme':
        default:
            $dir = get_stylesheet_directory();
            if (empty($dir)) {
                return new WP_Error('pubpi_missing_theme', 'Impossible de déterminer le thème actif.');
            }
            break;
    }

    if ($create) {
        if (!wp_mkdir_p($dir)) {
            return new WP_Error('pubpi_unwritable_dir', 'Impossible de créer le dossier cible : ' . $dir);
        }
    } elseif (!file_exists($dir)) {
        return new WP_Error('pubpi_missing_dir', 'Le dossier cible est introuvable : ' . $dir);
    }

    return $dir;
}

function pubpi_install_target_label($target_location, $custom_path) {
    $base_label = '';
    switch ($target_location) {
        case 'mu-plugins':
            $base_label = 'MU-Plugins';
            break;
        case 'plugins':
            $base_label = 'Plugins';
            break;
        default:
            $base_label = 'le thème actif';
            break;
    }

    if (!empty($custom_path)) {
        return $base_label . ' dans ' . $custom_path;
    }

    return $base_label;
}

function pubpi_install_and_activate_plugin($plugin_path, $plugin_name) {
    include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    include_once ABSPATH . 'wp-admin/includes/file.php';
    include_once ABSPATH . 'wp-admin/includes/misc.php';
    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $slug = dirname($plugin_path);
    $api = plugins_api('plugin_information', ['slug' => $slug]);

    if (is_wp_error($api)) {
        echo '<div class="error notice"><p>❌ Erreur lors de la récupération des informations du plugin : ' . esc_html($api->get_error_message()) . '</p></div>';
        return;
    }

    if (empty($api->download_link)) {
        echo '<div class="error notice"><p>❌ L\'URL de téléchargement est introuvable pour ' . esc_html($plugin_name) . '.</p></div>';
        return;
    }

    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    $result = $upgrader->install($api->download_link);

    if (is_wp_error($result)) {
        echo '<div class="error notice"><p>❌ Erreur d\'installation : ' . esc_html($result->get_error_message()) . '</p></div>';
        return;
    }

    $activate = activate_plugin($plugin_path);

    if (is_wp_error($activate)) {
        echo '<div class="error notice"><p>❌ Erreur lors de l\'activation : ' . esc_html($activate->get_error_message()) . '</p></div>';
        return;
    }

    echo '<div class="updated notice"><p>✅ ' . esc_html($plugin_name) . ' a été installé et activé avec succès.</p></div>';
}

// MODIFIÉ : Ajout du paramètre $is_update
function pubpi_install_from_github($repo, $name, $type = 'plugin', $main_file = '', $is_update = false) {
    include_once ABSPATH . 'wp-admin/includes/file.php';
    include_once ABSPATH . 'wp-admin/includes/misc.php';
    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $slug = basename($repo);
    $destination = ($type === 'theme') ? WP_CONTENT_DIR . '/themes/' : WP_PLUGIN_DIR . '/';
    $destination_path = $destination . $slug;

    // AJOUTÉ : Supprimer l'ancien dossier si c'est une mise à jour
    if ($is_update) {
        if (file_exists($destination_path)) {
            pubpi_delete_directory($destination_path);
        }
    }

    $release_info = pubpi_get_latest_release_info($repo);
    $zip_url = false;
    if ($release_info) {
        $zip_url = $release_info['zip_url'];
    }

    // Si pas de release, on tente avec la branche par défaut
    if (!$zip_url) {
        $zip_url = 'https://github.com/' . $repo . '/archive/refs/heads/main.zip';
        $tmp_file = download_url($zip_url);
        if (is_wp_error($tmp_file)) {
            $zip_url = 'https://github.com/' . $repo . '/archive/refs/heads/master.zip';
        }
    }

    $tmp_file = download_url($zip_url);

    if (is_wp_error($tmp_file)) {
        echo '<div class="error notice"><p>❌ Erreur lors du téléchargement depuis GitHub : ' . esc_html($tmp_file->get_error_message()) . '</p></div>';
        return;
    }

    WP_Filesystem();
    $unzip_result = unzip_file($tmp_file, $destination);
    @unlink($tmp_file);

    if (is_wp_error($unzip_result)) {
        echo '<div class="error notice"><p>❌ Erreur lors de l\'extraction : ' . esc_html($unzip_result->get_error_message()) . '</p></div>';
        return;
    }

    // MODIFIÉ : Logique de renommage améliorée avec la fonction find_extracted_folder
    $extracted_folder = pubpi_find_extracted_folder($destination, $slug);
    if ($extracted_folder && $extracted_folder !== $destination_path) {
        rename($extracted_folder, $destination_path);
    }

    // Ajouter un délai pour s'assurer que tous les fichiers sont bien disponibles avant activation
    sleep(1);

    // MODIFIÉ : Messages de succès personnalisés
    $action_text = $is_update ? 'mis à jour' : 'installé';

    if ($type === 'plugin') {
        if (empty($main_file)) {
            $main_file = pubpi_find_plugin_main_file($slug);
        }
        $plugin_path = $slug . '/' . $main_file;
        $activate = activate_plugin($plugin_path);

        if (is_wp_error($activate)) {
            echo '<div class="error notice"><p>⚠️ ' . esc_html($name) . ' ' . $action_text . ', mais erreur d\'activation : ' . esc_html($activate->get_error_message()) . '</p></div>';
            return;
        }

        echo '<div class="updated notice"><p>✅ ' . esc_html($name) . ' a été ' . $action_text . ' et activé depuis GitHub avec succès.</p></div>';
    } else {
        echo '<div class="updated notice"><p>✅ ' . esc_html($name) . ' a été ' . $action_text . ' depuis GitHub avec succès. Vous pouvez maintenant l\'activer.</p></div>';
    }
}

function pubpi_activate_existing_plugin($plugin_path) {
    $activate = activate_plugin($plugin_path);
    if (is_wp_error($activate)) {
        echo '<div class="error notice"><p>❌ Erreur lors de l\'activation : ' . esc_html($activate->get_error_message()) . '</p></div>';
        return;
    }
    echo '<div class="updated notice"><p>✅ Plugin activé avec succès.</p></div>';
}

function pubpi_activate_theme($theme_slug) {
    switch_theme($theme_slug);
    echo '<div class="updated notice"><p>✅ Thème activé avec succès.</p></div>';
}

function pubpi_find_plugin_main_file($plugin_slug) {
    $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
    if (!is_dir($plugin_dir)) return '';
    $php_files = glob($plugin_dir . '/*.php');
    foreach ($php_files as $file) {
        $file_data = get_file_data($file, ['Plugin Name' => 'Plugin Name']);
        if (!empty($file_data['Plugin Name'])) {
            return basename($file);
        }
    }
    return '';
}

// MODIFIÉ : La fonction retourne maintenant un tableau avec la version et l'URL
/**
 * Récupère les informations de la dernière release GitHub
 * @param string $repo Format: user/repo
 * @return array|false Un tableau ['version' => ..., 'zip_url' => ...] ou false
 */
function pubpi_get_latest_release_info($repo) {
    $api_url = 'https://api.github.com/repos/' . $repo . '/releases/latest';
    $response = wp_remote_get($api_url, [
        'timeout' => 15,
        'headers' => ['User-Agent' => 'WordPress-Plugin-Installer']
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data['zipball_url']) || empty($data['tag_name'])) {
        return false;
    }

    // Nettoyer le nom de la version (ex: 'v1.2.3' devient '1.2.3')
    $version = ltrim($data['tag_name'], 'v');

    return ['version' => $version, 'zip_url' => $data['zipball_url']];
}

// AJOUTÉ : Nouvelle fonction pour récupérer la version locale d'un plugin ou d'un thème
/**
 * Récupère la version locale d'un plugin ou d'un thème
 * @param string $slug Le slug
 * @param string $type 'plugin' ou 'theme'
 * @return string La version, ou '0' si non trouvée
 */
function pubpi_get_local_version($slug, $type) {
    if ($type === 'plugin') {
        $plugin_file = pubpi_find_plugin_main_file($slug);
        if (!$plugin_file) return '0';
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $slug . '/' . $plugin_file);
        return $plugin_data['Version'] ?? '0';
    } elseif ($type === 'theme') {
        $theme = wp_get_theme($slug);
        if ($theme->exists()) {
            return $theme->get('Version') ?? '0';
        }
    }
    return '0';
}

function pubpi_find_extracted_folder($destination, $slug) {
    $dirs = glob($destination . '*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $dir_name = basename($dir);
        if (strpos($dir_name, $slug) === 0 && (time() - filemtime($dir) < 60)) {
            return $dir;
        }
    }
    return false;
}

function pubpi_delete_directory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!pubpi_delete_directory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}

function pubpi_download_repo_archive($repo, $branch = 'main') {
    include_once ABSPATH . 'wp-admin/includes/file.php';
    include_once ABSPATH . 'wp-admin/includes/misc.php';
    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $slug = basename($repo);
    $temp_root = trailingslashit(WP_CONTENT_DIR) . 'uploads/pubpi-temp';
    if (!wp_mkdir_p($temp_root)) {
        return new WP_Error('pubpi_temp_dir', 'Impossible de créer le dossier temporaire.');
    }

    $temp_dir = trailingslashit($temp_root) . $slug . '-' . uniqid('', true);
    if (!wp_mkdir_p($temp_dir)) {
        return new WP_Error('pubpi_temp_prepare', 'Impossible de préparer le dossier temporaire.');
    }

    $release_info = pubpi_get_latest_release_info($repo);
    $zip_url = $release_info ? $release_info['zip_url'] : '';
    $tmp_file = null;

    if ($zip_url) {
        $tmp_file = download_url($zip_url);
        if (is_wp_error($tmp_file)) {
            $tmp_file = null;
            $zip_url = '';
        }
    }

    if (!$tmp_file) {
        $branches_to_try = array_unique([$branch, 'main', 'master']);
        foreach ($branches_to_try as $candidate_branch) {
            $candidate_url = 'https://github.com/' . $repo . '/archive/refs/heads/' . $candidate_branch . '.zip';
            $candidate_file = download_url($candidate_url);
            if (!is_wp_error($candidate_file)) {
                $tmp_file = $candidate_file;
                $zip_url = $candidate_url;
                break;
            }
        }
    }

    if (!$tmp_file || is_wp_error($tmp_file)) {
        pubpi_delete_directory($temp_dir);
        return new WP_Error('pubpi_download', 'Impossible de télécharger l\'archive GitHub.');
    }

    WP_Filesystem();
    $unzip_result = unzip_file($tmp_file, $temp_dir);
    @unlink($tmp_file);

    if (is_wp_error($unzip_result)) {
        pubpi_delete_directory($temp_dir);
        return new WP_Error('pubpi_unzip', $unzip_result->get_error_message());
    }

    $root_dir = pubpi_find_first_directory($temp_dir);
    if (!$root_dir) {
        $root_dir = $temp_dir;
    }

    return [
        'temp_dir' => $temp_dir,
        'root_dir' => $root_dir,
    ];
}

function pubpi_find_first_directory($path) {
    $dirs = glob(trailingslashit($path) . '*', GLOB_ONLYDIR);
    if (empty($dirs)) {
        return false;
    }
    return $dirs[0];
}

function pubpi_copy_file_with_fallback($source, $destination, $overwrite = true) {
    WP_Filesystem();
    global $wp_filesystem;

    if ($wp_filesystem && is_object($wp_filesystem)) {
        $copied = $wp_filesystem->copy($source, $destination, $overwrite);
        if ($copied) {
            return true;
        }
    }

    $dir = dirname($destination);
    if (!wp_mkdir_p($dir)) {
        return false;
    }

    return @copy($source, $destination);
}

function pubpi_copy_directory_with_fallback($source, $destination) {
    $source = untrailingslashit($source);
    $destination = untrailingslashit($destination);

    if (!is_dir($source)) {
        return false;
    }

    if (!wp_mkdir_p($destination)) {
        return false;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative_path = substr($item->getPathname(), strlen($source) + 1);
        $target_path = trailingslashit($destination) . $relative_path;

        if ($item->isDir()) {
            if (!wp_mkdir_p($target_path)) {
                return false;
            }
        } else {
            $dir = dirname($target_path);
            if (!is_dir($dir) && !wp_mkdir_p($dir)) {
                return false;
            }
            if (!@copy($item->getPathname(), $target_path)) {
                return false;
            }
        }
    }

    return true;
}

function pubpi_install_feature_from_github($repo, $name, $file, $target, $branch = 'main') {
    include_once ABSPATH . 'wp-admin/includes/file.php';
    include_once ABSPATH . 'wp-admin/includes/misc.php';
    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    if (empty($file)) {
        echo '<div class="error notice"><p>❌ Aucun fichier à installer n\'a été défini pour ' . esc_html($name) . '.</p></div>';
        return;
    }

    $download = pubpi_download_repo_archive($repo, $branch);
    if (is_wp_error($download)) {
        echo '<div class="error notice"><p>❌ ' . esc_html($download->get_error_message()) . '</p></div>';
        return;
    }

    $temp_dir = $download['temp_dir'];
    $repo_root = $download['root_dir'];

    $feature_source = pubpi_locate_file_in_directory($repo_root, $file);
    if (!$feature_source) {
        echo '<div class="error notice"><p>❌ Le fichier ' . esc_html($file) . ' est introuvable dans le dépôt ' . esc_html($repo) . '.</p></div>';
        pubpi_delete_directory($temp_dir);
        return;
    }

    $base_filename = basename($file);
    $sanitized_filename = sanitize_file_name($base_filename);
    if (empty($sanitized_filename)) {
        echo '<div class="error notice"><p>❌ Nom de fichier invalide pour ' . esc_html($file) . '.</p></div>';
        pubpi_delete_directory($temp_dir);
        return;
    }

    if ($target === 'mu') {
        $destination_dir = trailingslashit(WPMU_PLUGIN_DIR);
        if (!wp_mkdir_p($destination_dir)) {
            echo '<div class="error notice"><p>❌ Impossible d\'accéder au dossier MU-Plugins.</p></div>';
            pubpi_delete_directory($temp_dir);
            return;
        }
        $destination_filename = $slug . '-' . $sanitized_filename;
        $relative_path = 'wp-content/mu-plugins/' . $destination_filename;
    } else {
        $theme_dir = get_stylesheet_directory();
        if (empty($theme_dir)) {
            echo '<div class="error notice"><p>❌ Impossible de déterminer le thème actif.</p></div>';
            pubpi_delete_directory($temp_dir);
            return;
        }
        $functions_dir = trailingslashit($theme_dir) . 'functions';
        if (!wp_mkdir_p($functions_dir)) {
            echo '<div class="error notice"><p>❌ Impossible de créer le dossier functions du thème.</p></div>';
            pubpi_delete_directory($temp_dir);
            return;
        }
        $destination_filename = $sanitized_filename;
        $relative_path = 'wp-content/themes/' . basename($theme_dir) . '/functions/' . $destination_filename;
        $destination_dir = trailingslashit($functions_dir);
    }

    $destination_path = trailingslashit($destination_dir) . $destination_filename;

    if (!pubpi_copy_file_with_fallback($feature_source, $destination_path, true)) {
        echo '<div class="error notice"><p>❌ Impossible de copier le fichier vers ' . esc_html($relative_path) . '.</p></div>';
        pubpi_delete_directory($temp_dir);
        return;
    }

    if ($target === 'theme') {
        $include_added = pubpi_ensure_theme_feature_include('functions/' . $destination_filename);
        if (!$include_added) {
            echo '<div class="error notice"><p>⚠️ ' . esc_html($name) . ' a été copié, mais l\'inclusion automatique dans functions.php a échoué. Vérifiez manuellement.</p></div>';
            pubpi_delete_directory($temp_dir);
            return;
        }
    }

    pubpi_delete_directory($temp_dir);

    if ($target === 'mu') {
        echo '<div class="updated notice"><p>✅ ' . esc_html($name) . ' a été installé dans MU-Plugins.</p></div>';
    } else {
        echo '<div class="updated notice"><p>✅ ' . esc_html($name) . ' a été installé dans le dossier functions du thème et inclus automatiquement.</p></div>';
    }
}

function pubpi_locate_file_in_directory($directory, $target_file) {
    $target_basename = basename($target_file);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file_info) {
        if ($file_info->isFile() && basename($file_info->getFilename()) === $target_basename) {
            return $file_info->getPathname();
        }
    }

    return false;
}

function pubpi_ensure_theme_feature_include($relative_path) {
    $theme_dir = get_stylesheet_directory();
    if (empty($theme_dir)) {
        return false;
    }

    $functions_file = trailingslashit($theme_dir) . 'functions.php';
    if (!file_exists($functions_file)) {
        if (file_put_contents($functions_file, "<?php\n") === false) {
            return false;
        }
    }

    $contents = file_get_contents($functions_file);
    if ($contents === false) {
        return false;
    }

    $normalized = str_replace(["\r\n", "\r"], "\n", $contents);
    if (strpos($normalized, $relative_path) !== false) {
        return true;
    }

    $include_line = "\nrequire_once get_stylesheet_directory() . '/" . $relative_path . "';\n";
    return file_put_contents($functions_file, $contents . $include_line) !== false;
}