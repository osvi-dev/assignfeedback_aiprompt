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
        
        // ID de la tarea
        $assignid = $this->assignment->get_instance()->id;
        // ID del usuario
        $userid = $grade ? $grade->userid : 0;
        
        $PAGE->requires->js_init_code("
            console.log('El user ID es:', " . $userid . ");
            console.log('El assignid es: ', " . $assignid . ");
        ");

        // Obtener el prompt de la tarea
        $promptdata = $DB->get_record('local_prompt_tarea', ["assignid" => $assignid]);
        
        if (!$promptdata) {
            // Si no hay prompt configurado, mostrar mensaje de advertencia
            $mform->addElement('html', '<div class="alert alert-warning">' . 
                get_string('no_prompt_found', 'assignfeedback_aiprompt') . 
                '</div>');
            return true;
        }

        // Cargar feedback existente si ya fue generado
        $aifeedback = '';
        if ($grade && $grade->id) {
            $record = $DB->get_record('assignfeedback_aiprompt', [
                'assignment' => $assignid,
                'userid' => $userid
            ]);
            if ($record) {
                $aifeedback = $record->aifeedback;
            }
        }

        // Botón para generar feedback
        $mform->addElement('html', '<div class="form-group row fitem">');
        $mform->addElement('html', '<div class="col-md-3"></div>');
        $mform->addElement('html', '<div class="col-md-9">');
        
        $buttonattributes = [
            'type' => 'button',
            'class' => 'btn btn-primary',
            'id' => 'id_generate_ai_feedback',
            'data-assignid' => $assignid,
            'data-userid' => $userid
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
        $mform->setType('assignfeedbackaiprompt', PARAM_RAW);
        
        // Setear el valor si ya existe feedback
        if (!empty($aifeedback)) {
            $mform->setDefault('assignfeedbackaiprompt', $aifeedback);
        }
        
        // Cargar JavaScript
        $PAGE->requires->js('/mod/assign/feedback/aiprompt/js/feedback_generator.js');
        
        return true;
    }
    
    /**
     * Guarda el feedback
     */
    public function save(stdClass $grade, stdClass $data): bool {
        global $DB;
        
        $feedbacktext = isset($data->assignfeedbackaiprompt) ? $data->assignfeedbackaiprompt : '';
        
        if (empty($feedbacktext)) {
            return true;
        }
        
        $record = new stdClass();
        $record->assignment = $this->assignment->get_instance()->id;
        $record->aifeedback = $feedbacktext;
        $record->userid = $grade->userid;
        $record->isedited = 1; // Marcado como editado ya que el profesor lo está guardando
        $record->timemodified = time();
        
        $existing = $DB->get_record('assignfeedback_aiprompt', [
            'assignment' => $record->assignment,
            'userid' => $record->userid
        ]);
        
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('assignfeedback_aiprompt', $record);
        } else {
            $record->timecreated = time();
            $DB->insert_record('assignfeedback_aiprompt', $record);
        }
        
        return true;
    }
    /**
     * Muestra el feedback al estudiante
     * Este método se llama cuando el estudiante visualiza su calificación
     */
    public function view(stdClass $grade) {
        global $DB;
        
        $assignid = $this->assignment->get_instance()->id;
        $userid = $grade->userid;
        
        // Obtener el feedback guardado
        $record = $DB->get_record('assignfeedback_aiprompt', [
            'assignment' => $assignid,
            'userid' => $userid
        ]);
        
        if (!$record || empty($record->aifeedback)) {
            return ''; // No hay feedback que mostrar
        }
        
        // Formatear el feedback para mostrarlo al estudiante
        $feedback = format_text($record->aifeedback, FORMAT_HTML);
        
        // Crear el HTML para mostrar
        $html = '<div class="assignfeedback_aiprompt_feedback">';
        $html .= '<div class="feedback-content" style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;">';
        $html .= $feedback;
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
     /**
     * Verifica si el feedback está vacío
     * Este método determina si hay contenido que mostrar al estudiante
     */
    public function is_empty(stdClass $grade) {
        global $DB;
        
        $assignid = $this->assignment->get_instance()->id;
        $userid = $grade->userid;
        
        $record = $DB->get_record('assignfeedback_aiprompt', [
            'assignment' => $assignid,
            'userid' => $userid
        ]);
        
        // Retorna true si está vacío, false si tiene contenido
        return !$record || empty($record->aifeedback);
    }
    
    /**
     * Muestra un resumen del feedback (para la vista del profesor)
     */
    public function view_summary(stdClass $grade, & $showviewlink) {
        $content = $this->view($grade);
        
        // Si hay contenido, no mostrar el enlace "ver más"
        if (!empty($content)) {
            $showviewlink = false;
        }
        
        return $content;
    }
    
    /**
     * Para debugging: Verifica si hay errores en las consultas
     */
    public function can_view_feedback(stdClass $grade) {
        // Permite ver el feedback si existe
        return !$this->is_empty($grade);
    }
}
