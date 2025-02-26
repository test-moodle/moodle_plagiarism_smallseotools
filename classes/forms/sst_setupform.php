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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/plagiarism/sst/lib.php');
require_once($CFG->libdir."/formslib.php");

class sst_setupform extends moodleform {

    // Define the form.
    public function definition() {
        global $DB, $CFG;

        $mform = $this->_form;

        $mform->disable_form_change_checker();

        $mform->addElement('header', 'config', get_string('sstconfig', 'plagiarism_sst'));
        $mform->addElement('html', get_string('sstexplain', 'plagiarism_sst').'</br></br>');

        // Loop through all modules that support Plagiarism.
        $mods = array_keys(core_component::get_plugin_list('mod'));

        foreach ($mods as $mod) {
            if (plugin_supports('mod', $mod, FEATURE_PLAGIARISM)) {
                $mform->addElement('advcheckbox',
                    'plagiarism_sst_mod_'.$mod,
                    get_string('usesst_mod', 'plagiarism_sst', ucfirst($mod)),
                    '',
                    null,
                    array(0, 1)
                );
            }
        }

        $mform->addElement(
            'textarea',
            'plagiarism_sst_studentdisclosure',
            get_string('studentdisclosure', 'plagiarism_sst')
        );
        $mform->addHelpButton(
            'plagiarism_sst_studentdisclosure',
            'studentdisclosure',
            'plagiarism_sst'
        );

        $mform->addElement('header', 'plagiarism_sstconfig', get_string('sstaccountconfig', 'plagiarism_sst'));
        $mform->setExpanded('plagiarism_sstconfig');

        $mform->addElement('text', 'plagiarism_sst_publickey', get_string('sstpublickey', 'plagiarism_sst'));
        $mform->setType('plagiarism_sst_publickey', PARAM_TEXT);
        $mform->addElement('passwordunmask', 'plagiarism_sst_secretkey', get_string('sstsecretkey', 'plagiarism_sst'));

//        $mform->addElement('button', 'connection_test', get_string("connecttest", 'plagiarism_sst'));

        $this->add_action_buttons();
    }

    /**
     * Display the form, saving the contents of the output buffer overriding Moodle's
     * display function that prints to screen when called
     *
     * @return the form as an object to print to screen at our convenience
     */
    public function display() {
        ob_start();
        parent::display();
        $form = ob_get_contents();
        ob_end_clean();

        return $form;
    }

    /**
     * Save the plugin config data
     */
    public function save($data) {
        global $CFG;
        global $DB;


        // Save whether the plugin is enabled for individual modules.
        $mods = array_keys(core_component::get_plugin_list('mod'));
        $pluginenabled = 0;
        foreach ($mods as $mod) {
            if (plugin_supports('mod', $mod, FEATURE_PLAGIARISM)) {
                $property = "plagiarism_sst_mod_" . $mod;
                ${ "plagiarism_sst_mod_" . "$mod" } = (!empty($data->$property)) ? $data->$property : 0;
                set_config('plagiarism_sst_mod_'.$mod, ${ "plagiarism_sst_mod_" . "$mod" }, 'plagiarism_sst');
                if (${ "plagiarism_sst_mod_" . "$mod" }) {
                    $pluginenabled = 1;
                }
            }
        }

        // save whether plugin is enabled or not in DB
        $defaultfield = new stdClass();
        $defaultfield->name = 'plagiarism_sst_enable';
        $defaultfield->value = $pluginenabled;
        $id = $DB->get_field(TABLE_SST_CONFIG, 'id', (array('cm' => null, 'name' => 'plagiarism_sst_enable')));
        if($id) {
            $defaultfield->id = $id;
            $DB->update_record(TABLE_SST_CONFIG, $defaultfield);
        }
        else {
            $DB->insert_record(TABLE_SST_CONFIG, $defaultfield);
        }

        // misc configs
        set_config('enabled', $pluginenabled, 'plagiarism_sst');
        // TODO: Remove sst_use completely when support for 3.8 is dropped.
        if ($CFG->branch < 39) {
            set_config('sst_use', $pluginenabled, 'plagiarism');
        }
        $properties = array("publickey", "secretkey", "studentdisclosure");

        foreach ($properties as $property) {
            $property = "plagiarism_sst_".$property;
            set_config($property, $data->$property, 'plagiarism_sst');
        }
    }
}
