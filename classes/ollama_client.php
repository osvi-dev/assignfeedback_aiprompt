<?php
namespace assignfeedback_aiprompt;

defined('MOODLE_INTERNAL') || die();

class ollama_client {
    
    private $url;
    private $model;
    private $timeout;
    
    public function __construct() {
        $this->url = get_config('assignfeedback_aiprompt', 'ollama_url');
        $this->model = get_config('assignfeedback_aiprompt', 'ollama_model');
        $this->timeout = get_config('assignfeedback_aiprompt', 'timeout') ?: 120;
    }
    
    /**
     * Envía un prompt a Ollama y obtiene la respuesta
     * 
     * @param string $prompt El prompt para enviar
     * @param string $context Contexto adicional (texto del PDF)
     * @return string|false La respuesta de Ollama o false en caso de error
     */
    public function generate_feedback($prompt, $context = '') {
        global $PAGE;

        
        $url = rtrim($this->url, '/') . '/api/generate';
        
        $full_prompt = $prompt;
        if (!empty($context)) {
            $full_prompt .= "\n\nContenido del documento del estudiante:\n" . $context;
        }
        
        $data = [
            'model' => $this->model,
            'prompt' => $full_prompt,
            'stream' => false
        ];
        
        $PAGE->requires->js_init_code("
            console.log('DATA:', " . json_encode($data) . ");
        "); 

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            debugging('Ollama error: ' . $error, DEBUG_DEVELOPER);
            return false;
        }
        
        if ($http_code != 200) {
            debugging('Ollama HTTP error: ' . $http_code, DEBUG_DEVELOPER);
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['response'])) {
            return $result['response'];
        }
        
        return false;
    }
    
    /**
     * Verifica la conexión con Ollama
     * 
     * @return bool True si la conexión es exitosa
     */
    public function test_connection() {
        $url = rtrim($this->url, '/') . '/api/tags';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($http_code == 200);
    }
}