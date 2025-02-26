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
require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/plagiarismlib.php');
require_once($CFG->dirroot.'/plagiarism/sst/lib.php');
require_once($CFG->dirroot.'/plagiarism/sst/classes/forms/sst_setupform.php');

require_login();

$context = context_system::instance();

require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");
// set Page
$PAGE->set_url(new moodle_url(__DIR__.'/plagiarism/sst/settings.php'));
$PAGE->set_context(\context_system::instance());
$PAGE->set_title(get_string('setting', 'plagiarism_sst'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'plagiarism_sst'), '2', 'main');

//load Plugin Settings
$plagiarism_plugin_sst = new plagiarism_plugin_sst();
$plugindefaults = $plagiarism_plugin_sst->get_settings();
$pluginconfig = get_config('plagiarism_sst');

// load Form
$setupform = new sst_setupform();
// save Form Data
if (($data = $setupform->get_data()) && confirm_sesskey()) {
    $setupform->save($data);
    echo $OUTPUT->notification(get_string('savesuccess', 'plagiarism_sst'), 'notifysuccess');
}

// if Form cancelled
if ($setupform->is_cancelled()) {
    redirect(new moodle_url('/admin/category.php?category=plagiarism'));
}


if (!isset($pluginconfig->plagiarism_sst_studentdisclosure)) {
    $pluginconfig->plagiarism_sst_studentdisclosure =
        get_string('studentdisclosuredefault', 'plagiarism_sst');
}

//view Form
$setupform->set_data($pluginconfig);
echo $setupform->display();
echo $OUTPUT->footer();



