<?php

require_once dirname(__DIR__) . '/BaseGenerator.php';

class PUBPI_PhpIncludeGenerator extends PUBPI_BaseGenerator {
    public function type(): string { return 'php_include'; }
    public function label(): string { return 'PHP Include'; }
    protected function fileExtensions(): array { return ['php']; }
    protected function blockKey(array $cfg): string { return $this->type(); }

    protected function perFileLines(array $cfg, string $abs, string $rel_from_base, string $target_file, string $base_dir, string $base_uri): array {
        // do not include the target file itself
        $target_real = realpath($target_file) ?: $target_file;
        $abs_real = realpath($abs) ?: $abs;
        if ($abs_real === $target_real) return [];

        $dest = $cfg['destination'] ?? 'theme';
        if ($dest === 'theme') {
            return ["require_once get_stylesheet_directory() . '/" . $rel_from_base . "';"];        }
        if ($dest === 'plugins') {
            return ["require_once WP_PLUGIN_DIR . '/" . $rel_from_base . "';"];        }
        // mu-plugins
        return ["require_once WPMU_PLUGIN_DIR . '/" . $rel_from_base . "';"];    }
}
