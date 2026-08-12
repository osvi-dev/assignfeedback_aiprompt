<?php
/**
 * Custom admin setting that renders a "Test Ollama Connection" button
 * in the plugin's settings page.
 */

namespace assignfeedback_aiprompt;

defined('MOODLE_INTERNAL') || die();

class admin_setting_testconnection extends \admin_setting {

    /**
     * Constructor — no actual config value is stored.
     *
     * @param string $name   Unique setting name.
     * @param string $heading Visible heading.
     * @param string $description Description text.
     */
    public function __construct($name, $heading, $description) {
        parent::__construct($name, $heading, $description, '');
    }

    /**
     * Returns the current setting — always empty for this widget.
     */
    public function get_setting() {
        return '';
    }

    /**
     * Nothing to store.
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * Render the button HTML.
     */
    public function output_html($data, $query = '') {
        global $CFG;

        $ajaxurl = $CFG->wwwroot . '/mod/assign/feedback/aiprompt/ajax_test_connection.php';

        $html = '<div class="form-group row" id="admin-test_connection">';
        $html .= '<div class="col-sm-3">';
        $html .= '<label>' . $this->visiblename . '</label>';
        $html .= '</div>';
        $html .= '<div class="col-sm-9">';

        // The button
        $html .= '<button type="button" class="btn btn-outline-primary" id="id_test_ollama_connection" '
               . 'data-ajaxurl="' . s($ajaxurl) . '">';
        $html .= '<i class="fa fa-plug" aria-hidden="true"></i> ';
        $html .= get_string('test_connection_btn', 'assignfeedback_aiprompt');
        $html .= '</button>';

        // Status area
        $html .= ' <span id="ollama_test_status" style="margin-left: 12px;"></span>';

        // Description
        if (!empty($this->description)) {
            $html .= '<div class="form-text text-muted small mt-1">' . $this->description . '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        // Inline JavaScript for the AJAX call
        $html .= '<script>
document.addEventListener("DOMContentLoaded", function() {
    var btn = document.getElementById("id_test_ollama_connection");
    var status = document.getElementById("ollama_test_status");
    if (!btn) return;

    btn.addEventListener("click", function() {
        btn.disabled = true;
        btn.innerHTML = \'<i class="fa fa-spinner fa-spin"></i> ' . addslashes_js(get_string('test_connection_checking', 'assignfeedback_aiprompt')) . '\';
        status.innerHTML = "";

        var xhr = new XMLHttpRequest();
        xhr.open("GET", btn.getAttribute("data-ajaxurl"), true);
        xhr.setRequestHeader("Content-Type", "application/json");
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                btn.disabled = false;
                btn.innerHTML = \'<i class="fa fa-plug"></i> ' . addslashes_js(get_string('test_connection_btn', 'assignfeedback_aiprompt')) . '\';

                try {
                    var result = JSON.parse(xhr.responseText);
                    if (result.success) {
                        status.innerHTML = \'<span class="badge badge-success bg-success text-white" style="font-size: 0.9em; padding: 6px 12px;"><i class="fa fa-check-circle"></i> \' + result.message + \'</span>\';
                    } else {
                        status.innerHTML = \'<span class="badge badge-danger bg-danger text-white" style="font-size: 0.9em; padding: 6px 12px;"><i class="fa fa-times-circle"></i> \' + result.message + \'</span>\';
                    }
                } catch (e) {
                    status.innerHTML = \'<span class="badge badge-danger bg-danger text-white" style="font-size: 0.9em; padding: 6px 12px;"><i class="fa fa-times-circle"></i> Error inesperado: \' + xhr.status + \'</span>\';
                }
            }
        };
        xhr.send();
    });
});
</script>';

        return $html;
    }
}
