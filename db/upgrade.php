<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_assignfeedback_aiprompt_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    
    if ($oldversion < 2025060200) {
        // Crear la tabla assignfeedback_aiprompt_stu para feedback del estudiante
        $table = new xmldb_table('assignfeedback_aiprompt_stu');
        
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('assignment', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('studentfeedback', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('assignment', XMLDB_KEY_FOREIGN, ['assignment'], 'assign', ['id']);
        
        $table->add_index('assignment_user_idx', XMLDB_INDEX_NOTUNIQUE, ['assignment', 'userid']);
        
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        
        upgrade_plugin_savepoint(true, 2025060200, 'assignfeedback', 'aiprompt');
    }
    
    if ($oldversion < 2025060201) {
        // Nueva versión: se registró el hook para inyectar el botón del estudiante
        // en mod/assign/view.php. No se requieren cambios de BD.
        upgrade_plugin_savepoint(true, 2025060201, 'assignfeedback', 'aiprompt');
    }
    
    return true;
}