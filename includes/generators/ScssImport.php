<?php

require_once dirname(__DIR__) . '/BaseGenerator.php';

class PUBPI_ScssImportGenerator extends PUBPI_BaseGenerator {
    public function type(): string { return 'scss_import'; }
    public function label(): string { return 'SCSS Import'; }
    protected function fileExtensions(): array { return ['scss']; }
    protected function blockKey(array $cfg): string { return $this->type(); }

    protected function perFileLines(array $cfg, string $abs, string $rel_from_base, string $target_file, string $base_dir, string $base_uri): array {
        $bn = basename($abs);
        if ($bn === basename($target_file)) return [];
        if (substr($bn, 0, 1) !== '_') return [];
        $rel_to_target = ltrim(str_replace(trailingslashit(dirname($target_file)), '', $abs), '/');
        $rel_to_target = str_replace("\\", '/', $rel_to_target);
        if (substr($rel_to_target, -5) === '.scss') { $rel_to_target = substr($rel_to_target, 0, -5); }
        $rel_segments = explode('/', $rel_to_target);
        $last_segment = array_pop($rel_segments);
        if (substr($last_segment, 0, 1) === '_') { $last_segment = substr($last_segment, 1); }
        $rel_clean = trim(($rel_segments ? implode('/', $rel_segments) . '/' : '') . $last_segment, '/');
        if ($rel_clean === '') return [];
        return ["@import '" . $rel_clean . "';"];    }
}
