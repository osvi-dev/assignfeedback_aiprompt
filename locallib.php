<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/feedback/aiprompt/classes/ollama_client.php');

class assign_feedback_aiprompt extends assign_feedback_plugin {
    
    /**
     * Obtiene el nombre del plugin
     */
    public function get_name() {
        return get_string('pluginname', 'assignfeedback_aiprompt');
    }
    
    public function get_form_elements($grade, MoodleQuickForm $mform, stdClass $data) {
        
        global $DB, $PAGE;
        $assignid = $this->assignment->get_instance()->id;
        $userid = $grade ? $grade->userid : 0;

        // Hacemos consulta a la tabla para obtener el prompt de la tarea

        $data = $DB->get_record('local_prompt_tarea', ["assignid" => $assignid]);
        $prompt = $data->prompt;

        // // Cargar feedback existente
        // $aifeedback = '';
        // if ($grade && $grade->id) {
        //     $record = $DB->get_record('local_prompt_tarea', [
        //         'assignid' => $assignid
        //     ]);
        //     if ($record) {
        //         $aifeedback = $record->aifeedback;
        //     }
        // }

        // ✅ ENVIAR DEBUG A CONSOLA JAVASCRIPT
        $debug_data = [
            'assignid' => $assignid,
            'userid' => $userid,
            'grade_id' => $grade ? $grade->id : null,
            'aifeedback' => $prompt
        ];
        
        $PAGE->requires->js_init_code("
            console.log('assign_feedback_aiprompt DEBUG:', " . json_encode($debug_data) . ");
        ");


        $mform->addElement('html', '<div class="form-group row fitem">');
        $mform->addElement('html', '<div class="col-md-3"></div>');
        $mform->addElement('html', '<div class="col-md-9">');
        
        $buttonattributes = [
            'type' => 'button',
            'class' => 'btn btn-primary',
            'id' => 'id_generate_ai_feedback',
            'data-assignid' => $assignid,
            'data-userid' => $userid,
            'data-gradeid' => $grade ? $grade->id : 0
        ];
        
        $mform->addElement('html', 
            html_writer::tag('button', 
                get_string('generate_feedback', 'assignfeedback_aiprompt'),
                $buttonattributes
            )
        );
        
        $mform->addElement('html', ' <span id="ai_feedback_status" style="margin-left: 10px;"></span>');
        $mform->addElement('html', '</div></div>');
        
        // Textarea para el feedback de IA (editable)
        $mform->addElement('textarea', 
            'assignfeedbackaiprompt', 
            get_string('aifeedback', 'assignfeedback_aiprompt'),
            ['rows' => 15, 'cols' => 80, 'id' => 'id_assignfeedbackaiprompt']
        );
        
        $PAGE->requires->js('/mod/assign/feedback/aiprompt/js/feedback_generator.js');

        
        return true;
    }
}