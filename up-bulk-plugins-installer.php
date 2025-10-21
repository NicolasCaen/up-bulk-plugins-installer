<?php
/*
Plugin Name: Up Bulk Plugin Installer
Description: Installe, active et met à jour automatiquement une sélection de plugins et thèmes essentiels depuis WordPress.org ou GitHub.
Version: 1.4
Author: GEHIN Nicolas
*/

if (!defined('ABSPATH')) {
    exit;
}

// =============================
// Clean actions storage utilities
// =============================

function pubpi_get_cleaners_directory() {
    return trailingslashit(__DIR__ . '/config/clean-actions');
}

function pubpi_ensure_cleaners_directory() {
    $dir = pubpi_get_cleaners_directory();
    if (!is_dir($dir)) {
        if (!wp_mkdir_p($dir)) {
            return new WP_Error('pubpi_cleaners_dir', 'Impossible de créer le dossier des actions Clean.');
        }
    }
    return $dir;
}

function pubpi_list_saved_cleaners() {
    $dir = pubpi_ensure_cleaners_directory();
    if (is_wp_error($dir)) {
        return [];
    }
    $out = [];
    $files = glob(trailingslashit($dir) . '*.json');
    if ($files) {
        foreach ($files as $file) {
            $json = file_get_contents($file);
            if ($json === false) continue;
            $cfg = json_decode($json, true);
            if (!is_array($cfg)) continue;
            $slug = basename($file, '.json');
            $cfg['slug'] = $cfg['slug'] ?? $slug;
            $cfg['name'] = $cfg['name'] ?? $slug;
            $cfg['updated_at'] = $cfg['updated_at'] ?? '';
            $out[] = $cfg;
        }
    }
    usort($out, function ($a, $b) {
        return strcmp($a['slug'], $b['slug']);
    });
    return $out;
}

function pubpi_save_cleaner_config($cleaner) {
    $dir = pubpi_ensure_cleaners_directory();
    if (is_wp_error($dir)) return $dir;
    $slug = sanitize_title($cleaner['slug'] ?? '');
    if ($slug === '') return new WP_Error('pubpi_clean_slug', 'Slug de l’action requis.');

    $existing = pubpi_load_cleaner_config($slug);
    $timestamps = [
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ];
    if (is_array($existing) && isset($existing['created_at'])) {
        $timestamps['created_at'] = $existing['created_at'];
    }

    $target_dir = sanitize_text_field($cleaner['target_dir'] ?? '');
    $target_dir = ltrim($target_dir, '/');
    $target_file = sanitize_text_field($cleaner['target_file'] ?? '');
    $target_file = ltrim($target_file, '/');

    $extensions = pubpi_clean_normalize_list($cleaner['extensions'] ?? []);
    $directories = pubpi_clean_normalize_list($cleaner['directories'] ?? []);

    $payload = [
        'slug' => $slug,
        'name' => sanitize_text_field($cleaner['name'] ?? $slug),
        'type' => sanitize_text_field($cleaner['type'] ?? ''),
        'target_dir' => $target_dir,
        'target_file' => $target_file,
        'extensions' => $extensions,
        'mode' => sanitize_text_field($cleaner['mode'] ?? ''),
        'pattern' => sanitize_text_field($cleaner['pattern'] ?? ''),
        'directories' => $directories,
        'recursive' => !empty($cleaner['recursive']) ? true : false,
        'delete_originals' => !empty($cleaner['delete_originals']) ? true : false,
        'created_at' => $timestamps['created_at'],
        'updated_at' => $timestamps['updated_at'],
    ];

    if ($payload['type'] === '') {
        return new WP_Error('pubpi_clean_type', 'Type d’action Clean requis.');
    }

    $path = trailingslashit($dir) . $slug . '.json';
    $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) return new WP_Error('pubpi_clean_encode', 'Encodage JSON impossible.');
    if (!pubpi_write_file_with_fallback($path, $json)) return new WP_Error('pubpi_clean_write', 'Écriture de l’action Clean impossible.');
    return $payload;
}

function pubpi_delete_cleaner_config($slug) {
    $dir = pubpi_ensure_cleaners_directory();
    if (is_wp_error($dir)) return false;
    $path = trailingslashit($dir) . sanitize_title($slug) . '.json';
    if (file_exists($path)) return @unlink($path);
    return false;
}

function pubpi_load_cleaner_config($slug) {
    $dir = pubpi_ensure_cleaners_directory();
    if (is_wp_error($dir)) return $dir;
    $path = trailingslashit($dir) . sanitize_title($slug) . '.json';
    if (!file_exists($path)) return new WP_Error('pubpi_clean_missing', 'Action Clean introuvable.');
    $json = file_get_contents($path);
    if ($json === false) return new WP_Error('pubpi_clean_read', 'Lecture action Clean impossible.');
    $cfg = json_decode($json, true);
    if (!is_array($cfg)) return new WP_Error('pubpi_clean_json', 'JSON de l’action Clean invalide.');
    return $cfg;
}

function pubpi_clean_normalize_path($path, $base = '') {
    $path = trim((string) $path);
    if ($path === '') return '';
    if ($base === '') $base = WP_CONTENT_DIR;
    if (preg_match('#^[a-zA-Z]:\\#', $path) || strpos($path, '/') === 0) {
        $abs = $path;
    } else {
        $abs = trailingslashit($base) . ltrim($path, '/');
    }
    $abs = wp_normalize_path($abs);
    $check = $abs;
    if (!file_exists($abs)) {
        $check = wp_normalize_path(dirname($abs));
    }
    $root = wp_normalize_path(WP_CONTENT_DIR);
    $root_slash = trailingslashit($root);
    $real = realpath($check);
    if ($real !== false) {
        $check = wp_normalize_path($real);
    }
    if ($check !== '' && strpos(trailingslashit($check), $root_slash) !== 0) {
        return new WP_Error('pubpi_clean_path', 'Chemin hors de wp-content : ' . $abs);
    }
    return $abs;
}

function pubpi_clean_normalize_list($value) {
    if (is_string($value)) {
        $value = array_map('trim', explode(',', $value));
    }
    if (!is_array($value)) {
        return [];
    }
    $out = [];
    foreach ($value as $entry) {
        $entry = sanitize_text_field($entry);
        if ($entry === '') {
            continue;
        }
        $out[$entry] = true;
    }
    return array_keys($out);
}

function pubpi_clean_collect_files($dir, $file, $extensions = [], $recursive = true) {
    $files = [];
    if ($file !== '') {
        if (file_exists($file) && is_file($file)) {
            $files[] = wp_normalize_path($file);
        }
        return $files;
    }
    if ($dir === '' || !is_dir($dir)) return $files;
    if ($recursive) {
        return pubpi_scan_files_recursive($dir, $extensions);
    }
    $handle = opendir($dir);
    if (!$handle) return $files;
    while (($entry = readdir($handle)) !== false) {
        if ($entry === '.' || $entry === '..') continue;
        $candidate = wp_normalize_path(trailingslashit($dir) . $entry);
        if (!is_file($candidate)) continue;
        if (!empty($extensions)) {
            $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
            if (!in_array($ext, $extensions, true)) continue;
        }
        $files[] = $candidate;
    }
    closedir($handle);
    sort($files);
    return $files;
}

function pubpi_clean_strip_comments($content) {
    $content = preg_replace('/\/\*.*?\*\//s', '', $content);
    $content = preg_replace('/^\s*\/\/.*$/m', '', $content);
    return $content;
}

function pubpi_clean_js_minify($content) {
    $content = preg_replace('/\/\*.*?\*\//s', '', $content);
    $content = preg_replace('/^\s*\/\/.*$/m', '', $content);
    $content = preg_replace('/\s+/', ' ', $content);
    return trim($content);
}

function pubpi_clean_inline_php_includes($file, $delete_originals, &$report) {
    $content = file_get_contents($file);
    if ($content === false) {
        $report['errors'][] = 'Lecture impossible: ' . $file;
        return;
    }
    $pattern = '/^\s*(require_once|require|include_once|include)\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)\s*;.*$/m';
    $callback = function ($matches) use ($file, $delete_originals, &$report) {
        $includePath = $matches[2];
        $base = wp_normalize_path(dirname($file));
        $normalized = pubpi_clean_normalize_path($includePath, $base);
        if (is_wp_error($normalized) || !file_exists($normalized)) {
            $report['warnings'][] = 'Fichier introuvable pour ' . $includePath . ' dans ' . $file;
            return $matches[0];
        }
        $includedContent = file_get_contents($normalized);
        if ($includedContent === false) {
            $report['errors'][] = 'Lecture include impossible: ' . $normalized;
            return $matches[0];
        }
        if ($delete_originals) {
            @unlink($normalized);
            $report['deleted'][] = $normalized;
        }
        return "\n" . $includedContent . "\n";
    };
    $replaced = preg_replace_callback($pattern, $callback, $content);
    if ($replaced !== null && $replaced !== $content) {
        if (!pubpi_write_file_with_fallback($file, $replaced)) {
            $report['errors'][] = 'Écriture impossible: ' . $file;
            return;
        }
        $report['modified'][] = $file;
    }
}

function pubpi_clean_inline_scss_imports($file, $delete_originals, &$report) {
    $content = file_get_contents($file);
    if ($content === false) {
        $report['errors'][] = 'Lecture impossible: ' . $file;
        return;
    }
    $pattern = '/^\s*@import\s+[\'\"]([^\'\"]+)[\'\"]\s*;.*$/m';
    $callback = function ($matches) use ($file, $delete_originals, &$report) {
        $importPath = $matches[1];
        $base = wp_normalize_path(dirname($file));
        $normalized = pubpi_clean_normalize_path($importPath, $base);
        if (is_wp_error($normalized) || !file_exists($normalized)) {
            $report['warnings'][] = 'Fichier introuvable pour ' . $importPath . ' dans ' . $file;
            return $matches[0];
        }
        $includedContent = file_get_contents($normalized);
        if ($includedContent === false) {
            $report['errors'][] = 'Lecture import impossible: ' . $normalized;
            return $matches[0];
        }
        if ($delete_originals) {
            @unlink($normalized);
            $report['deleted'][] = $normalized;
        }
        return "\n" . $includedContent . "\n";
    };
    $replaced = preg_replace_callback($pattern, $callback, $content);
    if ($replaced !== null && $replaced !== $content) {
        if (!pubpi_write_file_with_fallback($file, $replaced)) {
            $report['errors'][] = 'Écriture impossible: ' . $file;
            return;
        }
        $report['modified'][] = $file;
    }
}

function pubpi_clean_remove_directories($base, $directories, &$report) {
    foreach ($directories as $rel) {
        $path = pubpi_clean_normalize_path($rel, $base);
        if (is_wp_error($path)) {
            $report['errors'][] = $path->get_error_message();
            continue;
        }
        if (!file_exists($path) || !is_dir($path)) {
            continue;
        }
        pubpi_rrmdir($path, $report);
        $report['deleted'][] = $path;
    }
}

function pubpi_rrmdir($dir, &$report = null) {
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($dir);
}

function pubpi_run_cleaner_by_slug($slug) {
    $cfg = pubpi_load_cleaner_config($slug);
    if (is_wp_error($cfg)) return $cfg;

    $report = [
        'modified' => [],
        'deleted' => [],
        'errors' => [],
        'warnings' => [],
    ];

    $type = $cfg['type'];
    $targetDir = pubpi_clean_normalize_path($cfg['target_dir'] ?? '');
    if (is_wp_error($targetDir)) return $targetDir;
    $targetFile = pubpi_clean_normalize_path($cfg['target_file'] ?? '', $targetDir !== '' ? $targetDir : WP_CONTENT_DIR);
    if (is_wp_error($targetFile)) return $targetFile;

    switch ($type) {
        case 'remove_comments':
            $files = pubpi_clean_collect_files($targetDir, $targetFile, $cfg['extensions'] ?? [], !empty($cfg['recursive']));
            pubpi_clean_strip_comments_from_files($files, $report);
            break;
        case 'inline_php':
            $files = pubpi_clean_collect_files($targetDir, $targetFile, ['php'], false);
            foreach ($files as $file) {
                pubpi_clean_inline_php_includes($file, !empty($cfg['delete_originals']), $report);
            }
            break;
        case 'inline_scss':
            $files = pubpi_clean_collect_files($targetDir, $targetFile, ['scss'], false);
            foreach ($files as $file) {
                pubpi_clean_inline_scss_imports($file, !empty($cfg['delete_originals']), $report);
            }
            break;
        case 'minify_js':
            $files = pubpi_clean_collect_files($targetDir, $targetFile, ['js'], !empty($cfg['recursive']));
            foreach ($files as $file) {
                $original = file_get_contents($file);
                if ($original === false) {
                    $report['errors'][] = 'Lecture impossible: ' . $file;
                    continue;
                }
                $minified = pubpi_clean_js_minify($original);
                if ($minified !== $original) {
                    if (!pubpi_write_file_with_fallback($file, $minified)) {
                        $report['errors'][] = 'Écriture impossible: ' . $file;
                        continue;
                    }
                    $report['modified'][] = $file;
                }
            }
            break;
        case 'purge_directories':
            $directories = $cfg['directories'] ?? [];
            $base = ($targetDir !== '') ? $targetDir : WP_CONTENT_DIR;
            pubpi_clean_remove_directories($base, $directories, $report);
            break;
        default:
            return new WP_Error('pubpi_clean_type', 'Type d’action Clean non supporté.');
    }

    return $report;
}

function pubpi_clean_strip_comments_from_files($files, &$report) {
    foreach ($files as $file) {
        $original = file_get_contents($file);
        if ($original === false) {
            $report['errors'][] = 'Lecture impossible: ' . $file;
            continue;
        }
        $stripped = pubpi_clean_strip_comments($original);
        if ($stripped !== $original) {
            if (!pubpi_write_file_with_fallback($file, $stripped)) {
                $report['errors'][] = 'Écriture impossible: ' . $file;
                continue;
            }
            $report['modified'][] = $file;
        }
    }
}

// =============================
// Generators storage utilities
// =============================

function pubpi_get_generators_directory() {
    return trailingslashit(__DIR__ . '/config/file-generators');
}

function pubpi_ensure_generators_directory() {
    $dir = pubpi_get_generators_directory();
    if (!is_dir($dir)) {
        if (!wp_mkdir_p($dir)) {
            return new WP_Error('pubpi_generators_dir', 'Impossible de créer le dossier des générateurs.');
        }
    }
    return $dir;
}

function pubpi_list_saved_generators() {
    $dir = pubpi_ensure_generators_directory();
    if (is_wp_error($dir)) {
        return [];
    }
    $out = [];
    $files = glob(trailingslashit($dir) . '*.json');
    if ($files) {
        foreach ($files as $file) {
            $json = file_get_contents($file);
            if ($json === false) continue;
            $cfg = json_decode($json, true);
            if (!is_array($cfg)) continue;
            $slug = basename($file, '.json');
            $cfg['slug'] = $cfg['slug'] ?? $slug;
            $cfg['name'] = $cfg['name'] ?? $slug;
            $out[] = $cfg;
        }
    }
    usort($out, function($a,$b){ return strcmp($a['slug'],$b['slug']); });
    return $out;
}

function pubpi_save_generator_config($gen) {
    $dir = pubpi_ensure_generators_directory();
    if (is_wp_error($dir)) return $dir;
    $slug = sanitize_title($gen['slug'] ?? '');
    if ($slug === '') return new WP_Error('pubpi_gen_slug', 'Slug du générateur requis.');
    $payload = [
        'slug' => $slug,
        'name' => sanitize_text_field($gen['name'] ?? $slug),
        'type' => sanitize_text_field($gen['type'] ?? ''),
        'destination' => sanitize_text_field($gen['destination'] ?? 'theme'),
        'source_dir' => ltrim(sanitize_text_field($gen['source_dir'] ?? ''), '/'),
        'target_file' => ltrim(sanitize_text_field($gen['target_file'] ?? ''), '/'),
        'handle' => sanitize_text_field($gen['handle'] ?? ''),
        'deps' => array_values(array_filter(array_map('sanitize_text_field', (array)($gen['deps'] ?? [])))),
        'in_footer' => !empty($gen['in_footer']) ? true : false,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ];
    if ($payload['type'] === '' || $payload['source_dir'] === '' || $payload['target_file'] === '') {
        return new WP_Error('pubpi_gen_fields', 'Champs du générateur incomplets.');
    }
    $path = trailingslashit($dir) . $slug . '.json';
    $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) return new WP_Error('pubpi_gen_encode', 'Encodage JSON impossible.');
    if (file_put_contents($path, $json) === false) return new WP_Error('pubpi_gen_write', 'Écriture du générateur impossible.');
    return $payload;
}

function pubpi_delete_generator_config($slug) {
    $dir = pubpi_ensure_generators_directory();
    if (is_wp_error($dir)) return false;
    $path = trailingslashit($dir) . sanitize_title($slug) . '.json';
    if (file_exists($path)) return @unlink($path);
    return false;
}

function pubpi_load_generator_config($slug) {
    $dir = pubpi_ensure_generators_directory();
    if (is_wp_error($dir)) return $dir;
    $path = trailingslashit($dir) . sanitize_title($slug) . '.json';
    if (!file_exists($path)) return new WP_Error('pubpi_gen_missing', 'Générateur introuvable.');
    $json = file_get_contents($path);
    if ($json === false) return new WP_Error('pubpi_gen_read', 'Lecture générateur impossible.');
    $cfg = json_decode($json, true);
    if (!is_array($cfg)) return new WP_Error('pubpi_gen_json', 'JSON générateur invalide.');
    return $cfg;
}

function pubpi_resolve_base_dir_and_uri($destination) {
    switch ($destination) {
        case 'mu-plugins':
            return [WPMU_PLUGIN_DIR, content_url('mu-plugins')];
        case 'plugins':
            return [WP_PLUGIN_DIR, content_url('plugins')];
        case 'theme':
        default:
            return [get_stylesheet_directory(), get_stylesheet_directory_uri()];
    }
}

function pubpi_scan_files_recursive($directory, $extensions = []) {
    $files = [];
    if (!is_dir($directory)) return $files;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $fileInfo) {
        if (!$fileInfo->isFile()) continue;
        $path = $fileInfo->getPathname();
        if (!empty($extensions)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, $extensions, true)) continue;
        }
        $files[] = $path;
    }
    sort($files);
    return $files;
}

function pubpi_ensure_marker_block($target_file, $block_key, $lines) {
    $start = "/* ----- {$block_key} ----- */";
    $end = "/* ----- {$block_key} fin ----- */";
    $contents = file_exists($target_file) ? file_get_contents($target_file) : '';
    if ($contents === false) return new WP_Error('pubpi_marker_read', 'Impossible de lire le fichier cible.');
    $normalized = str_replace(["\r\n","\r"], "\n", (string)$contents);
    if ($normalized === '') {
        $normalized = '';
    }
    $block = $start . "\n" . implode("\n", $lines) . "\n" . $end . "\n";
    if (strpos($normalized, $start) === false || strpos($normalized, $end) === false) {
        // prepend block
        $new = $block . $normalized;
        if (!pubpi_write_file_with_fallback($target_file, $new)) return new WP_Error('pubpi_marker_write', 'Écriture du bloc impossible.');
        return count($lines);
    }
    // Update existing block with de-dup by inserting missing lines just after start
    $before = substr($normalized, 0, strpos($normalized, $start) + strlen($start));
    $afterStartPos = strpos($normalized, $start) + strlen($start);
    $afterEndPos = strpos($normalized, $end);
    $middle = substr($normalized, $afterStartPos, $afterEndPos - $afterStartPos);
    $middleLines = array_values(array_filter(array_map('trim', explode("\n", $middle))));
    $added = 0;
    foreach (array_reverse($lines) as $line) { // insert at top (after start), preserve manual order
        if (!in_array(trim($line), $middleLines, true)) {
            array_unshift($middleLines, trim($line));
            $added++;
        }
    }
    $newMiddle = "\n" . implode("\n", $middleLines) . "\n";
    $new = $before . $newMiddle . substr($normalized, $afterEndPos);
    if (!pubpi_write_file_with_fallback($target_file, $new)) return new WP_Error('pubpi_marker_write', 'Mise à jour du bloc impossible.');
    return $added;
}

function pubpi_run_generator_by_slug($slug) {
    $cfg = pubpi_load_generator_config($slug);
    if (is_wp_error($cfg)) return $cfg;
    list($base_dir, $base_uri) = pubpi_resolve_base_dir_and_uri($cfg['destination'] ?? 'theme');
    if (empty($base_dir)) return new WP_Error('pubpi_gen_base', 'Base introuvable pour la destination.');
    $source_dir = trailingslashit($base_dir) . ltrim($cfg['source_dir'], '/');
    $target_file = trailingslashit($base_dir) . ltrim($cfg['target_file'], '/');
    if (!is_dir(dirname($target_file))) {
        if (!wp_mkdir_p(dirname($target_file))) return new WP_Error('pubpi_gen_target_dir', 'Impossible de créer le dossier cible.');
    }
    if (!file_exists($target_file)) {
        if (!pubpi_write_file_with_fallback($target_file, "")) return new WP_Error('pubpi_gen_touch', 'Impossible de créer le fichier cible.');
    }

    $target_ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    if ($target_ext === 'php') {
        $current = file_get_contents($target_file);
        if ($current === false) {
            return new WP_Error('pubpi_gen_target_read', 'Impossible de lire le fichier PHP cible.');
        }
        $trimmed = ltrim($current);
        if (strpos($trimmed, "<?php") !== 0) {
            $body = ltrim($current, "\r\n\t ");
            $prefixed = "<?php\n\n" . $body;
            if (!pubpi_write_file_with_fallback($target_file, $prefixed)) {
                return new WP_Error('pubpi_gen_php_prefix', 'Impossible de préparer le fichier PHP cible.');
            }
        } elseif (strpos($current, "<?php") !== 0) {
            // Nettoyer les espaces avant l'ouverture PHP
            $normalized = preg_replace('/^\s+/', '', $current);
            if (!pubpi_write_file_with_fallback($target_file, $normalized)) {
                return new WP_Error('pubpi_gen_php_prefix', 'Impossible de normaliser le fichier PHP cible.');
            }
        }
    }

    $exts = [];
    $block_key = $cfg['type'];
    if ($cfg['type'] === 'php_include') $exts = ['php'];
    if ($cfg['type'] === 'scss_import') $exts = ['scss'];
    if ($cfg['type'] === 'js_register' || $cfg['type'] === 'js_enqueue') $exts = ['js'];

    $files = pubpi_scan_files_recursive($source_dir, $exts);
    $lines = [];

    $target_real = realpath($target_file) ?: $target_file;
    foreach ($files as $abs) {
        // Do not import/include the target file itself
        $abs_real = realpath($abs) ?: $abs;
        if ($abs_real === $target_real) {
            continue;
        }
        $rel_from_base = ltrim(str_replace(trailingslashit($base_dir), '', $abs), '/');
        switch ($cfg['type']) {
            case 'php_include':
                if ($cfg['destination'] === 'theme') {
                    $lines[] = "require_once get_stylesheet_directory() . '/" . $rel_from_base . "';";
                } elseif ($cfg['destination'] === 'plugins') {
                    $lines[] = "require_once WP_PLUGIN_DIR . '/" . $rel_from_base . "';";
                } else {
                    $lines[] = "require_once WPMU_PLUGIN_DIR . '/" . $rel_from_base . "';";
                }
                break;
            case 'scss_import':
                // only partials: basename must start with '_'
                $bn = basename($abs);
                if ($bn === basename($target_file)) {
                    break;
                }
                if (substr($bn, 0, 1) !== '_') {
                    break; // skip non-partial .scss
                }
                // path relative to target file directory
                $rel_to_target = ltrim(str_replace(trailingslashit(dirname($target_file)), '', $abs), '/');
                $rel_to_target = str_replace("\\", '/', $rel_to_target);
                // strip .scss extension
                if (substr($rel_to_target, -5) === '.scss') {
                    $rel_to_target = substr($rel_to_target, 0, -5);
                }
                $rel_segments = explode('/', $rel_to_target);
                $last_segment = array_pop($rel_segments);
                if (substr($last_segment, 0, 1) === '_') {
                    $last_segment = substr($last_segment, 1);
                }
                $rel_clean = trim(($rel_segments ? implode('/', $rel_segments) . '/' : '') . $last_segment, '/');
                if ($rel_clean !== '') {
                    $lines[] = "@import '" . $rel_clean . "';";
                }
                break;
            case 'js_register':
            case 'js_enqueue':
                $handle_base = $cfg['handle'] !== '' ? sanitize_title($cfg['handle']) : 'pubpi-script';
                $file_slug = sanitize_title(basename($abs, '.js'));
                $handle = $handle_base . '-' . $file_slug;
                $deps = isset($cfg['deps']) ? array_values(array_filter((array)$cfg['deps'])) : [];
                $deps_php = "['" . implode("','", array_map('esc_js', $deps)) . "']";
                $url = rtrim($base_uri, '/') . '/' . $rel_from_base;
                if ($cfg['type'] === 'js_register') {
                    $lines[] = "wp_register_script('{$handle}', '" . $url . "', {$deps_php}, null, " . (!empty($cfg['in_footer']) ? 'true' : 'false') . ");";
                } else {
                    $lines[] = "wp_enqueue_script('{$handle}', '" . $url . "', {$deps_php}, null, " . (!empty($cfg['in_footer']) ? 'true' : 'false') . ");";
                }
                break;
        }
    }

    // For JS, wrap with add_action to ensure execution
    if ($cfg['type'] === 'js_register' || $cfg['type'] === 'js_enqueue') {
        if (!empty($lines)) {
            array_unshift($lines, "add_action('wp_enqueue_scripts', function() {" );
            $lines[] = "}, 10);";
        }
    }

    return pubpi_ensure_marker_block($target_file, $block_key, $lines);
}

function pubpi_write_file_with_fallback($path, $contents) {
    // Try WP_Filesystem first
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();
    global $wp_filesystem;
    if ($wp_filesystem && is_object($wp_filesystem)) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!wp_mkdir_p($dir)) return false;
        }
        $ok = $wp_filesystem->put_contents($path, $contents, FS_CHMOD_FILE);
        if ($ok) return true;
    }
    // Fallback to direct file_put_contents
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!wp_mkdir_p($dir)) return false;
    }
    return file_put_contents($path, $contents) !== false;
}

function pubpi_register_rest_routes() {
    register_rest_route(
        'up-bulk-plugins-installer/v1',
        '/sets',
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'pubpi_rest_list_sets',
            'permission_callback' => 'pubpi_rest_can_manage',
        ]
    );

    register_rest_route(
        'up-bulk-plugins-installer/v1',
        '/sets/(?P<slug>[a-z0-9\-_/]+)/install',
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'pubpi_rest_install_set',
            'permission_callback' => 'pubpi_rest_can_manage',
            'args' => [
                'manifest_target' => [
                    'type' => 'string',
                    'required' => false,
                ],
            ],
        ]
    );
}

function pubpi_rest_can_manage() {
    return current_user_can('manage_options');
}

function pubpi_rest_list_sets() {
    $sets = pubpi_list_saved_sets();
    return rest_ensure_response($sets);
}

function pubpi_rest_install_set(WP_REST_Request $request) {
    $slug = sanitize_title($request->get_param('slug'));
    if ($slug === '') {
        return new WP_Error('pubpi_rest_invalid_slug', 'Slug de set invalide.', ['status' => 400]);
    }

    $manifest_target = $request->get_param('manifest_target');

    $result = pubpi_install_set_by_slug($slug, [
        'context' => 'rest',
        'manifest_target' => $manifest_target,
    ]);

    if (is_wp_error($result)) {
        $status = 500;
        $data = $result->get_error_data();
        if (is_array($data) && isset($data['status'])) {
            $status = (int) $data['status'];
        }
        $response = new WP_Error($result->get_error_code(), $result->get_error_message(), $data);
        $response->add_data(['status' => $status]);
        return $response;
    }

    return rest_ensure_response($result);
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

    echo '<h2>5. Utiliser l&#8217;API REST des sets</h2>';
    echo '<p>L&#8217;API REST permet de lister et d&#8217;installer des sets à distance. Vous devez être authentifié avec un compte ayant la capacité <code>manage_options</code> (nonce WP REST ou authentification basique pour tests locaux).</p>';
    echo '<h3>Routes disponibles</h3>';
    echo '<ul>';
    echo '<li><code>GET /wp-json/up-bulk-plugins-installer/v1/sets</code> : retourne tous les sets enregistrés.</li>';
    echo '<li><code>POST /wp-json/up-bulk-plugins-installer/v1/sets/&lt;slug&gt;/install</code> : installe le set ciblé. Paramètre optionnel <code>manifest_target</code> pour forcer la destination des patterns (<code>theme</code>, <code>mu-plugins</code> ou <code>plugins</code>).</li>';
    echo '</ul>';
    echo '<h3>Exemples de requêtes</h3>';
    $rest_examples = <<<'HTML'
<pre style="background:#1e1e1e; color:#f5f5f5; border:1px solid #111; padding:12px; overflow:auto;"><code>curl https://example.com/wp-json/up-bulk-plugins-installer/v1/sets \\ 
    -H "X-WP-Nonce: &lt;nonce&gt;"

curl https://example.com/wp-json/up-bulk-plugins-installer/v1/sets/hotel/install \\ 
    -X POST \\ 
    -H "Content-Type: application/json" \\ 
    -H "X-WP-Nonce: &lt;nonce&gt;" \\ 
    -d '{"manifest_target":"theme"}'</code></pre>
HTML;
    echo $rest_examples;
    echo '<p>La réponse d&#8217;installation renvoie le nombre d&#8217;éléments traités, la destination appliquée et, si la requête est effectuée côté REST, les messages générés par l&#8217;installation.</p>';

    echo '<p>Pour chaque ajout ou modification du manifest, videz le cache navigateur si nécessaire et vérifiez que les chemins indiqués dans <code>files</code> existent bien dans l&#8217;archive GitHub.</p>';
    echo '</div>';
}

function pubpi_render_api_docs_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    echo '<div class="wrap">';
    echo '<h1>API REST des sets</h1>';
    echo '<p>Cette page documente les routes REST fournies par le plugin pour consulter et installer les sets enregistrés.</p>';

    echo '<h2>Authentification</h2>';
    echo '<p>Les requêtes doivent être effectuées avec un compte disposant de la capacité <code>manage_options</code>. Utilisez un nonce WordPress REST (en envoyant l&#8217;en-tête <code>X-WP-Nonce</code>) ou l&#8217;authentification basique sur un environnement de test sécurisé.</p>';

    echo '<h2>Base des routes</h2>';
    echo '<p>Toutes les routes sont exposées sous le namespace <code>up-bulk-plugins-installer/v1</code>.</p>';

    echo '<h2>Endpoints disponibles</h2>';
    echo '<ul>';
    echo '<li><code>GET /wp-json/up-bulk-plugins-installer/v1/sets</code> : retourne la liste complète des sets enregistrés (slug, nom, éléments, métadonnées).</li>';
    echo '<li><code>POST /wp-json/up-bulk-plugins-installer/v1/sets/&lt;slug&gt;/install</code> : installe le set correspondant au slug fourni. Paramètre optionnel <code>manifest_target</code> pour forcer la destination des patterns (<code>theme</code>, <code>mu-plugins</code> ou <code>plugins</code>).</li>';
    echo '</ul>';

    echo '<h2>Exemples de requêtes</h2>';
    $curl_examples = <<<'HTML'
<pre style="background:#1e1e1e; color:#f5f5f5; border:1px solid #111; padding:12px; overflow:auto;"><code>curl https://example.com/wp-json/up-bulk-plugins-installer/v1/sets \
    -H "X-WP-Nonce: &lt;nonce&gt;"

curl https://example.com/wp-json/up-bulk-plugins-installer/v1/sets/mu-plugins-cpt/install \
    -X POST \
    -H "Content-Type: application/json" \
    -H "X-WP-Nonce: &lt;nonce&gt;" \
    -d '{"manifest_target":"mu-plugins"}'</code></pre>
HTML;
    echo $curl_examples;

    echo '<h2>Réponses attendues</h2>';
    echo '<p>Les réponses suivent le format JSON standard de WordPress :</p>';
    echo '<ul>';
    echo '<li><strong>GET sets</strong> : tableau d’objets contenant <code>slug</code>, <code>name</code>, <code>items</code> et <code>meta</code>.</li>';
    echo '<li><strong>POST install</strong> : objet détaillant le nombre d’éléments installés, la destination effective et les messages d’opération.</li>';
    echo '</ul>';

    echo '<h2>Conseils</h2>';
    echo '<ul>';
    echo '<li>Générez un nonce côté WordPress via <code>wp_create_nonce(&#39;wp_rest&#39;)</code> et transmettez-le dans l&#8217;en-tête <code>X-WP-Nonce</code>.</li>';
    echo '<li>Vérifiez que le slug transmis dans l’URL correspond exactement au fichier de set (ex : <code>mu-plugins-cpt</code> pour <code>mu-plugins-cpt.json</code>).</li>';
    echo '<li>Utilisez l&#8217;argument <code>manifest_target</code> uniquement si vous souhaitez forcer la destination par défaut configurée dans le set.</li>';
    echo '</ul>';

    echo '</div>';
}

add_action('admin_menu', 'pubpi_add_admin_page');
add_action('rest_api_init', 'pubpi_register_rest_routes');

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

    add_submenu_page(
        'bulk-plugin-installer',
        'Documentation API REST',
        'Doc API REST',
        'manage_options',
        'bulk-plugin-installer-api-docs',
        'pubpi_render_api_docs_page'
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
    $saved_sets = pubpi_list_saved_sets();

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
    echo '<a href="#pubpi-tab-generators" class="nav-tab">Générateurs</a>';
    echo '<a href="#pubpi-tab-sets" class="nav-tab">Sets</a>';
    echo '<a href="#pubpi-tab-clean" class="nav-tab">Clean</a>';
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
    echo '<table id="pubpi-wp-plugins" class="widefat fixed striped"><thead><tr><th class="pubpi-set-col">Add set</th><th>Nom du plugin</th><th>Description</th><th>Catégories</th><th>Action</th></tr></thead><tbody>';

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
        echo '<td class="pubpi-set-cell"><label class="pubpi-set-option"><input type="checkbox" class="pubpi-set-item" data-type="wp_plugin" data-plugin-path="' . esc_attr($plugin_path) . '" data-plugin-name="' . esc_attr($plugin_name) . '"> </label></td>';
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

    echo '<div id="pubpi-tab-sets" class="pubpi-tab-panel">';
    echo '<h2>Sets d’éléments</h2>';
    echo '<p>Enregistrez des sélections d’éléments afin de les réinstaller rapidement sur d’autres sites.</p>';
    echo '<div class="pubpi-sets-section">';
    if (!empty($saved_sets)) {
        echo '<div class="pubpi-sets-table-wrapper">';
        echo '<h3>Sets enregistrés</h3>';
        echo '<table class="widefat fixed striped pubpi-sets-table"><thead><tr><th>Nom</th><th>Slug</th><th>Éléments</th><th>Action</th></tr></thead><tbody>';
        foreach ($saved_sets as $set_entry) {
            $count_items = is_array($set_entry['items']) ? count($set_entry['items']) : 0;
            echo '<tr>';
            echo '<td><strong>' . esc_html($set_entry['name']) . '</strong></td>';
            echo '<td><code>' . esc_html($set_entry['slug']) . '</code></td>';
            echo '<td>' . esc_html($count_items) . '</td>';
            echo '<td><form method="post" class="pubpi-set-install-form"><input type="hidden" name="pubpi_set_slug" value="' . esc_attr($set_entry['slug']) . '">';
            submit_button('Installer', 'secondary small', 'pubpi_install_set', false);
            echo '</form></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    echo '<div class="pubpi-sets-load">';
    echo '<label for="pubpi-load-set-select">Charger un set existant&nbsp;:</label>';
    echo '<select id="pubpi-load-set-select" class="pubpi-set-select">';
    echo '<option value="">— Sélectionner —</option>';
    foreach ($saved_sets as $set_entry) {
        echo '<option value="' . esc_attr($set_entry['slug']) . '">' . esc_html($set_entry['name']) . '</option>';
    }
    echo '</select>';
    echo '<button type="button" class="button" id="pubpi-load-set-button">Charger</button>';
    echo '</div>';

    echo '<form method="post" id="pubpi-set-form" class="pubpi-set-form">';
    echo '<h3 class="pubpi-set-form-title">Créer un nouveau set</h3>';
    echo '<p class="description pubpi-set-note">Sélectionnez les éléments directement dans les onglets correspondants grâce aux cases à cocher ils seront ajoutés au set.</p>';

    echo '<div class="pubpi-set-fields">';
    echo '<label for="pubpi-set-slug">Slug du set</label>';
    echo '<input type="text" id="pubpi-set-slug" name="pubpi_set_slug" class="regular-text" />';
    echo '<label for="pubpi-set-name">Nom du set</label>';
    echo '<input type="text" id="pubpi-set-name" name="pubpi_set_name" class="regular-text" />';
    echo '<label for="pubpi-set-manifest-default">Destination par défaut des patterns manifest</label>';
    echo '<select id="pubpi-set-manifest-default" name="pubpi_set_manifest_default" class="pubpi-set-select">';
    echo '<option value="">Défaut (manifest)</option>';
    echo '<option value="theme">Thème actif</option>';
    echo '<option value="mu-plugins">MU-Plugins</option>';
    echo '<option value="plugins">Plugins</option>';
    echo '</select>';
    echo '</div>';


    echo '<input type="hidden" name="pubpi_set_payload" value="" />';
    submit_button('Enregistrer le set', 'primary', 'pubpi_save_set', false);
    echo '</form>';

    echo '</div>';
    echo '<script type="application/json" id="pubpi-saved-sets-data">' . wp_json_encode($saved_sets) . '</script>';
    echo '</div>';

    // Tab: Clean actions
    $saved_cleaners = pubpi_list_saved_cleaners();
    echo '<div id="pubpi-tab-clean" class="pubpi-tab-panel">';
    echo '<h2>Actions Clean</h2>';
    echo '<p>Automatisez les opérations de nettoyage et d’optimisation sur vos fichiers.</p>';
    echo '<table class="widefat fixed striped"><thead><tr><th class="pubpi-set-col">Add set</th><th>Nom</th><th>Type</th><th>Cible</th><th>Dernière mise à jour</th><th>Actions</th></tr></thead><tbody>';
    if (!empty($saved_cleaners)) {
        foreach ($saved_cleaners as $cleaner) {
            $type = $cleaner['type'] ?? '';
            $targetDir = $cleaner['target_dir'] ?? '';
            $targetFile = $cleaner['target_file'] ?? '';
            $updated = $cleaner['updated_at'] ?? '';
            $cible = $targetFile !== '' ? $targetFile : $targetDir;
            echo '<tr>';
            echo '<td class="pubpi-set-cell"><label class="pubpi-set-option"><input type="checkbox" class="pubpi-set-item" data-type="clean_action" data-clean-slug="' . esc_attr($cleaner['slug']) . '"> </label></td>';
            echo '<td><strong>' . esc_html($cleaner['name']) . '</strong><br><code>' . esc_html($cleaner['slug']) . '</code></td>';
            echo '<td>' . esc_html($type) . '</td>';
            echo '<td><code>' . esc_html($cible) . '</code></td>';
            echo '<td>' . esc_html($updated) . '</td>';
            echo '<td>';
            echo '<form method="post" style="display:inline-block; margin-right:6px;"><input type="hidden" name="pubpi_cleaner_slug" value="' . esc_attr($cleaner['slug']) . '">';
            submit_button('Exécuter', 'secondary small', 'pubpi_run_cleaner', false);
            echo '</form>';
            echo '<form method="post" style="display:inline-block;"><input type="hidden" name="pubpi_cleaner_slug" value="' . esc_attr($cleaner['slug']) . '">';
            submit_button('Supprimer', 'link-delete', 'pubpi_delete_cleaner', false);
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6">Aucune action Clean enregistrée.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<div class="pubpi-clean-load">';
    echo '<label for="pubpi-load-cleaner-select">Charger une action existante&nbsp;:</label>';
    echo '<select id="pubpi-load-cleaner-select" class="pubpi-set-select">';
    echo '<option value="">— Sélectionner —</option>';
    foreach ($saved_cleaners as $cleaner) {
        $option_data = wp_json_encode($cleaner, JSON_UNESCAPED_UNICODE);
        echo '<option value="' . esc_attr($cleaner['slug']) . '" data-cleaner="' . esc_attr($option_data) . '">' . esc_html($cleaner['name'] ?? $cleaner['slug']) . '</option>';
    }
    echo '</select>';
    echo '<button type="button" class="button" id="pubpi-load-cleaner-button">Charger</button>';
    echo '<button type="button" class="button" id="pubpi-reset-cleaner-button">Réinitialiser</button>';
    echo '</div>';

    echo '<h3>Créer ou modifier une action</h3>';
    echo '<form method="post" class="pubpi-set-form" id="pubpi-cleaner-form">';
    echo '<div class="pubpi-set-fields">';
    echo '<label for="pubpi-clean-name">Nom</label>';
    echo '<input type="text" id="pubpi-clean-name" name="pubpi_clean_name" class="regular-text" />';
    echo '<label for="pubpi-clean-slug">Slug</label>';
    echo '<input type="text" id="pubpi-clean-slug" name="pubpi_clean_slug" class="regular-text" />';
    echo '<label for="pubpi-clean-type">Type</label>';
    echo '<select id="pubpi-clean-type" name="pubpi_clean_type" class="pubpi-set-select">';
    echo '<option value="remove_comments">Supprimer commentaires</option>';
    echo '<option value="inline_php">Inline includes PHP</option>';
    echo '<option value="inline_scss">Inline imports SCSS</option>';
    echo '<option value="minify_js">Minifier JS</option>';
    echo '<option value="purge_directories">Purger dossiers</option>';
    echo '</select>';
    echo '<label for="pubpi-clean-target-dir">Dossier cible (relatif wp-content)</label>';
    echo '<input type="text" id="pubpi-clean-target-dir" name="pubpi_clean_target_dir" class="regular-text" placeholder="ex: mu-plugins/nicolas" />';
    echo '<label for="pubpi-clean-target-file">Fichier cible (relatif au dossier)</label>';
    echo '<input type="text" id="pubpi-clean-target-file" name="pubpi_clean_target_file" class="regular-text" placeholder="ex: root.scss ou includes.php" />';
    echo '<label for="pubpi-clean-extensions">Extensions (CSV)</label>';
    echo '<input type="text" id="pubpi-clean-extensions" name="pubpi_clean_extensions" class="regular-text" placeholder="ex: php,js,scss" />';
    echo '<label><input type="checkbox" id="pubpi-clean-recursive" name="pubpi_clean_recursive" value="1" /> Parcours récursif</label>';
    echo '<label><input type="checkbox" id="pubpi-clean-delete-originals" name="pubpi_clean_delete_originals" value="1" /> Supprimer les fichiers originaux (inline)</label>';
    echo '<label for="pubpi-clean-directories">Dossiers à supprimer (CSV)</label>';
    echo '<input type="text" id="pubpi-clean-directories" name="pubpi_clean_directories" class="regular-text" placeholder="ex: node_modules,.git" />';
    echo '</div>';
    echo '<input type="hidden" id="pubpi-clean-original" name="pubpi_clean_original_slug" value="" />';
    submit_button('Enregistrer l’action', 'primary', 'pubpi_save_cleaner', false);
    echo '</form>';
    echo '</div>';
    echo '<script type="application/json" id="pubpi-saved-cleaners-data">' . wp_json_encode($saved_cleaners) . '</script>';

    // Tab: Generators
    $saved_generators = pubpi_list_saved_generators();
    echo '<div id="pubpi-tab-generators" class="pubpi-tab-panel">';
    echo '<h2>Générateurs de fichiers</h2>';
    echo '<p>Automatisez la création de fichiers d\'agrégation: includes PHP, imports SCSS, register/enqueue scripts JS.</p>';
    echo '<table class="widefat fixed striped"><thead><tr><th class="pubpi-set-col">Add set</th><th>Nom</th><th>Type</th><th>Destination</th><th>Source</th><th>Fichier cible</th><th>Action</th></tr></thead><tbody>';
    if (!empty($saved_generators)) {
        foreach ($saved_generators as $gen) {
            $dest = $gen['destination'] ?? '';
            $type = $gen['type'] ?? '';
            $source = $gen['source_dir'] ?? '';
            $target = $gen['target_file'] ?? '';
            echo '<tr>';
            echo '<td class="pubpi-set-cell"><label class="pubpi-set-option"><input type="checkbox" class="pubpi-set-item" data-type="file_generator" data-generator-slug="' . esc_attr($gen['slug']) . '"> </label></td>';
            echo '<td><strong>' . esc_html($gen['name']) . '</strong><br><code>' . esc_html($gen['slug']) . '</code></td>';
            echo '<td>' . esc_html($type) . '</td>';
            echo '<td>' . esc_html($dest) . '</td>';
            echo '<td><code>' . esc_html($source) . '</code></td>';
            echo '<td><code>' . esc_html($target) . '</code></td>';
            echo '<td>';
            echo '<form method="post" style="display:inline-block; margin-right:6px;"><input type="hidden" name="pubpi_generator_slug" value="' . esc_attr($gen['slug']) . '">';
            submit_button('Générer', 'secondary small', 'pubpi_run_generator', false);
            echo '</form>';
            echo '<form method="post" style="display:inline-block;"><input type="hidden" name="pubpi_generator_slug" value="' . esc_attr($gen['slug']) . '">';
            submit_button('Supprimer', 'link-delete', 'pubpi_delete_generator', false);
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="7">Aucun générateur enregistré.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<div class="pubpi-generators-load">';
    echo '<label for="pubpi-load-generator-select">Charger un générateur existant&nbsp;:</label>';
    echo '<select id="pubpi-load-generator-select" class="pubpi-set-select">';
    echo '<option value="">— Sélectionner —</option>';
    foreach ($saved_generators as $gen) {
        $option_data = wp_json_encode($gen, JSON_UNESCAPED_UNICODE);
        echo '<option value="' . esc_attr($gen['slug']) . '" data-generator="' . esc_attr($option_data) . '">' . esc_html($gen['name'] ?? $gen['slug']) . '</option>';
    }
    echo '</select>';
    echo '<button type="button" class="button" id="pubpi-load-generator-button">Charger</button>';
    echo '<button type="button" class="button" id="pubpi-reset-generator-button">Réinitialiser</button>';
    echo '</div>';

    echo '<h3>Créer ou modifier un générateur</h3>';
    echo '<form method="post" class="pubpi-set-form" id="pubpi-generator-form">';
    echo '<div class="pubpi-set-fields">';
    echo '<label for="pubpi-gen-name">Nom</label>';
    echo '<input type="text" id="pubpi-gen-name" name="pubpi_gen_name" class="regular-text" />';
    echo '<label for="pubpi-gen-slug">Slug</label>';
    echo '<input type="text" id="pubpi-gen-slug" name="pubpi_gen_slug" class="regular-text" />';
    echo '<label for="pubpi-gen-type">Type</label>';
    echo '<select id="pubpi-gen-type" name="pubpi_gen_type" class="pubpi-set-select">';
    echo '<option value="php_include">PHP include</option>';
    echo '<option value="scss_import">SCSS import</option>';
    echo '<option value="js_register">JS register</option>';
    echo '<option value="js_enqueue">JS enqueue</option>';
    echo '</select>';
    echo '<label for="pubpi-gen-dest">Destination</label>';
    echo '<select id="pubpi-gen-dest" name="pubpi_gen_destination" class="pubpi-set-select">';
    echo '<option value="theme">Thème actif</option>';
    echo '<option value="mu-plugins">MU-Plugins</option>';
    echo '<option value="plugins">Plugins</option>';
    echo '</select>';
    echo '<label for="pubpi-gen-source">Dossier source (relatif)</label>';
    echo '<input type="text" id="pubpi-gen-source" name="pubpi_gen_source" class="regular-text" placeholder="ex: assets/scss/blocks" />';
    echo '<label for="pubpi-gen-target">Fichier cible (relatif)</label>';
    echo '<input type="text" id="pubpi-gen-target" name="pubpi_gen_target" class="regular-text" placeholder="ex: assets/scss/root.scss ou inc/includes.php" />';
    echo '<label for="pubpi-gen-handle">Handle (JS)</label>';
    echo '<input type="text" id="pubpi-gen-handle" name="pubpi_gen_handle" class="regular-text" placeholder="ex: theme-scripts" />';
    echo '<label for="pubpi-gen-deps">Dépendances (JS, CSV)</label>';
    echo '<input type="text" id="pubpi-gen-deps" name="pubpi_gen_deps" class="regular-text" placeholder="ex: jquery,wp-element" />';
    echo '<label><input type="checkbox" name="pubpi_gen_in_footer" id="pubpi-gen-in-footer" value="1" /> Charger en footer (JS)</label>';
    echo '<input type="hidden" id="pubpi-gen-original" name="pubpi_gen_original_slug" value="" />';
    echo '</div>';
    submit_button('Enregistrer le générateur', 'primary', 'pubpi_save_generator', false);
    echo '</form>';

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
    echo '<table id="pubpi-github-plugins" class="widefat fixed striped"><thead><tr><th class="pubpi-set-col">Add set</th><th>Nom du plugin</th><th>Description</th><th>Catégories</th><th>Repository</th><th>Actions</th></tr></thead><tbody>';

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
        echo '<td class="pubpi-set-cell"><label class="pubpi-set-option"><input type="checkbox" class="pubpi-set-item" data-type="github_plugin" data-repo="' . esc_attr($repo) . '" data-name="' . esc_attr($plugin_name) . '" data-main-file="' . esc_attr($main_file) . '"> </label></td>';
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
    echo '<table id="pubpi-github-themes" class="widefat fixed striped"><thead><tr><th class="pubpi-set-col">Add set</th><th>Nom du thème</th><th>Catégories</th><th>Repository</th><th>Action</th></tr></thead><tbody>';

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

        echo '<td class="pubpi-set-cell"><label class="pubpi-set-option"><input type="checkbox" class="pubpi-set-item" data-type="github_theme" data-repo="' . esc_attr($repo) . '" data-name="' . esc_attr($theme_name) . '"> </label></td>';
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
        echo '<table id="' . esc_attr($table_id) . '" class="widefat fixed striped"><thead><tr><th class="pubpi-set-col">Add set</th><th>Preview</th><th>Nom</th><th>Description</th><th>Catégories</th><th>Repository</th><th>Actions</th></tr></thead><tbody>';

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
                $target_id = 'pubpi-pattern-target-' . sanitize_title($manifest_key . '-' . $pattern_slug);
                $custom_id = 'pubpi-pattern-custom-' . sanitize_title($manifest_key . '-' . $pattern_slug);
                $custom_wrap_id = 'pubpi-pattern-custom-wrap-' . sanitize_title($manifest_key . '-' . $pattern_slug);
                $advanced_wrap_id = 'pubpi-manifest-advanced-' . sanitize_title($manifest_key . '-' . $pattern_slug);

                echo '<tr data-categories="' . esc_attr($row_categories_attr) . '">';
                echo '<td class="pubpi-set-cell">';
                echo '<label class="pubpi-set-option"><input type="checkbox" class="pubpi-set-item" data-type="manifest_pattern" data-repo="' . esc_attr($repo) . '" data-name="' . esc_attr($source_name) . '" data-branch="' . esc_attr($branch) . '" data-manifest-path="' . esc_attr($manifest_path) . '" data-pattern="' . esc_attr($pattern_slug) . '" data-target-select="' . esc_attr($target_id) . '" data-custom-input="pubpi_manifest_custom_path_' . esc_attr($pattern_slug) . '"> </label>';
                echo '</td>';
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
                echo '<div class="pubpi-actions-wrapper">';
                echo '<form method="post" class="pubpi-manifest-install-form" style="display:inline;">';
                echo '<input type="hidden" name="pubpi_manifest_repo" value="' . esc_attr($repo) . '" />';
                echo '<input type="hidden" name="pubpi_manifest_name" value="' . esc_attr($source_name) . '" />';
                echo '<input type="hidden" name="pubpi_manifest_branch" value="' . esc_attr($branch) . '" />';
                echo '<input type="hidden" name="pubpi_manifest_path" value="' . esc_attr($manifest_path) . '" />';
                echo '<input type="hidden" name="pubpi_manifest_pattern" value="' . esc_attr($pattern_slug) . '" />';
                echo '<input type="hidden" name="pubpi_manifest_table" value="' . esc_attr($manifest_key) . '" />';
                echo '<div class="pubpi-actions-target">';
                echo '<label for="' . esc_attr($target_id) . '">Destination globale</label>';
                echo '<select id="' . esc_attr($target_id) . '" name="pubpi_manifest_target" class="pubpi-pattern-target-select pubpi-manifest-target-select" data-default="" data-custom-wrap="#' . esc_attr($custom_wrap_id) . '" data-advanced="#' . esc_attr($advanced_wrap_id) . '">';
                echo '<option value="">Défaut (manifest)</option>';
                echo '<option value="theme">Thème actif</option>';
                echo '<option value="mu-plugins">MU-Plugins</option>';
                echo '<option value="plugins">Plugins</option>';
                echo '</select>';
                echo '</div>';
                echo '<div id="' . esc_attr($advanced_wrap_id) . '" class="pubpi-manifest-advanced" style="display:none;">';
                echo '<div id="' . esc_attr($custom_wrap_id) . '" class="pubpi-set-custom-field" style="display:none;">';
                echo '<input type="text" id="pubpi_manifest_custom_path_' . esc_attr($pattern_slug) . '" name="pubpi_manifest_custom_path" class="regular-text pubpi-pattern-custom pubpi-manifest-custom-path" placeholder="Chemin personnalisé" />';
                echo '</div>';
                echo '</div>';
                submit_button('Installer', 'secondary small', 'pubpi_install_manifest_pattern', false);
                echo '</form>';
                echo '</div>';
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
    echo '<table id="pubpi-github-features" class="widefat fixed striped"><thead><tr><th class="pubpi-set-col">Add set</th><th>Nom</th><th>Description</th><th>Catégories</th><th>Repository</th><th>Actions</th></tr></thead><tbody>';
    foreach ($github_features as $repo => $feature_data) {
        $feature_name = $feature_data['name'];
        $feature_description = $feature_data['description'] ?? '';
        $feature_file = $feature_data['file'] ?? '';
        $feature_categories = pubpi_get_item_categories($feature_data);
        $feature_branch = $feature_data['branch'] ?? 'main';
        $slug = basename($repo);
        $category_slugs = array_map('pubpi_category_slug', $feature_categories);
        $row_categories_attr = empty($category_slugs) ? '' : implode(' ', $category_slugs);
        $feature_target_id = 'pubpi-feature-target-' . sanitize_title($feature_name . '-' . $repo);

        echo '<tr data-categories="' . esc_attr($row_categories_attr) . '">';
        echo '<td class="pubpi-set-cell">';
        echo '<label class="pubpi-set-option"><input type="checkbox" class="pubpi-set-item" data-type="github_feature" data-repo="' . esc_attr($repo) . '" data-name="' . esc_attr($feature_name) . '" data-file="' . esc_attr($feature_file) . '" data-branch="' . esc_attr($feature_branch) . '" data-target-select="' . esc_attr($feature_target_id) . '"> </label>';
        echo '</td>';
        echo '<td><strong>' . esc_html($feature_name) . '</strong></td>';
        echo '<td>' . (!empty($feature_description) ? esc_html($feature_description) : '&mdash;') . '</td>';
        echo '<td>' . (!empty($feature_categories) ? esc_html(implode(', ', $feature_categories)) : '&mdash;') . '</td>';
        echo '<td><code>' . esc_html($repo) . '</code></td>';
        echo '<td>';
        echo '<div class="pubpi-set-controls">';
        echo '<select id="' . esc_attr($feature_target_id) . '" class="pubpi-feature-target-select" data-default="theme" data-custom-wrap="">';
        echo '<option value="theme">Thème</option>';
        echo '<option value="mu">MU-Plugins</option>';
        echo '</select>';
        echo '</div>';
        echo '<div class="pubpi-actions-wrapper">';
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
.pubpi-set-col { width: 30px; text-align: center; }
.pubpi-set-cell { width: 30px; text-align: center; white-space: nowrap; }
.pubpi-set-cell .pubpi-set-option { display: flex; flex-direction: column; align-items: center; gap: 2px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: #2271b1; }
.pubpi-set-cell .pubpi-set-option input { margin: 0; }
.pubpi-sets-section { display: grid; gap: 24px; margin-top: 16px; }
.pubpi-sets-table-wrapper { overflow-x: auto; }
.pubpi-sets-load { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
.pubpi-sets-load label { font-weight: 600; }
.pubpi-generators-load { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin: 16px 0; }
.pubpi-generators-load label { font-weight: 600; }
.pubpi-set-form { display: grid; gap: 18px; margin-top: 8px; padding: 16px; border: 1px solid #dcdcde; background: #f7f7f7; border-radius: 4px; }
.pubpi-set-fields { display: grid; gap: 12px; max-width: 420px; }
.pubpi-set-fields label { font-weight: 600; }
.pubpi-set-note { margin: 0; }
.pubpi-set-form-title { margin: 0; font-size: 1.2em; }
.pubpi-clean-load { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin: 16px 0; }
.pubpi-clean-load label { font-weight: 600; }
</style>';
    echo '<script type="text/javascript">';
    echo <<<'JS'
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

        function toggleCustomField(select) {
            if (!select) {
                return;
            }
            var wrapSelector = select.getAttribute("data-custom-wrap");
            if (wrapSelector) {
                var wrap = document.querySelector(wrapSelector);
                if (wrap) {
                    wrap.style.display = select.value === "" ? "none" : "block";
                }
            }
            var advSelector = select.getAttribute("data-advanced");
            if (advSelector) {
                var adv = document.querySelector(advSelector);
                if (adv) {
                    adv.style.display = select.value === "" ? "none" : "block";
                }
            }
        }

        document.querySelectorAll(".pubpi-pattern-target-select, .pubpi-feature-target-select, .pubpi-manifest-target-select").forEach(function(select) {
            toggleCustomField(select);
            select.addEventListener("change", function() {
                toggleCustomField(select);
            });
        });

        var savedSetsData = document.getElementById("pubpi-saved-sets-data");
        var savedSets = savedSetsData ? JSON.parse(savedSetsData.textContent || "[]") : [];
        var setForm = document.getElementById("pubpi-set-form");
        var payloadInput = setForm ? setForm.querySelector("input[name='pubpi_set_payload']") : null;
        var loadButton = document.getElementById("pubpi-load-set-button");
        var loadSelect = document.getElementById("pubpi-load-set-select");
        var savedGeneratorsData = document.getElementById("pubpi-saved-generators-data");
        var savedGenerators = savedGeneratorsData ? JSON.parse(savedGeneratorsData.textContent || "[]") : [];
        var generatorSelect = document.getElementById("pubpi-load-generator-select");
        var generatorLoadBtn = document.getElementById("pubpi-load-generator-button");
        var generatorResetBtn = document.getElementById("pubpi-reset-generator-button");
        var generatorForm = document.getElementById("pubpi-generator-form");
        var savedCleanersData = document.getElementById("pubpi-saved-cleaners-data");
        var savedCleaners = savedCleanersData ? JSON.parse(savedCleanersData.textContent || "[]") : [];
        var cleanerSelect = document.getElementById("pubpi-load-cleaner-select");
        var cleanerLoadBtn = document.getElementById("pubpi-load-cleaner-button");
        var cleanerResetBtn = document.getElementById("pubpi-reset-cleaner-button");
        var cleanerForm = document.getElementById("pubpi-cleaner-form");
        var cleanNameField = document.getElementById("pubpi-clean-name");
        var cleanSlugField = document.getElementById("pubpi-clean-slug");
        var cleanTypeField = document.getElementById("pubpi-clean-type");
        var cleanTargetDirField = document.getElementById("pubpi-clean-target-dir");
        var cleanTargetFileField = document.getElementById("pubpi-clean-target-file");
        var cleanExtensionsField = document.getElementById("pubpi-clean-extensions");
        var cleanRecursiveField = document.getElementById("pubpi-clean-recursive");
        var cleanDeleteField = document.getElementById("pubpi-clean-delete-originals");
        var cleanDirectoriesField = document.getElementById("pubpi-clean-directories");
        var cleanOriginalField = document.getElementById("pubpi-clean-original");
        var genNameField = document.getElementById("pubpi-gen-name");
        var genSlugField = document.getElementById("pubpi-gen-slug");
        var genTypeField = document.getElementById("pubpi-gen-type");
        var genDestField = document.getElementById("pubpi-gen-dest");
        var genSourceField = document.getElementById("pubpi-gen-source");
        var genTargetField = document.getElementById("pubpi-gen-target");
        var genHandleField = document.getElementById("pubpi-gen-handle");
        var genDepsField = document.getElementById("pubpi-gen-deps");
        var genFooterField = document.getElementById("pubpi-gen-in-footer");
        var genOriginalField = document.getElementById("pubpi-gen-original");

        function normalizeGeneratorData(source) {
            if (!source) return null;
            if (typeof source === 'string') {
                try {
                    return JSON.parse(source);
                } catch (e) {
                    return null;
                }
            }
            return source;
        }

        function fillGeneratorForm(generator) {
            if (!generatorForm || !generator) return;
            if (genNameField) genNameField.value = generator.name || generator.slug || "";
            if (genSlugField) genSlugField.value = generator.slug || "";
            if (genTypeField) genTypeField.value = generator.type || "php_include";
            if (genDestField) genDestField.value = generator.destination || "theme";
            if (genSourceField) genSourceField.value = generator.source_dir || "";
            if (genTargetField) genTargetField.value = generator.target_file || "";
            if (genHandleField) genHandleField.value = generator.handle || "";
            if (genDepsField) {
                if (Array.isArray(generator.deps)) {
                    genDepsField.value = generator.deps.join(',');
                } else if (typeof generator.deps === 'string') {
                    genDepsField.value = generator.deps;
                } else {
                    genDepsField.value = '';
                }
            }
            if (genFooterField) genFooterField.checked = !!generator.in_footer;
            if (genOriginalField) genOriginalField.value = generator.slug || "";
        }

        function resetGeneratorForm() {
            if (!generatorForm) return;
            generatorForm.reset();
            if (genDepsField) genDepsField.value = "";
            if (genFooterField) genFooterField.checked = false;
            if (genOriginalField) genOriginalField.value = "";
        }

        function fillCleanerForm(cleaner) {
            if (!cleanerForm || !cleaner) return;
            if (cleanNameField) cleanNameField.value = cleaner.name || cleaner.slug || "";
            if (cleanSlugField) cleanSlugField.value = cleaner.slug || "";
            if (cleanTypeField) cleanTypeField.value = cleaner.type || "remove_comments";
            if (cleanTargetDirField) cleanTargetDirField.value = cleaner.target_dir || "";
            if (cleanTargetFileField) cleanTargetFileField.value = cleaner.target_file || "";
            if (cleanExtensionsField) cleanExtensionsField.value = (cleaner.extensions || []).join(',');
            if (cleanRecursiveField) cleanRecursiveField.checked = !!cleaner.recursive;
            if (cleanDeleteField) cleanDeleteField.checked = !!cleaner.delete_originals;
            if (cleanDirectoriesField) cleanDirectoriesField.value = (cleaner.directories || []).join(',');
            if (cleanOriginalField) cleanOriginalField.value = cleaner.slug || "";
        }

        function resetCleanerForm() {
            if (!cleanerForm) return;
            cleanerForm.reset();
            if (cleanExtensionsField) cleanExtensionsField.value = "";
            if (cleanDirectoriesField) cleanDirectoriesField.value = "";
            if (cleanRecursiveField) cleanRecursiveField.checked = false;
            if (cleanDeleteField) cleanDeleteField.checked = false;
            if (cleanOriginalField) cleanOriginalField.value = "";
        }

        if (generatorLoadBtn && generatorSelect) {
            generatorLoadBtn.addEventListener("click", function() {
                var slug = generatorSelect.value;
                if (!slug) return;
                var option = generatorSelect.options[generatorSelect.selectedIndex];
                var dataAttr = option ? option.getAttribute("data-generator") : null;
                var parsed = normalizeGeneratorData(dataAttr);
                if (!parsed) {
                    parsed = savedGenerators.find(function(gen) { return gen.slug === slug; }) || null;
                }
                if (parsed) {
                    fillGeneratorForm(parsed);
                }
            });
            generatorSelect.addEventListener("change", function() {
                var slug = generatorSelect.value;
                if (!slug) {
                    resetGeneratorForm();
                    return;
                }
                var option = generatorSelect.options[generatorSelect.selectedIndex];
                var dataAttr = option ? option.getAttribute("data-generator") : null;
                var parsed = normalizeGeneratorData(dataAttr);
                if (!parsed) {
                    parsed = savedGenerators.find(function(gen) { return gen.slug === slug; }) || null;
                }
                if (parsed) {
                    fillGeneratorForm(parsed);
                }
            });
        }

        if (generatorResetBtn) {
            generatorResetBtn.addEventListener("click", function() {
                if (generatorSelect) {
                    generatorSelect.value = "";
                }
                resetGeneratorForm();
            });
        }

        if (cleanerLoadBtn && cleanerSelect) {
            cleanerLoadBtn.addEventListener("click", function() {
                var slug = cleanerSelect.value;
                if (!slug) return;
                var option = cleanerSelect.options[cleanerSelect.selectedIndex];
                var dataAttr = option ? option.getAttribute("data-cleaner") : null;
                var parsed = normalizeGeneratorData(dataAttr);
                if (!parsed) {
                    parsed = savedCleaners.find(function(c) { return c.slug === slug; }) || null;
                }
                if (parsed) {
                    fillCleanerForm(parsed);
                }
            });
            cleanerSelect.addEventListener("change", function() {
                var slug = cleanerSelect.value;
                if (!slug) {
                    resetCleanerForm();
                    return;
                }
                var option = cleanerSelect.options[cleanerSelect.selectedIndex];
                var dataAttr = option ? option.getAttribute("data-cleaner") : null;
                var parsed = normalizeGeneratorData(dataAttr);
                if (!parsed) {
                    parsed = savedCleaners.find(function(c) { return c.slug === slug; }) || null;
                }
                if (parsed) {
                    fillCleanerForm(parsed);
                }
            });
        }

        if (cleanerResetBtn) {
            cleanerResetBtn.addEventListener("click", function() {
                if (cleanerSelect) {
                    cleanerSelect.value = "";
                }
                resetCleanerForm();
            });
        }

        function buildSetPayload() {
            if (!setForm || !payloadInput) {
                return;
            }
            var slugField = document.getElementById("pubpi-set-slug");
            var nameField = document.getElementById("pubpi-set-name");
            var manifestDefaultField = document.getElementById("pubpi-set-manifest-default");
            var items = [];
            var selected = document.querySelectorAll(".pubpi-set-item:checked");
            selected.forEach(function(input) {
                var item = { type: input.getAttribute("data-type") };
                if (item.type === "wp_plugin") {
                    item.plugin_path = input.getAttribute("data-plugin-path");
                    item.plugin_name = input.getAttribute("data-plugin-name");
                } else if (item.type === "github_plugin") {
                    item.repo = input.getAttribute("data-repo");
                    item.name = input.getAttribute("data-name");
                    item.main_file = input.getAttribute("data-main-file") || "";
                } else if (item.type === "github_theme") {
                    item.repo = input.getAttribute("data-repo");
                    item.name = input.getAttribute("data-name");
                } else if (item.type === "github_feature") {
                    item.repo = input.getAttribute("data-repo");
                    item.name = input.getAttribute("data-name");
                    item.file = input.getAttribute("data-file");
                    item.branch = input.getAttribute("data-branch") || "main";
                    var targetSelectId = input.getAttribute("data-target-select");
                    if (targetSelectId) {
                        var targetSelect = document.getElementById(targetSelectId);
                        if (targetSelect) {
                            item.target = targetSelect.value;
                        }
                    }
                } else if (item.type === "manifest_pattern") {
                    item.repo = input.getAttribute("data-repo");
                    item.name = input.getAttribute("data-name");
                    item.branch = input.getAttribute("data-branch") || "main";
                    item.manifest_path = input.getAttribute("data-manifest-path");
                    item.pattern = input.getAttribute("data-pattern");
                    var patternTargetId = input.getAttribute("data-target-select");
                    var patternCustomId = input.getAttribute("data-custom-input");
                    if (patternTargetId) {
                        var targetSelect = document.getElementById(patternTargetId);
                        if (targetSelect) {
                            item.target = targetSelect.value;
                        }
                    }
                    if (patternCustomId) {
                        var customInput = document.getElementById(patternCustomId);
                        if (customInput) {
                            item.custom_path = customInput.value;
                        }
                    }
                } else if (item.type === "file_generator") {
                    item.generator_slug = input.getAttribute("data-generator-slug");
                } else if (item.type === "clean_action") {
                    item.clean_slug = input.getAttribute("data-clean-slug");
                }
                items.push(item);
            });

            var payload = {
                slug: slugField ? slugField.value : "",
                name: nameField ? nameField.value : "",
                manifest_default: manifestDefaultField ? manifestDefaultField.value : "",
                items: items
            };
            payloadInput.value = JSON.stringify(payload);
        }

        if (setForm && payloadInput) {
            setForm.addEventListener("submit", function() {
                buildSetPayload();
            });
        }

        function populateSetForm(setData) {
            var slugField = document.getElementById("pubpi-set-slug");
            var nameField = document.getElementById("pubpi-set-name");
            var manifestDefaultField = document.getElementById("pubpi-set-manifest-default");
            if (slugField) {
                slugField.value = setData.slug || "";
            }
            if (nameField) {
                nameField.value = setData.name || "";
            }
            if (manifestDefaultField) {
                manifestDefaultField.value = (setData.meta && setData.meta.manifest_default) ? setData.meta.manifest_default : "";
            }
            document.querySelectorAll(".pubpi-set-item").forEach(function(input) {
                input.checked = false;
            });
            document.querySelectorAll(".pubpi-feature-target-select, .pubpi-pattern-target-select, .pubpi-manifest-target-select").forEach(function(select) {
                var def = select.getAttribute("data-default");
                if (typeof def === "string") {
                    select.value = def;
                } else {
                    select.value = "";
                }
                toggleCustomField(select);
            });
            document.querySelectorAll(".pubpi-pattern-custom").forEach(function(input) {
                input.value = "";
            });
            (setData.items || []).forEach(function(item) {
                var selector = ".pubpi-set-item[data-type=\"" + item.type + "\"]";
                if (item.type === "wp_plugin") {
                    selector += "[data-plugin-path=\"" + item.plugin_path + "\"]";
                } else if (item.type === "github_plugin") {
                    selector += "[data-repo=\"" + item.repo + "\"]";
                } else if (item.type === "github_theme") {
                    selector += "[data-repo=\"" + item.repo + "\"]";
                } else if (item.type === "github_feature") {
                    selector += "[data-repo=\"" + item.repo + "\"]";
                } else if (item.type === "manifest_pattern") {
                    selector += "[data-repo=\"" + item.repo + "\"][data-pattern=\"" + item.pattern + "\"]";
                } else if (item.type === "file_generator") {
                    selector += "[data-generator-slug=\"" + item.generator_slug + "\"]";
                } else if (item.type === "clean_action") {
                    selector += "[data-clean-slug=\"" + item.clean_slug + "\"]";
                }
                var input = document.querySelector(selector);
                if (input) {
                    input.checked = true;
                    if (item.type === "github_feature") {
                        var targetSelectId = input.getAttribute("data-target-select");
                        if (targetSelectId) {
                            var targetSelect = document.getElementById(targetSelectId);
                            if (targetSelect && item.target) {
                                targetSelect.value = item.target;
                            }
                        }
                    }
                    if (item.type === "manifest_pattern") {
                        var patternTargetId = input.getAttribute("data-target-select");
                        if (patternTargetId) {
                            var targetSelect = document.getElementById(patternTargetId);
                            if (targetSelect && (item.target || item.target === "")) {
                                targetSelect.value = item.target;
                            }
                        }
                        var patternCustomId = input.getAttribute("data-custom-input");
                        if (patternCustomId) {
                            var customInput = document.getElementById(patternCustomId);
                            if (customInput && item.custom_path) {
                                customInput.value = item.custom_path;
                            }
                        }
                    }
                }
            });
        }

        if (loadButton && loadSelect) {
            loadButton.addEventListener("click", function(event) {
                event.preventDefault();
                var slug = loadSelect.value;
                if (!slug) {
                    return;
                }
                var setData = savedSets.find(function(s) { return s.slug === slug; });
                if (!setData) {
                    return;
                }
                populateSetForm(setData);
            });
        }
    });
})();
JS;
    echo '</script>';
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

    // Generators actions
    if (isset($_POST['pubpi_save_generator'])) {
        $gen = [
            'name' => sanitize_text_field($_POST['pubpi_gen_name'] ?? ''),
            'slug' => sanitize_title($_POST['pubpi_gen_slug'] ?? ''),
            'type' => sanitize_text_field($_POST['pubpi_gen_type'] ?? ''),
            'destination' => sanitize_text_field($_POST['pubpi_gen_destination'] ?? ''),
            'source_dir' => sanitize_text_field($_POST['pubpi_gen_source'] ?? ''),
            'target_file' => sanitize_text_field($_POST['pubpi_gen_target'] ?? ''),
            'handle' => sanitize_text_field($_POST['pubpi_gen_handle'] ?? ''),
            'deps' => array_filter(array_map('trim', explode(',', sanitize_text_field($_POST['pubpi_gen_deps'] ?? '')))),
            'in_footer' => !empty($_POST['pubpi_gen_in_footer']) ? true : false,
        ];
        $save = pubpi_save_generator_config($gen);
        if (is_wp_error($save)) {
            echo '<div class="error notice"><p>❌ ' . esc_html($save->get_error_message()) . '</p></div>';
        } else {
            echo '<div class="updated notice"><p>✅ Générateur enregistré.</p></div>';
        }
    }
    if (isset($_POST['pubpi_delete_generator'])) {
        $slug = sanitize_title($_POST['pubpi_generator_slug'] ?? '');
        $deleted = pubpi_delete_generator_config($slug);
        if ($deleted) {
            echo '<div class="updated notice"><p>🗑️ Générateur supprimé.</p></div>';
        } else {
            echo '<div class="error notice"><p>❌ Impossible de supprimer le générateur.</p></div>';
        }
    }
    if (isset($_POST['pubpi_run_generator'])) {
        $slug = sanitize_title($_POST['pubpi_generator_slug'] ?? '');
        $result = pubpi_run_generator_by_slug($slug);
        if (is_wp_error($result)) {
            echo '<div class="error notice"><p>❌ ' . esc_html($result->get_error_message()) . '</p></div>';
        } else {
            echo '<div class="updated notice"><p>✅ Génération effectuée : ' . esc_html($result) . ' lignes ajoutées.</p></div>';
        }
    }

    // Clean actions
    if (isset($_POST['pubpi_save_cleaner'])) {
        $clean = [
            'name' => sanitize_text_field($_POST['pubpi_clean_name'] ?? ''),
            'slug' => sanitize_title($_POST['pubpi_clean_slug'] ?? ''),
            'type' => sanitize_text_field($_POST['pubpi_clean_type'] ?? ''),
            'target_dir' => sanitize_text_field($_POST['pubpi_clean_target_dir'] ?? ''),
            'target_file' => sanitize_text_field($_POST['pubpi_clean_target_file'] ?? ''),
            'extensions' => sanitize_text_field($_POST['pubpi_clean_extensions'] ?? ''),
            'directories' => sanitize_text_field($_POST['pubpi_clean_directories'] ?? ''),
            'recursive' => !empty($_POST['pubpi_clean_recursive']),
            'delete_originals' => !empty($_POST['pubpi_clean_delete_originals']),
        ];
        $save = pubpi_save_cleaner_config($clean);
        if (is_wp_error($save)) {
            echo '<div class="error notice"><p>❌ ' . esc_html($save->get_error_message()) . '</p></div>';
        } else {
            echo '<div class="updated notice"><p>✅ Action Clean enregistrée.</p></div>';
        }
    }
    if (isset($_POST['pubpi_delete_cleaner'])) {
        $slug = sanitize_title($_POST['pubpi_cleaner_slug'] ?? '');
        $deleted = pubpi_delete_cleaner_config($slug);
        if ($deleted) {
            echo '<div class="updated notice"><p>🗑️ Action Clean supprimée.</p></div>';
        } else {
            echo '<div class="error notice"><p>❌ Impossible de supprimer l’action Clean.</p></div>';
        }
    }
    if (isset($_POST['pubpi_run_cleaner'])) {
        $slug = sanitize_title($_POST['pubpi_cleaner_slug'] ?? '');
        $result = pubpi_run_cleaner_by_slug($slug);
        if (is_wp_error($result)) {
            echo '<div class="error notice"><p>❌ ' . esc_html($result->get_error_message()) . '</p></div>';
        } else {
            $modified = count($result['modified']);
            $deleted = count($result['deleted']);
            $errors = count($result['errors']);
            $warnings = count($result['warnings']);
            echo '<div class="updated notice"><p>✅ Action Clean exécutée. Modifiés : ' . esc_html($modified) . ', supprimés : ' . esc_html($deleted) . ', avertissements : ' . esc_html($warnings) . ', erreurs : ' . esc_html($errors) . '.</p></div>';
        }
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

    if (isset($_POST['pubpi_save_set'])) {
        $payload_raw = isset($_POST['pubpi_set_payload']) ? wp_unslash($_POST['pubpi_set_payload']) : '';
        $payload = json_decode($payload_raw, true);
        if (!is_array($payload)) {
            echo '<div class="error notice"><p>❌ Données de set invalides.</p></div>';
        } else {
            $manifest_default = isset($_POST['pubpi_set_manifest_default']) ? sanitize_text_field($_POST['pubpi_set_manifest_default']) : '';
            if (!isset($payload['meta']) || !is_array($payload['meta'])) {
                $payload['meta'] = [];
            }
            $payload['meta']['manifest_default'] = $manifest_default;
            $result = pubpi_save_set_data($payload);
            if (is_wp_error($result)) {
                echo '<div class="error notice"><p>❌ ' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="updated notice"><p>✅ Set enregistré.</p></div>';
            }
        }
    }

    if (isset($_POST['pubpi_install_set'])) {
        $set_slug = sanitize_title($_POST['pubpi_set_slug'] ?? '');
        if ($set_slug === '') {
            echo '<div class="error notice"><p>❌ Set introuvable.</p></div>';
        } else {
            $install_result = pubpi_install_set_by_slug($set_slug, ['context' => 'admin']);
            if (is_wp_error($install_result)) {
                echo '<div class="error notice"><p>❌ ' . esc_html($install_result->get_error_message()) . '</p></div>';
            } else {
                $set_info = $install_result['set'];
                $count = (int) $install_result['items_installed'];
                $default_target = $install_result['manifest_default'];
                if ($default_target !== '') {
                    $target_label = pubpi_install_target_label($default_target, '');
                } else {
                    $target_label = 'destinations définies dans chaque manifest';
                }
                echo '<div class="updated notice"><p>✅ Set "' . esc_html($set_info['name']) . '" : ' . esc_html($count) . ' élément(s) traités (' . esc_html($target_label) . ').</p></div>';
            }
        }
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

function pubpi_install_set_by_slug($slug, $args = []) {
    $context = isset($args['context']) ? $args['context'] : 'admin';
    $override_target = isset($args['manifest_target']) ? $args['manifest_target'] : '';

    $set = pubpi_load_set_data($slug);
    if (is_wp_error($set)) {
        return $set;
    }

    $default_target = $set['meta']['manifest_default'] ?? '';
    $override_target = pubpi_normalize_set_manifest_target($override_target);
    if ($override_target !== '') {
        $default_target = $override_target;
    }

    $items = pubpi_apply_manifest_default_to_items($set['items'], $default_target);
    $items_count = count($items);

    $buffered_output = '';
    if ($context === 'rest') {
        ob_start();
    }

    $result = pubpi_install_set_items($items);

    if ($context === 'rest') {
        $buffered_output = trim((string) ob_get_clean());
    }

    if (is_wp_error($result)) {
        if ($context === 'rest' && $buffered_output !== '') {
            $data = (array) $result->get_error_data();
            $data['messages'] = $buffered_output;
            $result->add_data($data);
        }
        return $result;
    }

    if ($context === 'rest') {
        return [
            'slug' => $set['slug'],
            'name' => $set['name'],
            'items_installed' => $items_count,
            'manifest_default' => $default_target,
            'messages' => $buffered_output,
        ];
    }

    return [
        'set' => $set,
        'items_installed' => $items_count,
        'manifest_default' => $default_target,
    ];
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

function pubpi_get_sets_directory() {
    return trailingslashit(__DIR__ . '/config/sets');
}

function pubpi_ensure_sets_directory() {
    $dir = pubpi_get_sets_directory();
    if (!is_dir($dir)) {
        if (!wp_mkdir_p($dir)) {
            return new WP_Error('pubpi_sets_dir', 'Impossible de créer le dossier des sets.');
        }
    }
    return $dir;
}

function pubpi_list_saved_sets() {
    $dir = pubpi_ensure_sets_directory();
    if (is_wp_error($dir)) {
        return [];
    }

    $sets = [];
    $files = glob(trailingslashit($dir) . '*.json');
    if (!$files) {
        return [];
    }

    foreach ($files as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            continue;
        }
        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            continue;
        }
        $slug = basename($file, '.json');
        if (empty($decoded['slug'])) {
            $decoded['slug'] = $slug;
        }
        $items = pubpi_normalize_set_items($decoded['items'] ?? []);
        $meta = is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [];
        $meta['manifest_default'] = pubpi_normalize_set_manifest_target($meta['manifest_default'] ?? '');
        $sets[$decoded['slug']] = [
            'slug' => $decoded['slug'],
            'name' => $decoded['name'] ?? $decoded['slug'],
            'file' => basename($file),
            'items' => $items,
            'meta' => $meta,
        ];
    }

    ksort($sets);
    return array_values($sets);
}

function pubpi_normalize_set_manifest_target($target) {
    $allowed = ['theme', 'mu-plugins', 'plugins'];
    $target = sanitize_text_field($target);
    if ($target === '' || in_array($target, $allowed, true)) {
        return $target;
    }
    return '';
}

function pubpi_normalize_set_items($items) {
    if (!is_array($items)) {
        return [];
    }

    $normalized = [];

    foreach ($items as $item) {
        if (!is_array($item) || empty($item['type'])) {
            continue;
        }

        $type = sanitize_key($item['type']);
        $entry = ['type' => $type];

        switch ($type) {
            case 'wp_plugin':
                $entry['plugin_path'] = sanitize_text_field($item['plugin_path'] ?? '');
                $entry['plugin_name'] = sanitize_text_field($item['plugin_name'] ?? '');
                if ($entry['plugin_path'] === '' || $entry['plugin_name'] === '') {
                    continue 2;
                }
                break;
            case 'github_plugin':
                $entry['repo'] = sanitize_text_field($item['repo'] ?? '');
                $entry['name'] = sanitize_text_field($item['name'] ?? '');
                $entry['main_file'] = sanitize_text_field($item['main_file'] ?? '');
                if ($entry['repo'] === '' || $entry['name'] === '') {
                    continue 2;
                }
                break;
            case 'github_theme':
                $entry['repo'] = sanitize_text_field($item['repo'] ?? '');
                $entry['name'] = sanitize_text_field($item['name'] ?? '');
                if ($entry['repo'] === '' || $entry['name'] === '') {
                    continue 2;
                }
                break;
            case 'github_feature':
                $entry['repo'] = sanitize_text_field($item['repo'] ?? '');
                $entry['name'] = sanitize_text_field($item['name'] ?? '');
                $entry['file'] = sanitize_text_field($item['file'] ?? '');
                $entry['branch'] = sanitize_text_field($item['branch'] ?? 'main');
                $entry['target'] = sanitize_text_field($item['target'] ?? '');
                if ($entry['repo'] === '' || $entry['name'] === '' || $entry['file'] === '') {
                    continue 2;
                }
                break;
            case 'manifest_pattern':
                $entry['repo'] = sanitize_text_field($item['repo'] ?? '');
                $entry['name'] = sanitize_text_field($item['name'] ?? '');
                $entry['branch'] = sanitize_text_field($item['branch'] ?? 'main');
                $entry['manifest_path'] = sanitize_text_field($item['manifest_path'] ?? '');
                $entry['pattern'] = sanitize_text_field($item['pattern'] ?? '');
                $entry['target'] = sanitize_text_field($item['target'] ?? '');
                $entry['custom_path'] = sanitize_text_field($item['custom_path'] ?? '');
                if ($entry['repo'] === '' || $entry['name'] === '' || $entry['manifest_path'] === '' || $entry['pattern'] === '') {
                    continue 2;
                }
                break;
            case 'file_generator':
                $entry['generator_slug'] = sanitize_title($item['generator_slug'] ?? '');
                if ($entry['generator_slug'] === '') {
                    continue 2;
                }
                break;
            case 'clean_action':
                $entry['clean_slug'] = sanitize_title($item['clean_slug'] ?? '');
                if ($entry['clean_slug'] === '') {
                    continue 2;
                }
                break;
            default:
                continue 2;
        }

        $normalized[] = $entry;
    }

    return $normalized;
}

function pubpi_apply_manifest_default_to_items($items, $default_target) {
    $default_target = pubpi_normalize_set_manifest_target($default_target);
    if ($default_target === '' || empty($items)) {
        return $items;
    }

    foreach ($items as &$item) {
        if (($item['type'] ?? '') === 'manifest_pattern' && ($item['target'] ?? '') === '') {
            $item['target'] = $default_target;
        }
    }
    unset($item);

    return $items;
}

function pubpi_load_set_data($slug) {
    $dir = pubpi_ensure_sets_directory();
    if (is_wp_error($dir)) {
        return $dir;
    }
    $path = trailingslashit($dir) . $slug . '.json';
    if (!file_exists($path)) {
        return new WP_Error('pubpi_set_missing', 'Set introuvable.');
    }
    $contents = file_get_contents($path);
    if ($contents === false) {
        return new WP_Error('pubpi_set_read', 'Impossible de lire le set.');
    }
    $decoded = json_decode($contents, true);
    if (!is_array($decoded)) {
        return new WP_Error('pubpi_set_json', 'Set JSON invalide.');
    }
    $decoded['slug'] = $decoded['slug'] ?? $slug;
    $decoded['items'] = pubpi_normalize_set_items($decoded['items'] ?? []);
    $decoded['meta'] = is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [];
    $decoded['meta']['manifest_default'] = pubpi_normalize_set_manifest_target($decoded['meta']['manifest_default'] ?? '');
    return $decoded;
}

function pubpi_save_set_data($data) {
    $dir = pubpi_ensure_sets_directory();
    if (is_wp_error($dir)) {
        return $dir;
    }

    $slug = sanitize_title($data['slug'] ?? '');
    if ($slug === '') {
        return new WP_Error('pubpi_set_slug', 'Le slug du set est obligatoire.');
    }

    $name = sanitize_text_field($data['name'] ?? $slug);
    $items = pubpi_normalize_set_items($data['items'] ?? []);

    $existing = pubpi_load_set_data($slug);
    $created_at = (!is_wp_error($existing) && isset($existing['meta']['created_at'])) ? $existing['meta']['created_at'] : current_time('mysql');

    $manifest_default = pubpi_normalize_set_manifest_target(
        $data['meta']['manifest_default'] ?? $data['manifest_default'] ?? ''
    );

    $payload = [
        'slug' => $slug,
        'name' => $name,
        'items' => $items,
        'meta' => [
            'created_at' => $created_at,
            'updated_at' => current_time('mysql'),
            'manifest_default' => $manifest_default,
        ],
    ];

    $path = trailingslashit($dir) . $slug . '.json';
    $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return new WP_Error('pubpi_set_encode', 'Impossible d\'encoder le set.');
    }

    if (file_put_contents($path, $json) === false) {
        return new WP_Error('pubpi_set_write', 'Impossible d\'écrire le set.');
    }

    return $payload;
}

function pubpi_install_set_items($items) {
    if (!is_array($items)) {
        return new WP_Error('pubpi_set_items', 'Liste d\'éléments invalide.');
    }

    $generators = [];
    foreach ($items as $item) {
        if (!is_array($item) || empty($item['type'])) {
            continue;
        }
        switch ($item['type']) {
            case 'wp_plugin':
                if (!empty($item['plugin_path']) && !empty($item['plugin_name'])) {
                    pubpi_install_and_activate_plugin($item['plugin_path'], $item['plugin_name']);
                }
                break;
            case 'github_plugin':
                if (!empty($item['repo']) && !empty($item['name'])) {
                    $main_file = $item['main_file'] ?? '';
                    pubpi_install_from_github($item['repo'], $item['name'], 'plugin', $main_file, false);
                }
                break;
            case 'github_theme':
                if (!empty($item['repo']) && !empty($item['name'])) {
                    pubpi_install_from_github($item['repo'], $item['name'], 'theme', '', false);
                }
                break;
            case 'github_feature':
                if (!empty($item['repo']) && !empty($item['name']) && !empty($item['file'])) {
                    $target = $item['target'] ?? 'theme';
                    $branch = $item['branch'] ?? 'main';
                    pubpi_install_feature_from_github($item['repo'], $item['name'], $item['file'], $target, $branch);
                }
                break;
            case 'manifest_pattern':
                if (!empty($item['repo']) && !empty($item['name']) && !empty($item['manifest_path']) && !empty($item['pattern'])) {
                    $branch = $item['branch'] ?? 'main';
                    $target = $item['target'] ?? 'theme';
                    $custom_path = $item['custom_path'] ?? '';
                    pubpi_install_manifest_pattern($item['repo'], $item['name'], $branch, $item['manifest_path'], $item['pattern'], $target, $custom_path);
                }
                break;
            case 'file_generator':
                if (!empty($item['generator_slug'])) {
                    $generators[] = $item['generator_slug'];
                }
                break;
            case 'clean_action':
                if (!empty($item['clean_slug'])) {
                    $cleaners[] = $item['clean_slug'];
                }
                break;
        }
    }

    // Run generators at the end
    foreach (array_unique($generators) as $gen_slug) {
        pubpi_run_generator_by_slug($gen_slug);
    }

    // Run cleaners after generators
    foreach (array_unique($cleaners ?? []) as $clean_slug) {
        $result = pubpi_run_cleaner_by_slug($clean_slug);
        if (is_wp_error($result)) {
            return $result;
        }
    }

    return true;
}