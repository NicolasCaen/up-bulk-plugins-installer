<?php

require_once dirname(__DIR__) . '/BaseGenerator.php';

class PUBPI_JsEnqueueGenerator extends PUBPI_BaseGenerator {
    public function type(): string { return 'js_enqueue'; }
    public function label(): string { return 'JS Enqueue'; }
    protected function fileExtensions(): array { return ['js']; }
    protected function replaceMode(): bool { return true; }
    protected function blockKey(array $cfg): string { return sanitize_title($cfg['slug'] ?? $this->type()); }

    protected function perFileLines(array $cfg, string $abs, string $rel_from_base, string $target_file, string $base_dir, string $base_uri): array {
        $handle_base = !empty($cfg['handle']) ? sanitize_title($cfg['handle']) : 'pubpi-script';
        $file_slug = sanitize_title(pathinfo($abs, PATHINFO_FILENAME));
        $handle = $handle_base . '-' . $file_slug;
        $deps_php = $this->depsPhp($cfg);
        $url_expr = $this->urlExpr($cfg, $rel_from_base);
        $in_footer = !empty($cfg['in_footer']) ? 'true' : 'false';
        return ["wp_enqueue_script('{$handle}', " . $url_expr . ", {$deps_php}, null, {$in_footer});"];    }

    protected function finalizeLines(array $cfg, array $lines): array {
        $func_base = sanitize_title($cfg['slug'] ?? 'pubpi-gen');
        $func_base = str_replace('-', '_', $func_base);
        $func_name = $func_base . '_scripts';
        $wrapped = [];
        $wrapped[] = "add_action('wp_enqueue_scripts', '{$func_name}');";
        $wrapped[] = "function {$func_name}() {";
        $wrapped[] = "  //-- Script des block js ---------";
        foreach ($lines as $ln) { $wrapped[] = '  ' . $ln; }
        $wrapped[] = "  //--------------------";
        $wrapped[] = "}";
        return $wrapped;
    }
}
