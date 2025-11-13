<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // URL de Ollama
    $settings->add(new admin_setting_configtext(
        'assignfeedback_aiprompt/ollama_url',
        get_string('ollama_url', 'assignfeedback_aiprompt'),
        get_string('ollama_url_desc', 'assignfeedback_aiprompt'),
        'http://localhost:11434',
        PARAM_URL
    ));
    
    // Modelo de Ollama
    $settings->add(new admin_setting_configtext(
        'assignfeedback_aiprompt/ollama_model',
        get_string('ollama_model', 'assignfeedback_aiprompt'),
        get_string('ollama_model_desc', 'assignfeedback_aiprompt'),
        'deepseek-r1:7b',
        PARAM_TEXT
    ));
    
    // Timeout
    $settings->add(new admin_setting_configtext(
        'assignfeedback_aiprompt/timeout',
        get_string('timeout', 'assignfeedback_aiprompt'),
        get_string('timeout_desc', 'assignfeedback_aiprompt'),
        '120',
        PARAM_INT
    ));
    
    // Habilitado por defecto
    $settings->add(new admin_setting_configcheckbox(
        'assignfeedback_aiprompt/default',
        get_string('default', 'assignfeedback_aiprompt'),
        get_string('default_help', 'assignfeedback_aiprompt'),
        1
    ));
}