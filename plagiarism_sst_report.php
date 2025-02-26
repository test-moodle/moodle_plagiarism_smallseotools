<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * @package   plagiarism_sst
 * @copyright 2023, SmallSEOTools <support@smallseotools.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(dirname(__FILE__)) . '/../config.php');

require_once($CFG->dirroot . '/plagiarism/sst/constants.php');
require_once($CFG->dirroot . '/plagiarism/sst/lib.php');


// Get url params.
$externalid = required_param('externalid', PARAM_TEXT);
$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$modulename = required_param('modulename', PARAM_TEXT);
$viewmode = optional_param('view', 'course', PARAM_TEXT);
$errormessagestyle = 'color:red; display:flex; width:100%; justify-content:center;';


// Get instance modules.
$cm = get_coursemodule_from_id($modulename, $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

// Request login.
require_login($course, true, $cm);

// Setup page meta data.
$context = context_course::instance($cm->course);
$PAGE->set_course($course);
$PAGE->set_cm($cm);
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('sst-report-page');
$PAGE->set_url('/moodle/plagiarism/sst/plagiarism_sst_report.php', array(
    'cmid' => $cmid,
    'userid' => $userid,
    'externalid' => $externalid,
    'modulename' => $modulename
));

// Setup page title and header.
$user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
$fs = get_file_storage();
//$file = $fs->get_file_by_hash($identifier);
//if ($file) {
//    $filename = $file->get_filename();
//    $pagetitle = get_string('reportpagetitle', 'plagiarism_sst') . ' - ' . fullname($user) . ' - ' . $filename;
//} else {
$pagetitle = get_string('reportpagetitle', 'plagiarism_sst') . ' - ' . fullname($user);
//}
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

if ($viewmode == 'course') {
    echo $OUTPUT->header();
}

// sst course settings.
$modulesettings = $DB->get_records_menu(TABLE_SST_CONFIG, array('cm' => $cmid), '', 'name,value');

$isinstructor = plagiarism_plugin_sst::is_instructor($context);

$moduleenabled = 1;
//$moduleenabled = plagiarism_plugin_sst::is_plugin_configured('mod_' . $cm->modname);

// Check if sst plugin is disabled.
if (empty($moduleenabled) || empty($modulesettings['plagiarism_sst_enable'])) {
    echo html_writer::div(get_string('disabledformodule', 'plagiarism_sst'), null, array('style' => $errormessagestyle));
} else {
    // Incase students not allowed to see the plagiairsm score.
    if (!$isinstructor && empty($modulesettings['plagiarism_sst_allowstudentaccess'])) {
        echo html_writer::div(get_string('nopageaccess', 'plagiarism_sst'), null, array('style' => $errormessagestyle));
    } else {
        echo html_writer::tag(
            'iframe',
            null,
            array(
                'title' => 'sst Report',
                'srcdoc' =>
                    "<form target='_self'" .
                    "method='GET'" .
                    "style='display: none;'" .
                    "action=".PLAGIARISM_SST_API_BASE_URL."/plag/scan/mdl/report/".$externalid.">" .
                    "</form>" .
                    "<script type='text/javascript'>" .
                    "window.document.forms[0].submit();" .
                    "</script>",
                'style' =>
                    $viewmode == 'course' ?
                        'width: 100%; height: calc(100vh - 87px); margin: 0px; padding: 0px; border: none;' :
                        'width: 100%; height: 100%; margin: 0px; padding: 0px; border: none;'
            )
        );


        if ($viewmode == 'course') {
            echo html_writer::link(
                "$CFG->wwwroot/plagiarism/sst/plagiarism_sst_report.php" .
                    "?cmid=$cmid&userid=$userid&externalid=$externalid&modulename=$modulename&view=fullscreen",
                get_string('openfullscreen', 'plagiarism_sst'),
                array('title' => get_string('openfullscreen', 'plagiarism_sst'))
            );
        }

    }
}

// Output footer.
if ($viewmode == 'course') {
    echo $OUTPUT->footer();
}

if ($viewmode == 'fullscreen') {
    echo html_writer::script(
        "window.document.body.style.margin=0;"
    );
}
