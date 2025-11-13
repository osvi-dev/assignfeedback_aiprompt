<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_assignfeedback_aiprompt_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    
    return true;
}