<?php

// Format attendu:
// 'user/repo' => [
//     'name' => 'Nom lisible',
//     'description' => 'Description',
//     'categories' => ['Cat1', 'Cat2'],
//     'main_file' => '' // Optionnel: laisser vide pour auto-détection
// ]
return [
    'NicolasCaen/up-plugins-agency'=>[
        'name' => 'Up Plugins Agency',
        'description' => 'Regroupement des plugins développés par l\'agence. dans le gestionnaire de plugins.',
        'categories' => ['Gutenberg', 'plugins'],
        'main_file' => 'up-plugins-agency.php'
    ],
    'NicolasCaen/up-admin-menu'=>[
        'name' => 'Up Admin Menu',
        'description' => 'Simplifier l\'interface admin.',
        'categories' => ['Gutenberg', 'Administration'],
        'main_file' => ''
    ],
    'NicolasCaen/up-inline-icon-format'=>[
        'name' => 'Up Inline Icon Format',
        'description' => 'Plugin permettant de mettre des icônes inline.',
        'categories' => ['Gutenberg', 'icons'],
        'main_file' => 'up-inline-icon-format.php'
    ],
    'NicolasCaen/up-gutenberg-query-filter' => [
        'name' => 'Up Gutenberg Query Filter',
        'description' => 'Plugin permettant de filtrer les boucles Query Loop.',
        'categories' => ['Gutenberg', 'Filtres'],
        'main_file' => ''
    ],
    'NicolasCaen/up-binding-lorem' => [
        'name' => 'Up Binding Lorem',
        'description' => 'Ajoute le block binding Lorem.',
        'categories' => ['Gutenberg', 'Bindings'],
        'main_file' => ''
    ],
    'NicolasCaen/up-variation-generator' => [
        'name' => 'Up Variation Generator',
        'description' => '',
        'categories' => ['Gutenberg', 'Variations'],
        'main_file' => ''
    ],
    'NicolasCaen/up-gutenberg-binding-collection' => [
        'name' => 'Up Gutenberg Binding Collection',
        'description' => '',
        'categories' => ['Gutenberg', 'Bindings'],
        'main_file' => ''
    ],
    'NicolasCaen/up-gutenberg-bindings-interface' => [
        'name' => 'Up Gutenberg Bindings Interface',
        'description' => '',
        'categories' => ['Gutenberg', 'Interface'],
        'main_file' => ''
    ],
    'NicolasCaen/up-gutenberg-metabox' => [
        'name' => 'Up Gutenberg Metabox',
        'description' => '',
        'categories' => ['Gutenberg', 'Metabox'],
        'main_file' => ''
    ],
    'NicolasCaen/up-library-generator' => [
        'name' => 'Up Library Generator',
        'description' => '',
        'categories' => ['Gutenberg', 'Outils'],
        'main_file' => ''
    ],
    'NicolasCaen/up-shortcodes-library' => [
        'name' => 'Up Shortcodes Library',
        'description' => '',
        'categories' => ['Shortcodes'],
        'main_file' => ''
    ],
    'NicolasCaen/up-section-styles' => [
        'name' => 'Up Section Styles',
        'description' => '',
        'categories' => ['Gutenberg', 'Styles'],
        'main_file' => ''
    ],
    'NicolasCaen/up-theme-generator' => [
        'name' => 'Up Theme Generator',
        'description' => '',
        'categories' => ['Thème'],
        'main_file' => ''
    ],
    'NicolasCaen/up-gsap-animate' => [
        'name' => 'Up GSAP Animate',
        'description' => '',
        'categories' => ['Animation'],
        'main_file' => ''
    ],
    'NicolasCaen/up-gsap-animate-2' => [
        'name' => 'Up GSAP Animate 2',
        'description' => '',
        'categories' => ['Animation'],
        'main_file' => ''
    ],
    'NicolasCaen/up-wp-resize-admin-aside' => [
        'name' => 'Up WP Resize Admin Aside',
        'description' => '',
        'categories' => ['Administration'],
        'main_file' => ''
    ],

    "NicolasCaen/up-composition-json-to-theme" =>[
        "name" => "Up Composition JSON to Theme",
        "description" => "Ajoute un générateur de composition qui va entegistrer la composition au format json dans le dossier pattern_json du thème",
        "categories" => ["Gutenberg"],
        "main_file" => ""
    ],
    "NicolasCaen/up-tour-guide" => [
        "name" => "Up Tour Guide",
        "description" => "Ajoute un guide de tour pour les utilisateurs",
        "categories" => ["Gutenberg", "Administration"],
        "main_file" => ""
    ],
    "NicolasCaen/up-bk-swiper-slider" =>[
        "name" => "Up BK Swiper Slider",
        "description" => "Ajoute un slider Swiper pour les utilisateurs",
        "categories" => ["Gutenberg", "Slider"],
        "main_file" => ""
    ],
    "NicolasCaen/up-gutenberg-easy-option" =>[
        "name" => "Up Gutenberg Easy Option",
        "description" => "Ajoute des options configurables (toggles, sélecteurs et presets) aux blocs Gutenberg pour ajouter/retirer des classes CSS depuis l’inspecteur de l’éditeur. Les options peuvent être groupées par panneau (metabox) personnalisé dans l’inspecteur et être gérées depuis une interface d’administration (import/export inclus).",
        "categories" => ["Gutenberg", "Options"],
        "main_file" => ""
    ],
    "NicolasCaen/up-define-inner-block-default" =>[
        "name" => "Up Define Inner Block Default",
        "description" => "Ajoute un inner block par défaut pour les blocs Gutenberg.",
        "categories" => ["Gutenberg", "Inner Block"],
        "main_file" => "Ce plugin permet de définir automatiquement la variation ou les classes à appliquer à un bloc inséré en fonction du parent dans lequel il est ajouté. Vous pouvez déclarer autant de règles que nécessaire via un filtre WordPress."
    ],
    "NicolasCaen/up-admin-bar-position" =>[
        "name" => "Up Admin Bar Position",
        "description" => "Ajoute un positionnement de l'admin bar.",
        "categories" => ["Administration"],
        "main_file" => ""
    ],
    "NicolasCaen/up-random-image-generator" =>[
        "name" => "Up Random Image Generator",
        "description" => "Ajoute une une attribution d'images aléatoires.",
        "categories" => ["Gutenberg", "Image"],
        "main_file" => ""
    ],
    "NicolasCaen/up-csv-importer" => [
        "name" => "Up CSV Importer",
        "description" => "Créer, configurer et enregistrer des fichiers XML décrivant comment importer un CSV dans WordPress..",
        "categories" => ["Import", "Export",  "CSV"],
        "main_file" => ""
    ],
    "NicolasCaen/up-csv-exporter" => [
        "name" => "Up CSV Exporter",
        "description" => "Exporter des données WordPress en CSV à partir des mêmes configurations XML que l’import (post_type, champs cœur, métadonnées, taxonomies, image à la une).",
        "categories" => ["Import", "Export", "CSV"],
        "main_file" => ""
    ]
];
