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

class plagiarism_receipt_message {

    /**
     * Send digital receipt to submitter
     *
     * @param string $message
     * @return void
     */
    public function send_message($userid, $message, $courseid) {
        global $CFG;
        $userfrom = \core_user::get_noreply_user();
        $subject = get_string('digital_receipt_subject', 'plagiarism_sst');

        $eventdata = new \core\message\message();
        $eventdata->component         = 'plagiarism_sst'; // Your component name.
        $eventdata->name              = 'submission'; // This is the message name from messages.php.
        $eventdata->userfrom          = $userfrom;
        $eventdata->userto            = $userid;
        $eventdata->subject           = $subject;
        $eventdata->fullmessage       = $message;
        $eventdata->fullmessageformat = FORMAT_HTML;
        $eventdata->fullmessagehtml   = $message;
        $eventdata->smallmessage      = '';
        $eventdata->notification      = 1; // This is only set to 0 for personal messages between users.
        $eventdata->courseid = $courseid;

        message_send($eventdata);
    }

    /**
     * Build message to send to user
     *
     * @param array $input - used to build message
     * @return string
     */
    public function build_message($input) {
        $message = new stdClass();
        $message->firstname = $input['firstname'];
        $message->lastname = $input['lastname'];
        $message->submission_title = $input['submission_title'];
        $message->assignment_name = $input['assignment_name'];
        if ( isset($input['assignment_part']) ) {
            $message->assignment_part = ": " . $input['assignment_part'];
        } else {
            $message->assignment_part = "";
        }
        $message->course_fullname = $input['course_fullname'];
        $message->submission_date = $input['submission_date'];

        return format_text(get_string('digital_receipt_message', 'plagiarism_sst', $message));
    }
}
