<?php
/**
 * AJAX endpoint to test the connection with Ollama server.
 * Uses the configured URL and model from the plugin settings.
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/classes/ollama_client.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

header('Content-Type: application/json');

$ollamaurl = get_config('assignfeedback_aiprompt', 'ollama_url');
$ollamamodel = get_config('assignfeedback_aiprompt', 'ollama_model');
$timeout = get_config('assignfeedback_aiprompt', 'timeout') ?: 120;

if (empty($ollamaurl)) {
    echo json_encode([
        'success' => false,
        'message' => get_string('test_connection_no_url', 'assignfeedback_aiprompt')
    ]);
    die();
}

// Step 1: Test basic connectivity (GET /api/tags).
$url = rtrim($ollamaurl, '/') . '/api/tags';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlerror = curl_error($ch);
curl_close($ch);

if ($curlerror) {
    echo json_encode([
        'success' => false,
        'message' => get_string('test_connection_fail', 'assignfeedback_aiprompt',
                     (object)['url' => $ollamaurl]) .
                     ' — Error: ' . $curlerror
    ]);
    die();
}

if ($httpcode != 200) {
    echo json_encode([
        'success' => false,
        'message' => get_string('test_connection_fail', 'assignfeedback_aiprompt',
                     (object)['url' => $ollamaurl]) .
                     ' — HTTP ' . $httpcode
    ]);
    die();
}

// Step 2: Check if the configured model is available.
$tags = json_decode($response, true);
$models = [];
$modelfound = false;

if (isset($tags['models']) && is_array($tags['models'])) {
    foreach ($tags['models'] as $m) {
        $models[] = $m['name'];
        if ($m['name'] === $ollamamodel) {
            $modelfound = true;
        }
    }
}

if (!$modelfound && !empty($ollamamodel)) {
    echo json_encode([
        'success' => false,
        'message' => get_string('test_connection_model_not_found', 'assignfeedback_aiprompt',
                     (object)['url' => $ollamaurl, 'model' => $ollamamodel, 'available' => implode(', ', $models)])
    ]);
    die();
}

// All good!
echo json_encode([
    'success' => true,
    'message' => get_string('test_connection_success', 'assignfeedback_aiprompt',
                 (object)['url' => $ollamaurl, 'model' => $ollamamodel])
]);
die();
