<?php
namespace assignfeedback_aiprompt\hook;

defined('MOODLE_INTERNAL') || die();

class output {

    /**
     * Inyecta el botón de retroalimentación con IA para el estudiante
     * en la página mod/assign/view.php cuando aún no hay calificación.
     *
     * @param \core\hook\output\before_standard_footer_html_generation $hook
     */
    public static function before_standard_footer_html_generation(
        \core\hook\output\before_standard_footer_html_generation $hook
    ): void {
        global $PAGE, $USER, $DB, $CFG;

        // Solo actuar en la página de vista del assign.
        if ($PAGE->pagetype !== 'mod-assign-view') {
            return;
        }

        $cm = $PAGE->cm;
        if (!$cm || $cm->modname !== 'assign') {
            return;
        }

        $context = \context_module::instance($cm->id);

        // Solo para estudiantes: puede enviar pero NO calificar.
        if (!has_capability('mod/assign:submit', $context) || has_capability('mod/assign:grade', $context)) {
            return;
        }

        $assignid = $cm->instance;

        // Verificar que el plugin esté habilitado para esta tarea.
        $pluginenabled = $DB->get_record('assign_plugin_config', [
            'assignment' => $assignid,
            'plugin'     => 'aiprompt',
            'subtype'    => 'assignfeedback',
            'name'       => 'enabled',
        ]);

        if (!$pluginenabled || $pluginenabled->value != '1') {
            return;
        }

        // Verificar que el estudiante tenga una entrega.
        $submission = $DB->get_record('assign_submission', [
            'assignment' => $assignid,
            'userid'     => $USER->id,
            'latest'     => 1,
        ]);

        if (!$submission || $submission->status !== 'submitted') {
            return;
        }

        // Si ya existe una calificación (grade), el método view() de locallib.php
        // ya se encargará de mostrar la sección del estudiante. No inyectar para
        // evitar que el botón aparezca dos veces.
        $grade = $DB->get_record('assign_grades', [
            'assignment' => $assignid,
            'userid'     => $USER->id,
        ]);
        if ($grade) {
            return;
        }

        // Obtener prompt del estudiante.
        $promptdata = $DB->get_record('local_prompt_tarea', ['assignid' => $assignid]);

        // Cargar feedback existente del estudiante si lo hay.
        $studentfeedback = '';
        $studentrecord = $DB->get_record('assignfeedback_aiprompt_stu', [
            'assignment' => $assignid,
            'userid'     => $USER->id,
        ]);
        if ($studentrecord && !empty($studentrecord->studentfeedback)) {
            $studentfeedback = format_text($studentrecord->studentfeedback, FORMAT_HTML);
        }

        // Construir el HTML del botón.
        $html = self::build_student_section_html($assignid, $USER->id, $promptdata, $studentfeedback);

        // Agregar el JS inline para mover la sección al lugar correcto y manejar el click.
        $ajaxurl = $CFG->wwwroot . '/mod/assign/feedback/aiprompt/ajax_generate_feedback.php';
        $sesskey = sesskey();
        $html .= self::build_inline_js($ajaxurl, $sesskey);

        $hook->add_html($html);
    }

    /**
     * Genera el HTML de la sección de retroalimentación del estudiante.
     */
    private static function build_student_section_html(
        int $assignid,
        int $userid,
        $promptdata,
        string $studentfeedback
    ): string {

        $title = get_string('student_feedback_title', 'assignfeedback_aiprompt');

        $inner = '';
        if (!$promptdata || empty($promptdata->prompt_estudiante)) {
            // No hay prompt — mostrar advertencia.
            $warning = get_string('no_student_prompt_assigned', 'assignfeedback_aiprompt');
            $inner .= '<div class="alert alert-warning" style="margin-bottom:0;">'
                     . '<i class="fa fa-exclamation-triangle"></i> ' . $warning
                     . '</div>';
        } else {
            // Hay prompt — mostrar botón.
            $btnlabel = get_string('generate_student_feedback', 'assignfeedback_aiprompt');
            $inner .= '<button type="button" class="btn btn-success" id="id_generate_student_ai_feedback_view" '
                     . 'data-assignid="' . $assignid . '" '
                     . 'data-userid="' . $userid . '">'
                     . '<i class="fa fa-robot"></i> ' . $btnlabel
                     . '</button>';
            $inner .= ' <span id="student_ai_feedback_status_view" style="margin-left:10px;"></span>';
        }

        // Contenedor de resultado.
        $resultcontent = '';
        if (!empty($studentfeedback)) {
            $resultcontent = '<div style="background-color:#f8f9fa;padding:15px;border-radius:5px;'
                           . 'border-left:4px solid #28a745;white-space:pre-wrap;">'
                           . $studentfeedback . '</div>';
        }
        $inner .= '<div id="student_ai_feedback_result_view" style="margin-top:15px;">'
                 . $resultcontent . '</div>';

        // Envolver en una tarjeta oculta (el JS la moverá al lugar correcto).
        $html  = '<div id="student-ai-feedback-section" style="display:none;">';
        $html .= '<div class="card mb-3" style="margin-top:20px;border:1px solid #dee2e6;border-radius:8px;">';
        $html .= '<div class="card-body" style="padding:15px;">';
        $html .= '<h5 style="margin-bottom:10px;color:#495057;">' . $title . '</h5>';
        $html .= $inner;
        $html .= '</div></div></div>';

        return $html;
    }

    /**
     * Genera el JavaScript inline que reubica la sección y maneja el click del botón.
     */
    private static function build_inline_js(string $ajaxurl, string $sesskey): string {
        return <<<JS
<script>
document.addEventListener("DOMContentLoaded", function() {
    var section = document.getElementById("student-ai-feedback-section");
    if (!section) return;

    // Buscar el lugar adecuado para insertar la sección.
    var target = document.querySelector(".submissionstatustable")
              || document.querySelector("[data-region='assign-info']")
              || document.getElementById("region-main-box");

    if (target) {
        target.parentNode.insertBefore(section, target.nextSibling);
    }
    section.style.display = "block";

    // Manejar click del botón.
    var button = document.getElementById("id_generate_student_ai_feedback_view");
    if (!button) return;

    button.addEventListener("click", function(e) {
        e.preventDefault();

        var assignid  = button.getAttribute("data-assignid");
        var userid    = button.getAttribute("data-userid");
        var status    = document.getElementById("student_ai_feedback_status_view");
        var container = document.getElementById("student_ai_feedback_result_view");

        button.disabled = true;
        button.style.opacity = "0.6";
        button.style.cursor  = "not-allowed";

        status.innerHTML = '<span style="color:blue;"><i class="fa fa-spinner fa-spin"></i> Generando retroalimentación con IA, por favor espere...</span>';
        container.innerHTML = '<p style="color:#888;font-style:italic;">Procesando...</p>';

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "{$ajaxurl}", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.timeout = 150000;

        xhr.onload = function() {
            button.disabled = false;
            button.style.opacity = "1";
            button.style.cursor  = "pointer";

            if (xhr.status === 200) {
                try {
                    var r = JSON.parse(xhr.responseText);
                    if (r.success) {
                        status.innerHTML = '<span style="color:green;"><i class="fa fa-check"></i> ¡Retroalimentación generada exitosamente!</span>';
                        container.innerHTML = '<div style="background-color:#f8f9fa;padding:15px;border-radius:5px;border-left:4px solid #28a745;white-space:pre-wrap;">' + r.feedback + '</div>';
                    } else {
                        status.innerHTML = '<span style="color:red;"><i class="fa fa-times"></i> ' + r.error + '</span>';
                        container.innerHTML = "";
                    }
                } catch (ex) {
                    status.innerHTML = '<span style="color:red;"><i class="fa fa-times"></i> Error al procesar la respuesta</span>';
                    container.innerHTML = "";
                }
            } else {
                status.innerHTML = '<span style="color:red;"><i class="fa fa-times"></i> Error HTTP ' + xhr.status + '</span>';
                container.innerHTML = "";
            }
        };

        xhr.onerror = function() {
            button.disabled = false;
            button.style.opacity = "1";
            button.style.cursor  = "pointer";
            status.innerHTML = '<span style="color:red;"><i class="fa fa-times"></i> Error de red</span>';
            container.innerHTML = "";
        };

        xhr.ontimeout = function() {
            button.disabled = false;
            button.style.opacity = "1";
            button.style.cursor  = "pointer";
            status.innerHTML = '<span style="color:red;"><i class="fa fa-times"></i> Timeout</span>';
            container.innerHTML = "";
        };

        xhr.send("assignid=" + assignid + "&userid=" + userid + "&role=student&sesskey={$sesskey}");
    });
});
</script>
JS;
    }
}
