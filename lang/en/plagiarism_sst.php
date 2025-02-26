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

$string['pluginname'] = 'SmallSEOTools Plagiarism Checker';
$string['sstconfig'] = 'SmallSEOTools Plagiarism Plugin Configuration';
$string['usesst_mod'] = 'Enable SmallSEOTools for {$a}';
$string['enable'] = 'Enable SmallSEOTools';
$string['sstexplain'] = 'SmallSEOTools Plagiarism Checker plugin ';
$string['setting'] = 'Settings';
$string['sstaccountconfig'] = 'SmallSEOTools Account Configuration';
$string['sstaccountid'] = 'SmallSEOTools Account ID';
$string['sstpublickey'] = 'SmallSEOTools Public Key';
$string['sstsecretkey'] = 'SmallSEOTools Secret Key';
$string['sstapiurl'] = 'SmallSEOTools API URL';
$string['connecttest'] = 'Test SmallSEOTools Connection';
$string['sststatus'] = 'SmallSEOTools status';
$string['error'] = 'Error Occurred';
$string['pending'] = 'Pending';
$string['queued'] = 'Queued';
$string['success'] = 'Completed Successfully';
$string['complete'] = 'Completed';
$string['similarity'] = 'Similarity';

$string['studentdisclosure'] = 'Student disclosure';
$string['studentdisclosure_help'] = 'This text will be displayed to all students on the file upload page.';
$string['studentdisclosuredefault']  = '<span>By submitting your files you are agreeing to the plagiarism detection service </span><a target="_blank" href="https://sst.com/legal/privacypolicy">privacy policy</a>';
$string['studentdagreedtoeula']  = '<span>You have already agreed to the plagiarism detection service </span><a target="_blank" href="https://sst.com/legal/privacypolicy">privacy policy</a>';




$string['coursesettings'] = 'SmallSEOTools Settings';
$string['draftsubmit'] = "Submit files only when students click the submit button";
$string['draftsubmit_help'] = "This option is only available if 'Require students to click the submit button' is Yes";
$string['reportgenspeed'] = 'When to generate report?';
$string['genereportimmediately'] = 'Generate reports immediately';
$string['genereportonduedate'] = 'Generate reports on due date';
$string['allowstudentaccess'] = 'Allow students access to plagiarism reports';
$string['reportpagetitle'] = 'SST report';
$string['savesuccess'] = 'settings saved successfully!';
$string['sst:enable'] = 'Enable SmallSEOTools Plagiarism Checker plugin';
$string['sst:viewfullreport'] = 'View Similarity Report';
$string['disabledformodule'] = 'SmallSEOTools Plagiarism Checker plugin is disabled for this module.';
$string['nopageaccess'] = 'You dont have access to this page.';
$string['openfullscreen'] = 'Open in full screen';
$string['similaritystr'] = 'Similarity Score';
$string['viewreportstr'] = 'View Report';
$string['clickstr'] = 'click here';

$string['updateerror'] = 'Error while trying to update records in database';
$string['inserterror'] = 'Error while trying to insert records to database';

$string['digital_receipt_subject'] = 'SmallSEOTools Digital Receipt';
$string['digital_receipt_message'] = 'Dear {$a->firstname} {$a->lastname},<br /><br />You have successfully submitted the file <strong>{$a->submission_title}</strong> to the assignment <strong>{$a->assignment_name}{$a->assignment_part}</strong> in the class <strong>{$a->course_fullname}</strong> on <strong>{$a->submission_date}</strong>. Your full digital receipt can be viewed and printed from the print/download button in the Document Viewer.<br /><br />Thank you for using SmallSEOTools,<br /><br />The SmallSEOTools Team';


$string['errorcode0'] = 'This file has not been submitted to SmallSEOTools, please consult your system administrator';
$string['errorcode1'] = 'This file has not been sent to SmallSEOTools as it does not have enough content to produce a Similarity Report.';
$string['errorcode2'] = 'This file will not be submitted to SmallSEOTools as it exceeds the maximum size of {$a->maxfilesize} allowed';
$string['errorcode3'] = 'This file has not been submitted to SmallSEOTools because the user has not accepted the SmallSEOTools End User Licence Agreement.';
$string['errorcode4'] = 'You must upload a supported file type for this assignment. Accepted file types are; .doc, .docx, .ppt, .pptx, .pps, .ppsx, .pdf, .txt, .htm, .html, .hwp, .odt, .wpd, .ps and .rtf';
$string['errorcode5'] = 'This file has not been submitted to SmallSEOTools because there is a problem creating the module in SmallSEOTools which is preventing submissions, please consult your API logs for further information';
$string['errorcode6'] = 'This file has not been submitted to SmallSEOTools because there is a problem editing the module settings in SmallSEOTools which is preventing submissions, please consult your API logs for further information';
$string['errorcode7'] = 'This file has not been submitted to SmallSEOTools because there is a problem creating the user in SmallSEOTools which is preventing submissions, please consult your API logs for further information';
$string['errorcode8'] = 'This file has not been submitted to SmallSEOTools because there is a problem creating the temp file. The most likely cause is an invalid file name. Please rename the file and re-upload using Edit Submission.';
$string['errorcode9'] = 'The file cannot be submitted as there is no accessible content in the file pool to submit.';
$string['errorcode10'] = 'This file has not been submitted to SmallSEOTools because there is a problem creating the class in SmallSEOTools which is preventing submissions, please consult your API logs for further information';
$string['errorcode11'] = 'This file has not been submitted to SmallSEOTools because it is missing data';
$string['errorcode12'] = 'This file has not been submitted to SmallSEOTools because it belongs to an assignment in which the course was deleted. Row ID: ({$a->id}) | Course Module ID: ({$a->cm}) | User ID: ({$a->userid})';
$string['errorcode14'] = 'This file has not been submitted to SmallSEOTools because the attempt it belongs to could not be found';

$string['updatereportscores'] = 'Update Report Scores for SmallSEOTools Plagiarism Plugin';
$string['sendqueuedsubmissions'] = 'Send Queued Files from the SmallSEOTools Plagiarism Plugin';

$string['coursegeterror'] = 'Could not get course data';
$string['cronsubmittedsuccessfully'] = 'Submission: {$a->title} ( ID: {$a->submissionid}) for the assignment {$a->assignmentname} on the course {$a->coursename} was successfully submitted to SmallSEOTools.';

$string['messageprovider:submission'] = 'SmallSEOTools Plagiarism Plugin Digital Receipt notifications';

