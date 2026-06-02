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
        
        if (!$promptdata || empty($promptdata->prompt)) {
            // Si no hay prompt del profesor configurado, mostrar mensaje de advertencia
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
     * Este método se llama cuando el estudiante visualiza su calificación.
     * Ahora incluye un botón para que el estudiante genere retroalimentación con IA
     * usando el prompt_estudiante definido por el profesor.
     */
    public function view(stdClass $grade) {
        global $DB, $USER, $PAGE;
        
        $assignid = $this->assignment->get_instance()->id;
        $userid = $grade->userid;
        
        $html = '';
        
        // --- Sección 1: Feedback del profesor (generado/editado por el profesor) ---
        $record = $DB->get_record('assignfeedback_aiprompt', [
            'assignment' => $assignid,
            'userid' => $userid
        ]);
        
        if ($record && !empty($record->aifeedback)) {
            $feedback = format_text($record->aifeedback, FORMAT_HTML);
            
            $html .= '<div class="assignfeedback_aiprompt_feedback">';
            $html .= '<div class="feedback-content" style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;">';
            $html .= $feedback;
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // --- Sección 2: Botón de retroalimentación para el estudiante ---
        // Solo mostrar si el usuario actual ES el estudiante dueño de esta entrega
        if ($USER->id == $userid) {
            // Verificar si existe el prompt_estudiante
            $promptdata = $DB->get_record('local_prompt_tarea', ['assignid' => $assignid]);
            
            // Cargar feedback del estudiante ya generado previamente
            $studentfeedback = '';
            $studentrecord = $DB->get_record('assignfeedback_aiprompt_stu', [
                'assignment' => $assignid,
                'userid' => $userid
            ]);
            if ($studentrecord && !empty($studentrecord->studentfeedback)) {
                $studentfeedback = $studentrecord->studentfeedback;
            }
            
            $html .= '<div class="assignfeedback_student_section" style="margin-top: 20px; padding: 15px; border: 1px solid #dee2e6; border-radius: 8px; background-color: #fff;">';
            $html .= '<h5 style="margin-bottom: 10px; color: #495057;">' . get_string('student_feedback_title', 'assignfeedback_aiprompt') . '</h5>';
            
            if (!$promptdata || empty($promptdata->prompt_estudiante)) {
                // No hay prompt del estudiante configurado — mostrar advertencia
                $html .= '<div class="alert alert-warning" style="margin-bottom: 0;">';
                $html .= '<i class="fa fa-exclamation-triangle"></i> ';
                $html .= get_string('no_student_prompt_assigned', 'assignfeedback_aiprompt');
                $html .= '</div>';
            } else {
                // Hay prompt — mostrar el botón
                $html .= '<button type="button" class="btn btn-success" id="id_generate_student_ai_feedback" '
                       . 'data-assignid="' . $assignid . '" '
                       . 'data-userid="' . $userid . '">'
                       . '<i class="fa fa-robot"></i> '
                       . get_string('generate_student_feedback', 'assignfeedback_aiprompt')
                       . '</button>';
                $html .= ' <span id="student_ai_feedback_status" style="margin-left: 10px;"></span>';
            }
            
            // Contenedor para mostrar el resultado
            $html .= '<div id="student_ai_feedback_result" style="margin-top: 15px;">';
            if (!empty($studentfeedback)) {
                $html .= '<div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; white-space: pre-wrap;">';
                $html .= format_text($studentfeedback, FORMAT_HTML);
                $html .= '</div>';
            }
            $html .= '</div>';
            
            $html .= '</div>';
            
            // Cargar JavaScript para el botón del estudiante
            $PAGE->requires->js('/mod/assign/feedback/aiprompt/js/student_feedback_generator.js');
        }
        
        return $html;
    }
    
     /**
     * Verifica si el feedback está vacío
     * Este método determina si hay contenido que mostrar al estudiante
     */
    public function is_empty(stdClass $grade) {
        global $DB, $USER;
        
        $assignid = $this->assignment->get_instance()->id;
        $userid = $grade->userid;
        
        // Si hay feedback del profesor, no está vacío
        $record = $DB->get_record('assignfeedback_aiprompt', [
            'assignment' => $assignid,
            'userid' => $userid
        ]);
        
        if ($record && !empty($record->aifeedback)) {
            return false;
        }
        
        // Si el usuario es el estudiante, siempre mostrar la sección
        // (para que vea el botón o el mensaje de advertencia)
        if ($USER->id == $userid) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Muestra un resumen del feedback 
     */
    public function view_summary(stdClass $grade, & $showviewlink) {
        $content = $this->view($grade);
        
        // Si hay contenido, no mostrar el enlace "ver más"
        if (!empty($content)) {
            $showviewlink = false;
        }
        
        return $content;
    }
    
}
