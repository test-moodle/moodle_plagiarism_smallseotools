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

/*
 * @package   plagiarism_sst
 * @copyright 2023, SmallSEOTools <support@smallseotools.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

//get global class
global $CFG;
global $DB;
require_once $CFG->dirroot.'/plagiarism/sst/constants.php';
require_once $CFG->dirroot.'/plagiarism/lib.php';

///// SST Class ////////////////////////////////////////////////////
class plagiarism_plugin_sst extends plagiarism_plugin
{
    /**
     * hook to allow plagiarism specific information to be displayed beside a submission.
     *
     * @return string
     */
    public function get_links($linkarray)
    {
        global $DB, $OUTPUT, $CFG;
        $output = '';
        $cmid = $linkarray['cmid'];
        $userid = $linkarray['userid'];

        $file = $linkarray['file'];
        $identifier = $file->get_pathnamehash();

        // Don't show links for certain file types as they won't have been submitted to SST.
        if (!empty($linkarray['file'])) {
            $file = $linkarray['file'];
            $filearea = $file->get_filearea();
            $nonsubmittingareas = ['feedback_files', 'introattachment'];
            if (in_array($filearea, $nonsubmittingareas)) {
                return $output;
            }
        }

        $component = (!empty($linkarray['component'])) ? $linkarray['component'] : '';

        // Exit if this is a quiz and quizzes are disabled.
        if ($component == 'qtype_essay' && empty($this->get_config_settings('mod_quiz'))) {
            return $output;
        }

        // If this is a quiz, retrieve the cmid
        if ($component == 'qtype_essay' && !empty($linkarray['area']) && empty($linkarray['cmid'])) {
            $questions = question_engine::load_questions_usage_by_activity($linkarray['area']);

            // Try to get cm using the questions owning context.
            $context = $questions->get_owning_context();
            if (empty($linkarray['cmid']) && $context->contextlevel == CONTEXT_MODULE) {
                $linkarray['cmid'] = $context->instanceid;
            }
        }

        // Get the course module.
        static $coursemodule;
        if (empty($coursemodule)) {
            $coursemodule = get_coursemodule_from_id(
                '',
                $linkarray['cmid']
            );
        }

        // Get sst module config.
        static $modulesettings;
        if (empty($clmodulesettings)) {
            $modulesettings = $DB->get_records_menu(
                TABLE_SST_CONFIG,
                ['cm' => $linkarray['cmid']],
                '',
                'name,value'
            );
        }

        // Get sst plugin admin config.
        static $adminconfig;
        if (empty($adminconfig)) {
            $adminconfig = self::plagiarism_sst_admin_config();
        }

        // Is sst plugin enabled for this module type?
        static $ismodenabled;
        if (empty($ismodenabled)) {
            $moduleconfigname = 'plagiarism_sst_mod_'.$coursemodule->modname;
            if (!isset($adminconfig->$moduleconfigname) || $adminconfig->$moduleconfigname !== '1') {
                // Plugin not enabled for this module.
                $ismodenabled = false;
            } else {
                $ismodenabled = true;
            }
        }

        // Exit if plugin is disabled or only disabled for this module.
        $enabledproperty = 'plagiarism_sst_enable';
        if (empty($ismodenabled) || empty($modulesettings[$enabledproperty])) {
            return $output;
        }

        // Init context.
        static $ctx;
        if (empty($ctx)) {
            $ctx = context_course::instance($coursemodule->course);
        }

        // Check current user if instructor.
        static $isinstructor;
        if (empty($isinstructor)) {
            $isinstructor = self::is_instructor($ctx);
        }

        // Incase of students, check if he is allowed to view the plagiairsm report progress & results.
        if (!$isinstructor && empty($modulesettings['plagiarism_sst_allowstudentaccess'])) {
            return;
        }

        $result = $DB->get_records(TABLE_SST_FILES, ['cm' => $cmid, 'userid' => $userid, 'identifier' => $identifier],
            'lastmodified DESC', '*'
        );
        //dd($result);

        $submittedfile = current($result);

        $score = '';
        if ($submittedfile) {
            $status = $submittedfile->statuscode;
            if ($submittedfile->externalid) {
                if ($submittedfile->similarityscore <= 50) {
                    $class = 'low';
                } elseif ($submittedfile->similarityscore <= 70) {
                    $class = 'middle';
                } elseif ($submittedfile->similarityscore <= 100) {
                    $class = 'high';
                }
                $reporturl = "$CFG->wwwroot/plagiarism/sst/plagiarism_sst_report.php".
                    "?cmid=$submittedfile->cm&userid=$submittedfile->userid".
                    "&externalid=$submittedfile->externalid&modulename=$coursemodule->modname";

                $similaritystr = get_string('similaritystr', 'plagiarism_sst');
                $viewreportstr = get_string('viewreportstr', 'plagiarism_sst');
                $clickstr = get_string('clickstr', 'plagiarism_sst');
                $score = html_writer::tag(
                    'div',
                    '<br>
                                '.$similaritystr.':<span class='.$class.'>
                                    '.$submittedfile->similarityscore.'% <span></span>
                                </span>
                         
                             <br>
                                <p>'.$viewreportstr.': <a href="'.$reporturl.'">'.$clickstr.'</a> </p>
                            ',
                    ['class' => 'score']
                );
            }

            $statusstr = get_string('sststatus', 'plagiarism_sst').': '.get_string($status, 'plagiarism_sst');
            $output .= html_writer::tag('div', $OUTPUT->pix_icon('logo', $statusstr, 'plagiarism_sst', ['class' => 'icon_size']).$statusstr.$score,
                ['class' => 'sst_status ']);
        }

        return $output;
    }

    /**
     * check if current module user is instructor.
     *
     * @param mixed $context
     *
     * @return bool is instructor?
     */
    public static function is_instructor($context)
    {
        return has_capability('mod/assign:grade', $context);
    }

    /* hook to save plagiarism specific settings on a module settings page
     * @param object $data - data from an mform submission.
    */
    public function save_form_elements($data)
    {
        global $DB;

        // Check if plugin is configured and enabled.
        if (empty($data->modulename) || !$this->is_plugin_configured('mod_'.$data->modulename)) {
            return;
        }

        $default = [];
        $default['plagiarism_sst_enable'] = $data->plagiarism_sst_enable;
        $default['plagiarism_sst_draftsubmit'] = isset($data->plagiarism_sst_draftsubmit) ? $data->plagiarism_sst_draftsubmit : 0;
        $default['plagiarism_sst_reportgen'] = isset($data->plagiarism_sst_reportgen) ? $data->plagiarism_sst_reportgen : 0;
        $default['plagiarism_sst_allowstudentaccess'] = $data->plagiarism_sst_allowstudentaccess;

        // Get saved db settings.
        $cmid = $data->coursemodule;
        $saveddefaultvalue = $DB->get_records_menu(TABLE_SST_CONFIG, ['cm' => $cmid], '', 'name,value');

        // Db settings elements name.
        $configfields = self::get_config_db_properties();

        // Save db settings.
        foreach ($configfields as $f) {
            if (isset($default[$f])) {
                $savedfield = new stdClass();
                $savedfield->cm = $cmid;
                $savedfield->name = $f;
                $savedfield->value = $default[$f];

                if (!isset($saveddefaultvalue[$f])) {
                    $savedfield->config_hash = $savedfield->cm.'_'.$savedfield->name;
                    if (!$DB->insert_record(TABLE_SST_CONFIG, $savedfield)) {
                        throw new moodle_exception(get_string('inserterror', 'plagiarism_sst'));
                    }
                } else {
                    $savedfield->id = $DB->get_field(
                        TABLE_SST_CONFIG,
                        'id',
                        ([
                            'cm' => $cmid,
                            'name' => $f,
                        ])
                    );
                    if (!$DB->update_record(TABLE_SST_CONFIG, $savedfield)) {
                        throw new moodle_exception(get_string('updateerror', 'plagiarism_sst'));
                    }
                }
            }
        }
    }

    public static function plagiarism_supported_mods()
    {
        $supported_mods = [];
        $mods = array_keys(core_component::get_plugin_list('mod'));
        foreach ($mods as $mod) {
            if (plugin_supports('mod', $mod, FEATURE_PLAGIARISM)) {
                array_push($supported_mods, $mod);
            }
        }

        return $supported_mods;
    }

    public static function get_config_db_properties()
    {
        return [
            'plagiarism_sst_enable',
            'plagiarism_sst_draftsubmit',
            'plagiarism_sst_reportgen',
            'plagiarism_sst_allowstudentaccess',
        ];
    }

    /**
     * hook to add plagiarism specific settings to a module settings page.
     *
     * @param object $mform   - Moodle form
     * @param object $context - current context
     */
    public function get_form_elements_module($mform, $context, $modulename = '')
    {
        global $DB, $PAGE, $COURSE;

        // This is a bit of a hack and untidy way to ensure the form elements aren't displayed,
        // twice. This won't be needed once this method goes away.
        // TODO: Remove once this method goes away.
        static $settingsdisplayed;
        if ($settingsdisplayed) {
            return;
        }

        if (
            has_capability('plagiarism/sst:enable', $context)
            && in_array(str_replace('mod_', '', $modulename), self::plagiarism_supported_mods())
        ) {
            // Return no form if the plugin isn't configured or not enabled.
            if (empty($modulename) || !$this->is_plugin_configured($modulename)) {
                return;
            }

            $mform->addElement(
                'header',
                'plagiarism_sst_defaultsettings',
                get_string('coursesettings', 'plagiarism_sst')
            );

            // Database settings.
            $mform->addElement(
                'advcheckbox',
                'plagiarism_sst_enable',
                get_string('enable', 'plagiarism_sst')
            );

            // Add draft submission properties only if exists.
            if ($mform->elementExists('submissiondrafts')) {
                $mform->addElement(
                    'advcheckbox',
                    'plagiarism_sst_draftsubmit',
                    get_string('draftsubmit', 'plagiarism_sst')
                );
                $mform->addHelpButton(
                    'plagiarism_sst_draftsubmit',
                    'draftsubmit',
                    'plagiarism_sst'
                );
                $mform->disabledIf(
                    'plagiarism_sst_draftsubmit',
                    'submissiondrafts',
                    'eq',
                    0
                );
            }

            // Add due date properties only if exists.
            if ($mform->elementExists('duedate')) {
                $genoptions = [
                    0 => get_string('genereportimmediately', 'plagiarism_sst'),
                    1 => get_string('genereportonduedate', 'plagiarism_sst'),
                ];
                $mform->addElement(
                    'select',
                    'plagiarism_sst_reportgen',
                    get_string('reportgenspeed', 'plagiarism_sst'),
                    $genoptions
                );
            }

            $mform->addElement(
                'advcheckbox',
                'plagiarism_sst_allowstudentaccess',
                get_string('allowstudentaccess', 'plagiarism_sst')
            );

            $cmid = optional_param('update', null, PARAM_INT);
            $savedvalues = $DB->get_records_menu(TABLE_SST_CONFIG, ['cm' => $cmid], '', 'name,value');

            if (count($savedvalues) > 0) {
                $mform->setDefault(
                    'plagiarism_sst_enable',
                    isset($savedvalues['plagiarism_sst_enable']) ? $savedvalues['plagiarism_sst_enable'] : 0
                );

                $draftsubmit = isset($savedvalues['plagiarism_sst_draftsubmit']) ?
                    $savedvalues['plagiarism_sst_draftsubmit'] : 0;

                $mform->setDefault('plagiarism_sst_draftsubmit', $draftsubmit);
                if (isset($savedvalues['plagiarism_sst_reportgen'])) {
                    $mform->setDefault('plagiarism_sst_reportgen', $savedvalues['plagiarism_sst_reportgen']);
                }
                if (isset($savedvalues['plagiarism_sst_allowstudentaccess'])) {
                    $mform->setDefault(
                        'plagiarism_sst_allowstudentaccess',
                        $savedvalues['plagiarism_sst_allowstudentaccess']
                    );
                }
            } else {
                $mform->setDefault('plagiarism_sst_enable', false);
                $mform->setDefault('plagiarism_sst_draftsubmit', 0);
                $mform->setDefault('plagiarism_sst_reportgen', 0);
                $mform->setDefault('plagiarism_sst_allowstudentaccess', 0);
            }

            $settingsdisplayed = true;
        }
    }

    /**
     * hook to allow a disclosure to be printed notifying users what will happen with their submission.
     *
     * @param int $cmid - course module id
     *
     * @return string
     */
    public function print_disclosure($cmid)
    {
        global $OUTPUT, $DB, $USER;

        $cm = get_coursemodule_from_id('', $cmid);

        // Get course module SST settings.
        $modulesettings = $DB->get_records_menu(
            TABLE_SST_CONFIG,
            ['cm' => $cmid],
            '',
            'name,value'
        );
        // Check if SST plugin is enabled for this module.
        $moduleenabled = $this->is_plugin_configured('mod_'.$cm->modname);
        if (empty($modulesettings['plagiarism_sst_enable']) || empty($moduleenabled)) {
            return '';
        }

        $plagiarismsettings = (array) get_config('plagiarism_sst');

        $isuseragreed = $this->is_user_eula_accepted($USER->id);

        if (!$isuseragreed) {
            if (isset($plagiarismsettings->plagiarism_sst_studentdisclosure)) {
                $studentdisclosure = $plagiarismsettings->plagiarism_sst_studentdisclosure;
            } else {
                $studentdisclosure = get_string('studentdisclosuredefault', 'plagiarism_sst');
            }
        } else {
            $studentdisclosure = get_string('studentdagreedtoeula', 'plagiarism_sst');
        }

        $contents = format_text($studentdisclosure, FORMAT_MOODLE, ['noclean' => true]);

        if (!$isuseragreed) {
            $checkbox = "<input type='checkbox' id='student_disclosure'>".
                "<label for='student_disclosure' class='student-disclosure-checkbox'>$contents</label>";
            $output = html_writer::tag('div', $checkbox, ['class' => 'student-disclosure ']);
            $output .= html_writer::tag(
                'script',
                '(function disableInput() {'.
                'setTimeout(() => {'.
                "var checkbox = document.getElementById('student_disclosure');".
                "var btn = document.getElementById('id_submitbutton');".
                'btn.disabled = true;'.
                'var intrval = setInterval(() => {'.
                'if(checkbox.checked){'.
                'btn.disabled = false;'.
                '}else{'.
                'btn.disabled = true;'.
                '}'.
                '}, 1000)'.
                '}, 500);'.
                '}());',
                null
            );
        } else {
            $output = html_writer::tag('div', $contents, ['class' => 'student-disclosure']);
        }

        return $output;
    }

    /**
     * hook to allow status of submitted files to be updated - called on grading/report pages.
     *
     * @param object $course - full Course object
     * @param object $cm     - full cm object
     */
    public function update_status($course, $cm)
    {
        //called at top of submissions/grading pages - allows printing of admin style links or updating status
    }

    /**
     * @return mixed the admin config settings for the plugin
     */
    public static function plagiarism_sst_admin_config()
    {
        return get_config('plagiarism_sst');
    }

    /**
     * Check if plugin has been enabled with sst .
     *
     * @return bool whether the plugin is enabled for sst
     **/
    public function is_plugin_enabled()
    {
        $config = self::plagiarism_sst_admin_config();
        if (isset($config->enabled)) {
            return true;
        }

        return false;
    }

    /**
     * Check if plugin has been configured with sst account details.
     *
     * @return bool whether the plugin is configured for sst
     **/
    public function is_plugin_configured($modulename = null)
    {
        $config = self::plagiarism_sst_admin_config();

        if (empty($config->plagiarism_sst_secretkey)) {
            return false;
        }

        if ($modulename != null) {
            $moduleconfigname = 'plagiarism_sst_'.$modulename;
            if (!isset($config->$moduleconfigname) || $config->$moduleconfigname !== '1') {
                // Plugin not enabled for this module.
                return false;
            }
        }

        return true;
    }

    /**
     * Get the configuration settings for the plagiarism plugin.
     *
     * @return mixed if plugin is enabled then an array of config settings is returned or false if not
     */
    public static function get_config_settings($modulename)
    {
        $pluginconfig = get_config('plagiarism_sst', 'plagiarism_sst_'.$modulename);

        return $pluginconfig;
    }

    /**
     * Get the SST settings for a module.
     *
     * @param int  $cmid            - the course module id, if this is 0 the default settings will be retrieved
     * @param bool $uselockedvalues - use locked values in place of saved values
     *
     * @return array of sst settings for a module
     */
    public function get_settings($cmid = null, $uselockedvalues = true)
    {
        global $DB;
        $defaults = $DB->get_records_menu(TABLE_SST_CONFIG, ['cm' => null], '', 'name,value');
        $settings = $DB->get_records_menu(TABLE_SST_CONFIG, ['cm' => $cmid], '', 'name,value');

        // Don't overwrite settings with locked values (only relevant on inital module creation).
        if ($uselockedvalues == false) {
            return $settings;
        }

        // Enforce site wide config locking.
        foreach ($defaults as $key => $value) {
            if (substr($key, -5) !== '_lock') {
                continue;
            }
            if ($value != 1) {
                continue;
            }
            $setting = substr($key, 0, -5);
            $settings[$setting] = $defaults[$setting];
        }

        return $settings;
    }

    /**
     * Check if it is possible for students to accept EULA in a specific module.
     *
     * @param string $modname module type name
     *
     * @return bool is allowed
     */
    public function is_allowed_eula_acceptance($modname)
    {
        $supportedeulamodules = ['assign', 'workshop'];

        return in_array($modname, $supportedeulamodules);
    }

    /**
     * Update in Database that the user accepted the EULA.
     *
     * @param string userid
     */
    public function upsert_user_eula($userid)
    {
        global $DB;
        $id = $DB->get_field(TABLE_SST_USERS, 'id', (['userid' => $userid]));

        $defaultfield = new stdClass();
        $defaultfield->userid = $userid;
        $defaultfield->eula_accepted = 1;

        if ($id) {
            $defaultfield->id = $id;
            $DB->update_record(TABLE_SST_USERS, $defaultfield);
        } else {
            $DB->insert_record(TABLE_SST_USERS, $defaultfield);
        }

        return true;
    }

    /**
     * Check if the  eula of the user is accepted.
     *
     * @param string userid check eula version by user Moodle id
     *
     * @return bool
     */
    public function is_user_eula_accepted($userid)
    {
        global $DB;

        $user = $DB->get_record(TABLE_SST_USERS, ['userid' => $userid]);
        if (!$user || !isset($user)) {
            return false;
        }

        return true;
    }

    private function create_new_submission($cm, $userid, $identifier, $submissiontype, $studentread, $scheduledscandate)
    {
        global $DB;

        $plagiarismfile = new stdClass();
        $plagiarismfile->cm = $cm->id;
        $plagiarismfile->userid = $userid;
        $plagiarismfile->identifier = $identifier;
        $plagiarismfile->statuscode = 'queued';
        $plagiarismfile->similarityscore = null;
        $plagiarismfile->attempt = 0; // This will be incremented when saved.
        $plagiarismfile->transmatch = 0;
        $plagiarismfile->submissiontype = $submissiontype;
        $plagiarismfile->studentread = $studentread;
        $plagiarismfile->duedatescan = $scheduledscandate;

        if (!$fileid = $DB->insert_record(TABLE_SST_FILES, $plagiarismfile)) {
            plagiarism_sst_activitylog('Insert record failed (CM: '.$cm->id.', User: '.$userid.')', 'PP_NEW_SUB');
            $fileid = 0;
        }

        return $fileid;
    }

    private function reset_submission($cm, $userid, $identifier, $currentsubmission, $submissiontype)
    {
        global $DB;

        $plagiarismfile = new stdClass();
        $plagiarismfile->id = $currentsubmission->id;
        $plagiarismfile->identifier = $identifier;
        $plagiarismfile->statuscode = 'pending';
        $plagiarismfile->similarityscore = null;
        if ($currentsubmission->statuscode != 'error') {
            $plagiarismfile->attempt = 1;
        }
        $plagiarismfile->transmatch = 0;
        $plagiarismfile->submissiontype = $submissiontype;
        $plagiarismfile->orcapable = null;
        $plagiarismfile->errormsg = null;
        $plagiarismfile->errorcode = null;

        if (!$DB->update_record(TABLE_SST_FILES, $plagiarismfile)) {
            plagiarism_sst_activitylog('Update record failed (CM: '.$cm->id.', User: '.$userid.')', 'PP_REPLACE_SUB');
        }
    }

    public function plagiarism_sst_retrieve_successful_submissions($author, $cmid, $identifier)
    {
        global $CFG, $DB;

        // Check if the same answer has been submitted previously. Remove if so.
        list($insql, $inparams) = $DB->get_in_or_equal(['success', 'queued'], SQL_PARAMS_QM, 'param', false);
        $typefield = ($CFG->dbtype == 'oci') ? ' to_char(statuscode) ' : ' statuscode ';

        $plagiarismfiles = $DB->get_records_select(
            TABLE_SST_FILES,
            ' userid = ? AND cm = ? AND identifier = ? AND '.$typefield.' '.$insql,
            array_merge([$author, $cmid, $identifier], $inparams)
        );

        return $plagiarismfiles;
    }

    /**
     * Queue submissions to send to SST.
     *
     * @param $cm
     * @param $author
     * @param $submitter
     * @param $identifier
     * @param $submissiontype
     * @param int $itemid
     *
     * @return bool
     */
    public function queue_submission($cm, $author, $submitter, $identifier, $submissiontype, $itemid = 0, $eventtype = null, $scheduledscandate = 0)
    {
        global $CFG, $DB;
        $errorcode = null;
        $attempt = 0;
        $externalid = null;

        $coursemodule = get_coursemodule_from_id('', $cm->id);
        $coursedata = $DB->get_record('course', ['id' => $coursemodule->course]);

        // Check the supported EULA acceptance module.
        if ($this->is_allowed_eula_acceptance($coursemodule->modname)) {
            $this->upsert_user_eula($author);
        }

        // Check if file has been submitted before.
        $plagiarismfiles = $this->plagiarism_sst_retrieve_successful_submissions($author, $cm->id, $identifier);

        if (count($plagiarismfiles) > 0) {
            return true;
        }

        $settings = $this->get_settings($cm->id);
        // check if student read report
        $studentread = isset($settings['plagiarism_sst_allowstudentaccess']) ? $settings['plagiarism_sst_allowstudentaccess'] : 0;
        // Get module data.
        $moduledata = $DB->get_record($cm->modname, ['id' => $cm->instance]);
        $moduledata->resubmission_allowed = false;

        if ($cm->modname == 'assign') {
            // Group submissions require userid = 0 when checking assign_submission.
            $userid = ($moduledata->teamsubmission) ? 0 : $author;

            if (!isset($_SESSION['moodlesubmissionstatus'])) {
                $_SESSION['moodlesubmissionstatus'] = null;
            }

            if ($eventtype == 'content_uploaded' || $eventtype == 'file_uploaded') {
                $moodlesubmission = $DB->get_record('assign_submission',
                    ['assignment' => $cm->instance,
                        'userid' => $userid,
                        'id' => $itemid, ], 'status');

                $_SESSION['moodlesubmissionstatus'] = $moodlesubmission->status;
            }

            if ($eventtype != 'content_uploaded' && $eventtype != 'file_uploaded') {
                unset($_SESSION['moodlesubmissionstatus']);
            }
        } else {
            $userid = $author;
        }

        // Work out submission method.
        // If this file has successfully submitted in the past then break, text content is to be submitted.
        switch ($submissiontype) {
            case 'file':
            case 'text_content':

                // Get file data or prepare text submission.
                if ($submissiontype == 'file') {
                    $fs = get_file_storage();
                    $file = $fs->get_file_by_hash($identifier);

                    $timemodified = $file->get_timemodified();
                    $filename = $file->get_filename();
                } else {
                    // Check when text submission was last modified.
                    switch ($cm->modname) {
                        case 'assign':
                            $moodlesubmission = $DB->get_record('assign_submission',
                                ['assignment' => $cm->instance,
                                    'userid' => $userid,
                                    'id' => $itemid, ], 'timemodified');
                            break;
                        case 'workshop':
                            $moodlesubmission = $DB->get_record('workshop_submissions',
                                ['workshopid' => $cm->instance,
                                    'authorid' => $userid, ], 'timemodified');
                            break;
                    }

                    $timemodified = $moodlesubmission->timemodified;
                }

                // Get submission method depending on whether there has been a previous submission.
                $submissionfields = 'id, cm, externalid, identifier, statuscode, lastmodified, attempt';
                $typefield = ($CFG->dbtype == 'oci') ? ' to_char(submissiontype) ' : ' submissiontype ';

                // Check if this content/file has been submitted previously.
                $previoussubmissions = $DB->get_records_select(TABLE_SST_FILES,
                    ' cm = ? AND userid = ? AND '.$typefield.' = ? AND identifier = ?',
                    [$cm->id, $author, $submissiontype, $identifier],
                    'id', $submissionfields);
                $previoussubmission = end($previoussubmissions);

                if ($previoussubmission) {
                    // Don't submit if submission hasn't changed.
                    if (in_array($previoussubmission->statuscode, ['success', 'error'])
                        && $timemodified <= $previoussubmission->lastmodified) {
                        return true;
                    } elseif ($moduledata->resubmission_allowed) {
                        // Replace submission in the specific circumstance where SST can accommodate resubmissions.
                        $submissionid = $previoussubmission->id;
                        $this->reset_submission($cm, $author, $identifier, $previoussubmission, $submissiontype);
                        $externalid = $previoussubmission->externalid;
                    } else {
                        if ($previoussubmission->statuscode != 'success') {
                            $submissionid = $previoussubmission->id;
                            $this->reset_submission($cm, $author, $identifier, $previoussubmission, $submissiontype);
                        } else {
                            $submissionid = $this->create_new_submission($cm, $author, $identifier, $submissiontype, $studentread, $scheduledscandate);
                            $externalid = $previoussubmission->externalid;
                        }
                    }
                    $attempt = $previoussubmission->attempt;
                } else {
                    // Check if there is previous submission of different content which we may be able to replace.
                    $typefield = ($CFG->dbtype == 'oci') ? ' to_char(submissiontype) ' : ' submissiontype ';
                    if ($previoussubmission = $DB->get_record_select(TABLE_SST_FILES,
                        ' cm = ? AND userid = ? AND '.$typefield.' = ?',
                        [$cm->id, $author, $submissiontype],
                        'id, cm, externalid, identifier, statuscode, lastmodified, attempt')) {
                        $submissionid = $previoussubmission->id;
                        $attempt = $previoussubmission->attempt;

                        // Replace submission in the specific circumstance where SST can accomodate resubmissions.
                        if ($moduledata->resubmission_allowed || $submissiontype == 'text_content') {
                            $this->reset_submission($cm, $author, $identifier, $previoussubmission, $submissiontype);
                            $externalid = $previoussubmission->externalid;
                        } else {
                            $submissionid = $this->create_new_submission($cm, $author, $identifier, $submissiontype, $studentread, $scheduledscandate);
                        }
                    } else {
                        $submissionid = $this->create_new_submission($cm, $author, $identifier, $submissiontype, $studentread, $scheduledscandate);
                    }
                }

                break;

            case 'forum_post':
            case 'quiz_answer':
                if ($previoussubmissions = $DB->get_records_select(TABLE_SST_FILES,
                    ' cm = ? AND userid = ? AND identifier = ? ',
                    [$cm->id, $author, $identifier],
                    'id DESC', 'id, cm, externalid, identifier, statuscode, attempt', 0, 1)) {
                    $previoussubmission = current($previoussubmissions);
                    if ($previoussubmission->statuscode == 'success') {
                        return true;
                    } else {
                        $submissionid = $previoussubmission->id;
                        $attempt = $previoussubmission->attempt;
                        $externalid = $previoussubmission->externalid;
                        $this->reset_submission($cm, $author, $identifier, $previoussubmission, $submissiontype);
                    }
                } else {
                    $submissionid = $this->create_new_submission($cm, $author, $identifier, $submissiontype, $studentread, $scheduledscandate);
                }
                break;
        }

        // Check file is less than maximum allowed size.
        if ($submissiontype == 'file') {
            if ($file->get_filesize() > PLAGIARISM_SST_MAX_FILE_UPLOAD_SIZE) {
                $errorcode = 2;
            }
        }

        // If applicable, check whether file type is accepted.
        $acceptanyfiletype = (!empty($settings['plagiarism_allow_non_or_submissions'])) ? 1 : 0;
        if (!$acceptanyfiletype && $submissiontype == 'file') {
            $filenameparts = explode('.', $filename);
            $fileext = strtolower(end($filenameparts));
            if (!in_array('.'.$fileext, ACCEPTED_FILE_EXTS)) {
                $errorcode = 4;
            }
        }

        // Save submission as queued or errored if we have an errorcode.
        $statuscode = ($errorcode != null) ? 'error' : 'queued';
        $errormsg = ($errorcode != null) ? get_string('errorcode'.$errorcode, 'plagiarism_sst') : null;

        return $this->save_submission($cm, $author, $submissionid, $identifier, $statuscode, $externalid, $submitter, $itemid,
            $submissiontype, $attempt, $studentread, $scheduledscandate, $errorcode, $errormsg);
    }

    public function event_handler($eventdata)
    {
        global $DB;

        $result = true;

        // Get the coursemodule, use a different method if in a quiz as we have the quiz id.
        if ($eventdata['other']['modulename'] == 'quiz') {
            $cm = get_coursemodule_from_instance($eventdata['other']['modulename'], $eventdata['other']['quizid']);
        } else {
            $cm = get_coursemodule_from_id($eventdata['other']['modulename'], $eventdata['contextinstanceid']);
        }

        // Remove the event if the course module no longer exists.
        if (!$cm) {
            return true;
        }
        $context = context_module::instance($cm->id);

        // Initialise module settings.
        $plagiarismsettings = $this->get_settings($cm->id);
        $moduleenabled = $this->get_config_settings('mod_'.$cm->modname);
        if ($cm->modname == 'assign') {
            $plagiarismsettings['plagiarism_sst_draftsubmit'] = (isset($plagiarismsettings['plagiarism_sst_draftsubmit'])) ? $plagiarismsettings['plagiarism_sst_draftsubmit'] : 0;
        }

        // Either module not using sst or sst not being used at all so return true to remove event from queue.
        if (empty($plagiarismsettings['plagiarism_sst_enable']) || empty($moduleenabled)) {
            return true;
        }

        // Get module data.
        $moduledata = $DB->get_record($cm->modname, ['id' => $cm->instance]);
        // generate report on due date
        if ($plagiarismsettings['plagiarism_sst_reportgen'] == 1) {
            $scheduledscandate = $moduledata->duedate - (1 * 60);
        } else {
            $scheduledscandate = 0;
        }

        if ($cm->modname != 'assign') {
            $moduledata->submissiondrafts = 0;
        }

        // Submit files only when students click the submit button
        if ($moduledata->submissiondrafts && $plagiarismsettings['plagiarism_sst_draftsubmit'] == 1 &&
            ($eventdata['eventtype'] == 'file_uploaded' || $eventdata['eventtype'] == 'content_uploaded')) {
            return true;
        }

        // Set the author and submitter.
        $submitter = $eventdata['userid'];
        $author = (!empty($eventdata['relateduserid'])) ? $eventdata['relateduserid'] : $eventdata['userid'];

        /*
           Related user ID will be NULL if an instructor submits on behalf of a student who is in a group.
           To get around this, we get the group ID, get the group members and set the author as the first student in the group.
        */
        if ((empty($eventdata['relateduserid'])) && ($cm->modname == 'assign')
            && has_capability('mod/assign:editothersubmission', $context, $submitter)) {
            $moodlesubmission = $DB->get_record('assign_submission', ['id' => $eventdata['objectid']], 'id, groupid');
            if (!empty($moodlesubmission->groupid)) {
                $author = $this->get_first_group_author($cm->course, $moodlesubmission->groupid);
            }
        }

        // Get actual text content and files to be submitted for draft submissions.
        // As this won't be present in eventdata for certain event types.
        if ($eventdata['other']['modulename'] == 'assign' && $eventdata['eventtype'] == 'assessable_submitted') {
            // Get content.
            $moodlesubmission = $DB->get_record('assign_submission', ['id' => $eventdata['objectid']], 'id');
            if ($moodletextsubmission = $DB->get_record('assignsubmission_onlinetext',
                ['submission' => $moodlesubmission->id], 'onlinetext')) {
                $eventdata['other']['content'] = $moodletextsubmission->onlinetext;
            }

            // Get Files.
            $eventdata['other']['pathnamehashes'] = [];
            $filesconditions = ['component' => 'assignsubmission_file',
                'itemid' => $moodlesubmission->id, 'userid' => $author, ];
            if ($moodlefiles = $DB->get_records('files', $filesconditions)) {
                foreach ($moodlefiles as $moodlefile) {
                    $eventdata['other']['pathnamehashes'][] = $moodlefile->pathnamehash;
                }
            }
        }

        // Queue every question submitted in a quiz attempt.
        if ($eventdata['eventtype'] == 'quiz_submitted') {
            $attempt = quiz_attempt::create($eventdata['objectid']);
            foreach ($attempt->get_slots() as $slot) {
                $qa = $attempt->get_question_attempt($slot);
                if ($qa->get_question()->get_type_name() != 'essay') {
                    continue;
                }
                $eventdata['other']['content'] = $qa->get_response_summary();

                // Queue text content.
                // adding slot to sha hash to create unique assignments for duplicate text based on it's id
                $identifier = sha1($eventdata['other']['content'].$slot);
                $result = $this->queue_submission(
                    $cm, $author, $submitter, $identifier, 'quiz_answer',
                    $eventdata['objectid'], $eventdata['eventtype'], $scheduledscandate);

                $files = $qa->get_last_qt_files('attachments', $context->id);
                foreach ($files as $file) {
                    // Queue file for sending to sst.
                    $identifier = $file->get_pathnamehash();
                    $result = $this->queue_submission(
                        $cm, $author, $submitter, $identifier, 'file',
                        $eventdata['objectid'], $eventdata['eventtype'], $scheduledscandate);
                }
            }
        }

        // Queue text content and forum posts to send to sst.
        if (in_array($eventdata['eventtype'], ['content_uploaded', 'assessable_submitted'])
            && !empty($eventdata['other']['content'])) {
            $submissiontype = ($cm->modname == 'forum') ? 'forum_post' : 'text_content';

            // TODO: Check eventdata to see if content is included correctly. If so, this can be removed.
            if ($cm->modname == 'workshop') {
                $moodlesubmission = $DB->get_record('workshop_submissions', ['id' => $eventdata['objectid']]);
                $eventdata['other']['content'] = $moodlesubmission->content;
            }

            $identifier = sha1($eventdata['other']['content']);

            // Check if content has been submitted before and return if so.
            $result = $this->queue_submission(
                $cm, $author, $submitter, $identifier, $submissiontype,
                $eventdata['objectid'], $eventdata['eventtype'], $scheduledscandate);
        }

        // Queue files to submit to sst.
        $result = $result && true;
        if (!empty($eventdata['other']['pathnamehashes'])) {
            foreach ($eventdata['other']['pathnamehashes'] as $pathnamehash) {
                $fs = get_file_storage();
                $file = $fs->get_file_by_hash($pathnamehash);

                if (!$file) {
                    plagiarism_sst_activitylog('File not found: '.$pathnamehash, 'PP_NO_FILE');
                    $result = true;
                    continue;
                } else {
                    try {
                        $file->get_content();
                    } catch (Exception $e) {
                        plagiarism_sst_activitylog('File content not found: '.$pathnamehash, 'PP_NO_FILE');
                        mtrace($e);
                        mtrace('File content not found. pathnamehash: '.$pathnamehash);
                        $result = true;
                        continue;
                    }
                }

                if ($file->get_filename() === '.') {
                    continue;
                }

                $result = $result && $this->queue_submission(
                        $cm, $author, $submitter, $pathnamehash, 'file', $eventdata['objectid'], $eventdata['eventtype'], $scheduledscandate);
            }
        }

        return $result;
    }

    /*
     * Related user ID will be NULL if an instructor submits on behalf of a student who is in a group.
     * To get around this, we get the group ID, get the group members and set the author as the first student in the group.

     * @param int $cmid - The course ID.
     * @param int $groupid - The ID of the Moodle group that we're getting from.
     * @return int $author The Moodle user ID that we'll be using for the author.
    */
    private function get_first_group_author($cmid, $groupid)
    {
        static $context;
        if (empty($context)) {
            $context = context_course::instance($cmid);
        }

        $groupmembers = groups_get_members($groupid, 'u.id');
        foreach ($groupmembers as $author) {
            if (!has_capability('mod/assign:grade', $context, $author->id)) {
                return $author->id;
            }
        }
    }

    public static function course_reset($eventdata)
    {
        global $DB, $CFG;
        $data = $eventdata->get_data();

        return true;
    }

    /**
     * Save the submission data to the files table.
     */
    public function save_submission($cm, $userid, $submissionid, $identifier, $statuscode, $externalid, $submitter, $itemid,
                                    $submissiontype, $attempt, $studentread = 1, $scheduledscandate = 0, $errorcode = null, $errormsg = null)
    {
        global $DB;

        $plagiarismfile = new stdClass();
        if ($submissionid != 0) {
            $plagiarismfile->id = $submissionid;
        }
        $plagiarismfile->cm = $cm->id;
        $plagiarismfile->userid = $userid;
        $plagiarismfile->identifier = $identifier;
        $plagiarismfile->statuscode = $statuscode;
        $plagiarismfile->similarityscore = null;
        $plagiarismfile->externalid = $externalid;
        $plagiarismfile->errorcode = (empty($errorcode)) ? null : $errorcode;
        $plagiarismfile->errormsg = (empty($errormsg)) ? null : $errormsg;
        $plagiarismfile->attempt = $attempt + 1;
        $plagiarismfile->transmatch = 0;
        $plagiarismfile->lastmodified = time();
        $plagiarismfile->submissiontype = $submissiontype;
        $plagiarismfile->itemid = $itemid;
        $plagiarismfile->submitter = $submitter;
        $plagiarismfile->studentread = $studentread;
        $plagiarismfile->duedatescan = $scheduledscandate;

        if ($submissionid != 0) {
            if (!$DB->update_record(TABLE_SST_FILES, $plagiarismfile)) {
                plagiarism_sst_activitylog('Update record failed (CM: '.$cm->id.', User: '.$userid.') - ', 'PP_UPDATE_SUB_ERROR');
            }
        } else {
            if (!$DB->insert_record(TABLE_SST_FILES, $plagiarismfile)) {
                plagiarism_sst_activitylog('Insert record failed (CM: '.$cm->id.', User: '.$userid.') - ', 'PP_INSERT_SUB_ERROR');
            }
        }

        return true;
    }

    /**
     * Update an errored submission in the files table.
     */
    public function save_errored_submission($submissionid, $attempt, $errorcode)
    {
        global $DB;

        $plagiarismfile = new stdClass();
        $plagiarismfile->id = $submissionid;
        $plagiarismfile->statuscode = 'error';
        $plagiarismfile->attempt = $attempt + 1;
        $plagiarismfile->errorcode = $errorcode;

        if (!$DB->update_record(TABLE_SST_FILES, $plagiarismfile)) {
            plagiarism_sst_activitylog('Update record failed (Submission: '.$submissionid.') - ', 'PP_UPDATE_SUB_ERROR');
        }

        return true;
    }

    /**
     * Handle Scheduled Task to Send Queued Submissions to SST.
     */
    public function send_queued_submissions()
    {
        global $CFG, $DB;

        $config = self::plagiarism_sst_admin_config();

        // Don't attempt to call  if a connection to sst could not be established.
//    if (!$this->test_SST_connection()) {
//        mtrace(get_string('ppeventsfailedconnection', 'plagiarism_sst'));
//        return;
//    }

        $currentdate = strtotime('now');
        $queueditems = $DB->get_records_select(TABLE_SST_FILES, '(statuscode = ? OR statuscode = ?) AND duedatescan < ?  ',
            [PLAGIARISM_SST_QUEUED_STATUS, PLAGIARISM_SST_PENDING_STATUS, $currentdate], 'lastmodified', '*', 0, PLAGIARISM_SST_CRON_SUBMISSIONS_LIMIT);

        // Submit each file individually to SST.
        foreach ($queueditems as $queueditem) {
            $user = $DB->get_record('user', ['id' => $queueditem->userid]);
            $errorcode = 0;
            // There should never not be a submission type, handle if there isn't just in case.
            if (!in_array($queueditem->submissiontype, ['file', 'text_content', 'forum_post', 'quiz_answer'])) {
                $errorcode = 11;
            }

            // Don't proceed if we can not find a cm.
            $cm = get_coursemodule_from_id('', $queueditem->cm);
            if (empty($cm)) {
                $this->save_errored_submission($queueditem->id, $queueditem->attempt, 12);

                // Output a message in the cron for failed submission to SST.
                $outputvars = new stdClass();
                $outputvars->id = $queueditem->id;
                $outputvars->cm = $queueditem->cm;
                $outputvars->userid = $queueditem->userid;

                plagiarism_sst_activitylog(get_string('errorcode12', 'plagiarism_sst', $outputvars), 'PP_NO_COURSE');
                continue;
            }

            // Get various settings that we need.
            $settings = $this->get_settings($cm->id);

            // Get module data.
            $moduledata = $DB->get_record($cm->modname, ['id' => $cm->instance]);
            $moduledata->resubmission_allowed = false;

            if ($cm->modname == 'assign') {
                // Group submissions require userid = 0 when checking assign_submission.
                $userid = ($moduledata->teamsubmission) ? 0 : $queueditem->userid;
            }

            // Get course data.
            $coursemodule = get_coursemodule_from_id('', $cm->id);
            $coursedata = $DB->get_record('course', ['id' => $coursemodule->course]);

            // Previously failed submissions may not have a value for submitter.
            if (empty($queueditem->submitter)) {
                $queueditem->submitter = $queueditem->userid;
            }

            // User Id should never be 0 but save as errored for old submissions where this may be the case.
            if (empty($queueditem->userid)) {
                $this->save_errored_submission($queueditem->id, $queueditem->attempt, 7);
                continue;
            }

            // Don't submit if a user has not accepted the eula.
//        if ($queueditem->userid == $queueditem->submitter && $user->useragreementaccepted != 1) {
//            $errorcode = 3;
//        }

            if (!empty($errorcode)) {
                // Save failed submission if user can not be joined to class or there was an error with the assignment.
                $this->save_errored_submission($queueditem->id, $queueditem->attempt, $errorcode);
                continue;
            }

            // Clean up old SST submission files.
            if ($queueditem->itemid != 0 && $queueditem->submissiontype == 'file' && $cm->modname != 'forum') {
                $this->clean_old_submissions($cm, $user->id, $queueditem->itemid, $queueditem->submissiontype,
                    $queueditem->identifier);
            }

            // Get more Submission Details as required.
            switch ($queueditem->submissiontype) {
                case 'file':
                case 'text_content':

                    // Get file data or prepare text submission.
                    if ($queueditem->submissiontype == 'file') {
                        $fs = get_file_storage();
                        $file = $fs->get_file_by_hash($queueditem->identifier);

                        if (!$file) {
                            plagiarism_sst_activitylog('File not found for submission: '.$queueditem->id, 'PP_NO_FILE');
                            mtrace('File not found for submission. Identifier: '.$queueditem->id);
                            $errorcode = 9;
                            break;
                        }

                        $title = $file->get_filename();
                        $filename = $file->get_filename();

                        try {
                            $textcontent = $file->get_content();
                        } catch (Exception $e) {
                            plagiarism_sst_activitylog('File content not found on submission: '.$queueditem->identifier, 'PP_NO_FILE');
                            mtrace($e);
                            mtrace('File content not found on submission. Identifier: '.$queueditem->identifier);
                            $errorcode = 9;
                            break;
                        }
                    } else {
                        // Get the actual text content for a submission.
                        switch ($cm->modname) {
                            case 'assign':
                                $moodlesubmission = $DB->get_record('assign_submission', ['assignment' => $cm->instance,
                                    'userid' => $queueditem->userid, 'id' => $queueditem->itemid, ], 'id');
                                $moodletextsubmission = $DB->get_record('assignsubmission_onlinetext',
                                    ['submission' => $moodlesubmission->id], 'onlinetext');
                                $textcontent = $moodletextsubmission->onlinetext;
                                break;

                            case 'workshop':
                                $moodlesubmission = $DB->get_record('workshop_submissions',
                                    ['id' => $queueditem->itemid], 'content');
                                $textcontent = $moodlesubmission->content;
                                break;
                        }

                        $title = 'onlinetext_'.$user->id.'_'.$cm->id.'_'.$cm->instance.'.txt';
                        $filename = $title;
                        $textcontent = html_to_text($textcontent);
                    }

                    // Remove any old text submissions from Moodle DB if there are any as there is only one per submission.
                    if (!empty($queueditem->itemid) && $queueditem->submissiontype == 'text_content') {
                        $this->clean_old_submissions($cm, $user->id, $queueditem->itemid, $queueditem->submissiontype, $queueditem->identifier);
                    }

                    break;

                case 'forum_post':
                    $forumpost = $DB->get_record_select('forum_posts', ' userid = ? AND id = ? ', [$user->id, $queueditem->itemid]);

                    if ($forumpost) {
                        $textcontent = strip_tags($forumpost->message);
                        $title = 'forumpost_'.$user->id.'_'.$cm->id.'_'.$cm->instance.'_'.$queueditem->itemid.'.txt';
                        $filename = $title;
                    } else {
                        $errorcode = 9;
                    }

                    break;

                case 'quiz_answer':
                    require_once $CFG->dirroot.'/mod/quiz/locallib.php';
                    try {
                        $attempt = quiz_attempt::create($queueditem->itemid);
                    } catch (Exception $e) {
                        plagiarism_sst_activitylog(get_string('errorcode14', 'plagiarism_sst'), 'PP_NO_ATTEMPT');
                        $errorcode = 14;
                        break;
                    }
                    foreach ($attempt->get_slots() as $slot) {
                        $qa = $attempt->get_question_attempt($slot);
                        if ($queueditem->identifier == sha1($qa->get_response_summary().$slot)) {
                            $textcontent = $qa->get_response_summary();
                            break;
                        }
                    }

                    if (!empty($textcontent)) {
                        $textcontent = strip_tags($textcontent);
                        $title = 'quizanswer_'.$user->id.'_'.$cm->id.'_'.$cm->instance.'_'.$queueditem->itemid.'.txt';
                        $filename = $title;
                    } else {
                        $errorcode = 9;
                    }

                    break;
            }

            // Save failed submission and don't process any further.
            if ($errorcode != 0) {
                $this->save_errored_submission($queueditem->id, $queueditem->attempt, $errorcode);
                continue;
            }

            // Don't proceed if we can not create a tempfile.
            try {
                $tempfile = $this->create_tempfile($filename);
            } catch (Exception $e) {
                $this->save_errored_submission($queueditem->id, $queueditem->attempt, 8);
                continue;
            }

            $fh = fopen($tempfile, 'w');
            fwrite($fh, $textcontent);
            fclose($fh);

            // Call SST API to check content
            require_once __DIR__.'/classes/plagiarism_sst_api_provider.php';
            require_once __DIR__.'/classes/plagiarism_receipt_message.php';
            $apikey = $this->get_config_settings('publickey');
            $apitoken = $this->get_config_settings('secretkey');
            $apiprovider = new plagiarism_sst_api_provider($apikey, $apitoken);
            $response_data = $apiprovider->send_text($textcontent, $tempfile);
            if ($response_data != null || !empty($response_data)) {
                // After finished the scan proccess, delete the temp file (if it exists).
                if (!is_null($tempfile)) {
                    unlink($tempfile);
                }

                $plagiarismfile = new stdClass();
                $plagiarismfile->id = $queueditem->id;
                $plagiarismfile->similarityscore = $response_data->plagPercent;
                $plagiarismfile->statuscode = isset($response_data->message) ? 'error' : 'success';
                $plagiarismfile->errormsg = isset($response_data->message) ? $response_data->message : null;
                $plagiarismfile->attempt = $queueditem->attempt;
                $plagiarismfile->externalid = $response_data->hash;
                $plagiarismfile->reporturl = isset($response_data->report_url) ? $response_data->report_url : PLAGIARISM_SST_API_BASE_URL.'/plag/scan/mdl/report/'.$response_data->hash;
                $DB->update_record(TABLE_SST_FILES, $plagiarismfile);
            }

            $outputvars = new stdClass();
            $outputvars->title = 'title';
            $outputvars->submissionid = 'submissionid';
            $outputvars->assignmentname = $moduledata->name;
            $outputvars->coursename = $coursedata->fullname;
            mtrace(get_string('cronsubmittedsuccessfully', 'plagiarism_sst', $outputvars));
        }
    }

    private function clean_old_submissions($cm, $userid, $itemid, $submissiontype, $identifier)
    {
        global $DB, $CFG;
        $deletestr = '';
        $filecomponent = '';

        switch ($cm->modname) {
            case 'assign':
                $filecomponent = 'assignsubmission_file';
                break;
            case 'coursework':
                $filecomponent = 'mod_coursework';
                break;
            case 'forum':
                $filecomponent = 'mod_forum';
                break;
            case 'quiz':
                $filecomponent = 'mod_quiz';
                break;
            case 'workshop':
                $filecomponent = 'mod_workshop';
                break;
        }
        if ($submissiontype == 'file') {
            // If this is an assignment then we need to account for previous attempts so get other items ids.
            if ($cm->modname == 'assign') {
                $itemids = $DB->get_records('assign_submission', [
                    'assignment' => $cm->instance,
                    'userid' => $userid,
                ], '', 'id');

                // Only proceed if we have item ids.
                if (empty($itemids)) {
                    return true;
                } else {
                    list($itemidsinsql, $itemidsparams) = $DB->get_in_or_equal(array_keys($itemids));
                    $itemidsinsql = ' itemid '.$itemidsinsql;
                    $params = array_merge([$filecomponent, $userid], $itemidsparams);
                }
            } else {
                $itemidsinsql = ' itemid = ? ';
                $params = [$filecomponent, $userid, $itemid];
            }

            if ($moodlefiles = $DB->get_records_select('files', ' component = ? AND userid = ? AND source IS NOT null AND '.$itemidsinsql,
                $params, 'id DESC', 'pathnamehash')) {
                list($notinsql, $notinparams) = $DB->get_in_or_equal(array_keys($moodlefiles), SQL_PARAMS_QM, 'param', false);
                $typefield = ($CFG->dbtype == 'oci') ? ' to_char(submissiontype) ' : ' submissiontype ';
                $oldfiles = $DB->get_records_select(TABLE_SST_FILES, ' userid = ? AND cm = ? '.
                    ' AND '.$typefield.' = ? AND identifier '.$notinsql,
                    array_merge([$userid, $cm->id, 'file'], $notinparams));

                if (!empty($oldfiles)) {
                    foreach ($oldfiles as $oldfile) {
                        $deletestr .= $oldfile->id.', ';
                    }

                    list($insql, $deleteparams) = $DB->get_in_or_equal(explode(',', substr($deletestr, 0, -2)));
                    $deletestr = ' id '.$insql;
                }
            }
        } elseif ($submissiontype == 'text_content') {
            $typefield = ($CFG->dbtype == 'oci') ? ' to_char(submissiontype) ' : ' submissiontype ';
            $deletestr = ' userid = ? AND cm = ? AND '.$typefield.' = ? AND identifier != ? ';
            $deleteparams = [$userid, $cm->id, 'text_content', $identifier];
        }

        // Delete from database.
        if (!empty($deletestr)) {
            if (!$DB->delete_records_select(TABLE_SST_FILES, $deletestr, $deleteparams)) {
                throw new moodle_exception('not deleted');
            }
        }
    }

    /**
     * Creates a temp file for submission to SST, uses a random number suffixed with the stored filename.
     *
     * @param string $filename temp filename
     *
     * @return string $file The filepath of the temp file
     */
    private function create_tempfile($filename)
    {
        $tempdir = make_temp_directory('plagiarism_sst');

        $filename = clean_param($filename, PARAM_FILE);

        $tries = 0;
        do {
            if ($tries == 5) {
                throw new invalid_dataroot_permissions('SST plagiarism plugin temporary file cannot be created.');
            }
            ++$tries;

            $file = $tempdir.DIRECTORY_SEPARATOR.$filename;
        } while (!touch($file));

        return $file;
    }
}

function plagiarism_sst_event_file_uploaded($eventdata)
{
    $result = true;
    //a file has been uploaded - submit this to the plagiarism prevention service.

    return $result;
}
function plagiarism_sst_event_files_done($eventdata)
{
    $result = true;
    //mainly used by assignment finalize - used if you want to handle "submit for marking" events
    //a file has been uploaded/finalised - submit this to the plagiarism prevention service.

    return $result;
}

function plagiarism_sst_event_mod_created($eventdata)
{
    $result = true;
    //a sst module has been created - this is a generic event that is called for all module types
    //make sure you check the type of module before handling if needed.

    return $result;
}

function plagiarism_sst_event_mod_updated($eventdata)
{
    $result = true;
    //a module has been updated - this is a generic event that is called for all module types
    //make sure you check the type of module before handling if needed.

    return $result;
}

function sst_event_mod_deleted($eventdata)
{
    $result = true;
    //a module has been deleted - this is a generic event that is called for all module types
    //make sure you check the type of module before handling if needed.

    return $result;
}

/**
 * Log activity / errors.
 *
 * @param string $string   The string describing the activity
 * @param string $activity The activity prompting the log
 *                         e.g. PRINT_ERROR (default), API_ERROR, INCLUDE, REQUIRE_ONCE, REQUEST, REDIRECT
 */
function plagiarism_sst_activitylog($string, $activity)
{
    global $CFG;

    static $config;
    if (empty($config)) {
        $config = plagiarism_plugin_sst::plagiarism_sst_admin_config();
    }

    if (isset($config->plagiarism_sst_enable)) {
        // We only keep 10 log files, delete any additional files.
        $prefix = 'activitylog_';

        $dirpath = $CFG->tempdir.'/plagiarism_sst/logs';
        if (!file_exists($dirpath)) {
            mkdir($dirpath, 0777, true);
        }
        $dir = opendir($dirpath);
        $files = [];
        while ($entry = readdir($dir)) {
            if (substr(basename($entry), 0, 1) != '.' and substr_count(basename($entry), $prefix) > 0) {
                $files[] = basename($entry);
            }
        }
        sort($files);
        for ($i = 0; $i < count($files) - 10; ++$i) {
            unlink($dirpath.'/'.$files[$i]);
        }

        // Replace <br> tags with new line character.
        $string = str_replace('<br/>', "\r\n", $string);

        // Write to log file.
        $filepath = $dirpath.'/'.$prefix.gmdate('Y-m-d', time()).'.txt';
        $file = fopen($filepath, 'a');
        $output = date('Y-m-d H:i:s O').' ('.$activity.')'.' - '.$string."\r\n";
        fwrite($file, $output);
        fclose($file);
    }
}

function plagiarism_sst_coursemodule_standard_elements($formwrapper, $mform)
{
    $sst_plugin = new plagiarism_plugin_sst();
    $course = $formwrapper->get_course();
    $context = context_course::instance($course->id);
    $modulename = $formwrapper->get_current()->modulename;

    $sst_plugin->get_form_elements_module(
        $mform,
        $context,
        isset($modulename) ? 'mod_'.$modulename : ''
    );
}

function plagiarism_sst_coursemodule_edit_post_actions($data, $course)
{
    $sstplugin = new plagiarism_plugin_sst();
    $sstplugin->save_form_elements($data);
}

 function dd($data = [])
 {
     echo '<pre>';
     print_r($data);
     exit;
 }
