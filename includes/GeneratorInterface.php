<?php

interface PUBPI_GeneratorInterface {
    public function type(): string; // php_include | scss_import | js_enqueue | js_register
    public function label(): string;
    /**
     * Execute generation according to provided config.
     * @param array $cfg Full JSON config for this generator
     * @return int|WP_Error Number of lines added/updated, or WP_Error
     */
    public function generate(array $cfg);
}
