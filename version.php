<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'assignfeedback_aiprompt';
$plugin->version = 2025011102;
$plugin->requires = 2024042200; // Moodle 5.0
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.0';
$plugin->dependencies = array(
    'mod_assign' => 2024042200
);