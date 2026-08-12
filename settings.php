<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/feedback/aiprompt/classes/admin_setting_testconnection.php');

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

    // Botón para verificar conexión con Ollama
    $settings->add(new \assignfeedback_aiprompt\admin_setting_testconnection(
        'assignfeedback_aiprompt/test_connection',
        get_string('test_connection', 'assignfeedback_aiprompt'),
        get_string('test_connection_desc', 'assignfeedback_aiprompt')
    ));
    
    // Habilitado por defecto
    $settings->add(new admin_setting_configcheckbox(
        'assignfeedback_aiprompt/default',
        get_string('default', 'assignfeedback_aiprompt'),
        get_string('default_help', 'assignfeedback_aiprompt'),
        1
    ));
}