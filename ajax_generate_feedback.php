<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/feedback/aiprompt/classes/ollama_client.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

// Verificar sesión
require_login();

$assignid = required_param('assignid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$role = optional_param('role', 'teacher', PARAM_ALPHA); // 'teacher' o 'student'

header('Content-Type: application/json');

try {
    // Obtener el prompt de la base de datos
    $promptdata = $DB->get_record('local_prompt_tarea', ['assignid' => $assignid]);

    // Determinar qué prompt usar según el rol
    if ($role === 'student') {
        // El estudiante usa el prompt_estudiante
        if (!$promptdata || empty($promptdata->prompt_estudiante)) {
            echo json_encode([
                'success' => false,
                'error' => get_string('no_student_prompt_assigned', 'assignfeedback_aiprompt')
            ]);
            exit;
        }
        $prompt = $promptdata->prompt_estudiante;
    } else {
        // El profesor usa el prompt normal
        if (!$promptdata || empty($promptdata->prompt)) {
            echo json_encode([
                'success' => false,
                'error' => get_string('no_prompt_found', 'assignfeedback_aiprompt')
            ]);
            exit;
        }
        $prompt = $promptdata->prompt;
    }
    
    // Obtener la tarea y el contexto del estudiante
    list($course, $cm) = get_course_and_cm_from_instance($assignid, 'assign');
    $context = context_module::instance($cm->id);
    
    // Verificar capacidades según el rol
    if ($role === 'student') {
        require_capability('mod/assign:submit', $context);
    } else {
        require_capability('mod/assign:grade', $context);
    }
    
    $assign = new assign($context, $cm, $course);
    
    // Obtener la entrega del estudiante
    $submission = $assign->get_user_submission($userid, false);
    
    if (!$submission) {
        echo json_encode([
            'success' => false,
            'error' => get_string('no_submission', 'assignfeedback_aiprompt')
        ]);
        exit;
    }
    
    // Obtener el contenido del PDF
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'assignsubmission_file', 'submission_files', $submission->id, 'timemodified', false);
    
    $pdfcontent = '';
    
    foreach ($files as $file) {
        if ($file->get_mimetype() === 'application/pdf') {
            // Extraer texto del PDF usando pdftotext si está disponible
            $tempfile = $file->copy_content_to_temp();
            
            // Intentar extraer texto con pdftotext
            if (shell_exec('which pdftotext')) {
                $output = shell_exec("pdftotext " . escapeshellarg($tempfile) . " -");
                if ($output) {
                    $pdfcontent = $output;
                }
            }
            
            // Si no se pudo extraer, al menos informar que hay un PDF
            if (empty($pdfcontent)) {
                $pdfcontent = "El estudiante ha enviado un archivo PDF llamado: " . $file->get_filename();
            }
            
            @unlink($tempfile);
            break; // Solo procesamos el primer PDF
        }
    }
    
    if (empty($pdfcontent) && empty($files)) {
        echo json_encode([
            'success' => false,
            'error' => get_string('no_pdf_found', 'assignfeedback_aiprompt')
        ]);
        exit;
    }
    
    // Crear instancia del cliente Ollama
    $ollama = new \assignfeedback_aiprompt\ollama_client();
    
    // Generar feedback
    $feedback = $ollama->generate_feedback($prompt, $pdfcontent);
    
    if ($feedback === false) {
        echo json_encode([
            'success' => false,
            'error' => get_string('ollama_error', 'assignfeedback_aiprompt')
        ]);
        exit;
    }
    
    // Guardar en base de datos
    if ($role === 'student') {
        // Guardar feedback del estudiante en una tabla o campo separado
        $record = new stdClass();
        $record->assignment = $assignid;
        $record->studentfeedback = $feedback;
        $record->userid = $userid;
        $record->timecreated = time();
        $record->timemodified = time();

        $existing = $DB->get_record('assignfeedback_aiprompt_stu', [
            'assignment' => $assignid,
            'userid' => $userid
        ]);

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('assignfeedback_aiprompt_stu', $record);
        } else {
            $DB->insert_record('assignfeedback_aiprompt_stu', $record);
        }
    } else {
        // Guardar feedback del profesor (comportamiento original)
        $record = new stdClass();
        $record->assignment = $assignid;
        $record->aifeedback = $feedback;
        $record->userid = $userid;
        $record->isedited = 0;
        $record->timecreated = time();
        $record->timemodified = time();

        $existing = $DB->get_record('assignfeedback_aiprompt', [
            'assignment' => $assignid,
            'userid' => $userid
        ]);

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('assignfeedback_aiprompt', $record);
        } else {
            $DB->insert_record('assignfeedback_aiprompt', $record);
        }
    }
    
    echo json_encode([
        'success' => true,
        'feedback' => $feedback
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}