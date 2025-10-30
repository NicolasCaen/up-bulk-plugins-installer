<?php

require_once __DIR__ . '/GeneratorInterface.php';

abstract class PUBPI_BaseGenerator implements PUBPI_GeneratorInterface {
    abstract public function type(): string;
    abstract public function label(): string;
    abstract protected function fileExtensions(): array;
    abstract protected function blockKey(array $cfg): string;
    abstract protected function perFileLines(array $cfg, string $abs, string $rel_from_base, string $target_file, string $base_dir, string $base_uri): array;
    protected function replaceMode(): bool { return false; }
    protected function finalizeLines(array $cfg, array $lines): array { return $lines; }

    public function generate(array $cfg) {
        if (!function_exists('trailingslashit')) return new WP_Error('pubpi_missing_wp', 'Fonctions WP indisponibles.');
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
        // Ensure PHP open tag at top
        $this->ensurePhpOpenTag($target_file);

        $exts = $this->fileExtensions();
        $files = pubpi_scan_files_recursive($source_dir, $exts);
        $lines = [];
        $target_real = realpath($target_file) ?: $target_file;
        foreach ($files as $abs) {
            $abs_real = realpath($abs) ?: $abs;
            if ($abs_real === $target_real) continue;
            $rel_from_base = ltrim(str_replace(trailingslashit($base_dir), '', $abs), '/');
            $lines = array_merge($lines, $this->perFileLines($cfg, $abs, $rel_from_base, $target_file, $base_dir, $base_uri));
        }
        $lines = $this->finalizeLines($cfg, $lines);
        $block_key = $this->blockKey($cfg);
        if ($this->replaceMode()) {
            return pubpi_replace_marker_block($target_file, $block_key, $lines);
        }
        return pubpi_ensure_marker_block($target_file, $block_key, $lines);
    }

    protected function ensurePhpOpenTag(string $target_file): void {
        $target_ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        if ($target_ext !== 'php') return;
        $current = file_get_contents($target_file);
        if ($current === false) return;
        $trimmed = ltrim($current);
        if (strpos($trimmed, "<?php") !== 0) {
            $body = ltrim($current, "\r\n\t ");
            pubpi_write_file_with_fallback($target_file, "<?php\n\n" . $body);
        } elseif (strpos($current, "<?php") !== 0) {
            $normalized = preg_replace('/^\s+/', '', $current);
            pubpi_write_file_with_fallback($target_file, $normalized);
        }
    }

    protected function depsPhp(array $cfg): string {
        $deps = isset($cfg['deps']) ? array_values(array_filter((array)$cfg['deps'], function($d){ return $d !== null && $d !== '';})) : [];
        if (empty($deps)) return '[]';
        $escaped = array_map('esc_js', $deps);
        return "['" . implode("','", $escaped) . "']";
    }

    protected function urlExpr(array $cfg, string $rel_from_base): string {
        $dest = $cfg['destination'] ?? 'theme';
        if ($dest === 'theme') {
            return "get_stylesheet_directory_uri() . '/" . $rel_from_base . "'";
        }
        if ($dest === 'plugins') {
            return "content_url('/plugins/" . $rel_from_base . "')";
        }
        return "content_url('/mu-plugins/" . $rel_from_base . "')";
    }
}
