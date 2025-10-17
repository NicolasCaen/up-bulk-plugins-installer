<?php
/*
Plugin Name: Up Bulk Plugin Installer
Description: Installe, active et met à jour automatiquement une sélection de plugins et thèmes essentiels depuis WordPress.org ou GitHub.
Version: 1.0
Author: GEHIN Nicolas
*/

if (!defined('ABSPATH')) {
    exit;
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
}

function pubpi_render_admin_page() {
    // Plugins WordPress.org
    $plugins = [
        'contact-form-7/wp-contact-form-7.php' => 'Contact Form 7',
        'wp-umbrella/wp-umbrella.php' => 'WP Umbrella',
        'wordpress-seo/wp-seo.php' => 'Yoast SEO',
        'updraftplus/updraftplus.php' => 'UpdraftPlus',
        'wp-super-cache/wp-cache.php' => 'WP Super Cache',
        'advanced-custom-fields/acf.php' => 'Advanced Custom Fields (free)',
        'admin-menu-editor/admin-menu-editor.php' => 'Admin Menu Editor (free)',
        'post-types-order/post-types-order.php' => 'Post Types Order',
        'safe-svg/safe-svg.php' => 'Safe SVG',
        'wp-media-folder/wp-media-folder.php' => 'WP Media Folder (JoomUnited)',
        'advanced-custom-fields-pro/acf.php' => 'Advanced Custom Fields PRO',
        'admin-menu-editor-pro/admin-menu-editor.php' => 'Admin Menu Editor PRO',
        'gravityforms/gravityforms.php' => 'Gravity Forms',
        'wp-media-folder-pro/wp-media-folder.php' => 'WP Media Folder PRO',
    ];

    // Plugins GitHub (format: 'user/repo' => ['name' => 'Nom', 'main_file' => 'fichier-principal.php'])
    $github_plugins = [
        'NicolasCaen/up-gutenberg-query-filter' => [
            'name' => 'Up Gutenberg Query Filter',
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-binding-lorem' => [
            'name' => 'Up Binding Lorem',
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-variation-generator' => [
            'name' => 'Up Variation Generator',
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-gutenberg-binding-collection' => [
            'name' => 'Up Gutenberg Binding Collection',
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-gutenberg-bindings-interface' => [
            'name' => 'Up Gutenberg Bindings Interface',
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-gutenberg-metabox' => [
            'name' => 'Up Gutenberg Metabox',
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-library-generator' => [
            'name' => 'Up Library Generator',
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-shortcodes-library' => [
            'name' => 'Up Shortcodes Library',
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-section-styles' => [
            'name' => 'Up Section Styles',
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-theme-generator' => [
            'name' => 'Up Theme Generator',
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-gsap-animate' => [
            'name' => 'Up GSAP Animate',
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-gsap-animate-2' => [
            'name' => 'Up GSAP Animate 2',
            'main_file' => '' // Auto-détection
        ],
        'NicolasCaen/up-wp-resize-admin-aside' => [
            'name' => 'Up WP Resize Admin Aside',
            'main_file' => '' // Auto-détection
        ],
    ];

    // Thèmes GitHub (format: 'user/repo' => 'Nom du thème')
    $github_themes = [
        'NicolasCaen/ng1-base' => 'NG1 Base Theme',
    ];

    echo '<div class="wrap"><h1>Installer & Mettre à jour des plugins et thèmes</h1>';

    // Section Plugins WordPress.org
    echo '<h2>Plugins WordPress.org</h2>';
    echo '<table class="widefat fixed striped"><thead><tr><th>Nom du plugin</th><th>Action</th></tr></thead><tbody>';

    foreach ($plugins as $plugin_path => $plugin_name) {
        $is_installed = file_exists(WP_PLUGIN_DIR . '/' . dirname($plugin_path));
        $is_active = is_plugin_active($plugin_path);

        echo '<tr>';
        echo '<td>' . esc_html($plugin_name) . '</td>';
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

    // Section Plugins GitHub
    echo '<h2 style="margin-top:30px;">Plugins GitHub</h2>';
    echo '<table class="widefat fixed striped"><thead><tr><th>Nom du plugin</th><th>Repository</th><th>Action</th></tr></thead><tbody>';

    foreach ($github_plugins as $repo => $plugin_data) {
        $plugin_name = $plugin_data['name'];
        $main_file = $plugin_data['main_file'];
        $slug = basename($repo);

        if (empty($main_file)) {
            $main_file = pubpi_find_plugin_main_file($slug);
        }

        $plugin_path = $slug . '/' . $main_file;
        $is_installed = file_exists(WP_PLUGIN_DIR . '/' . $slug);
        $is_active = $is_installed && !empty($main_file) && is_plugin_active($plugin_path);

        echo '<tr>';
        echo '<td>' . esc_html($plugin_name) . '</td>';
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

    // Section Thèmes GitHub
    echo '<h2 style="margin-top:30px;">Thèmes GitHub</h2>';
    echo '<table class="widefat fixed striped"><thead><tr><th>Nom du thème</th><th>Repository</th><th>Action</th></tr></thead><tbody>';

    foreach ($github_themes as $repo => $theme_name) {
        $slug = basename($repo);
        $theme = wp_get_theme($slug);
        $is_installed = $theme->exists();
        $is_active = (get_stylesheet() === $slug);

        echo '<tr>';
        echo '<td>' . esc_html($theme_name) . '</td>';
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

    echo '</tbody></table></div>';

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