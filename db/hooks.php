<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_standard_footer_html_generation::class,
        'callback' => [\assignfeedback_aiprompt\hook\output::class, 'before_standard_footer_html_generation'],
    ],
];
