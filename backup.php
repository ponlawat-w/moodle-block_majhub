<?php

///////////////////////////////////////////////////////////////////////////
//                                                                       //
// This file is part of Moodle - http://moodle.org/                      //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//                                                                       //
// Moodle is free software: you can redistribute it and/or modify        //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation, either version 3 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// Moodle is distributed in the hope that it will be useful,             //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details.                          //
//                                                                       //
// You should have received a copy of the GNU General Public License     //
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.       //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

/*
 * @package    block_majhub
 * @originalauthor     Jerome Mouneyrac <jerome@mouneyrac.com>
 * @author     Justin Hunt <poodllsupport@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * This page display the backup form
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/registration/lib.php');
require_once($CFG->dirroot . '/course/publish/lib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/local/majhub/lib.php');
require_once($CFG->dirroot . '/local/majhub/classes/courseware.php');
require_once($CFG->dirroot . '/local/majhub//classes/storage.php');

global $DB,$COURSE;

//retrieve initial page parameters
$id = required_param('id', PARAM_INT);


$courseid = $id;
$huburl = $CFG->wwwroot;
//need to do something here
$hubname = "MAJ Hub";

//some permissions and parameters checking
$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);

//make sure we are logged in and got here properly
require_login($course);
require_sesskey();

if (!has_capability('block/majhub:manageversions', context_course::instance($id))
        or !confirm_sesskey()) {
    throw new moodle_exception('nopermission');
}

//page settings
$PAGE->set_url('/blocks/majhub/backup.php');
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('course') . ': ' . $course->fullname);
$PAGE->set_heading($course->fullname);


//BEGIN backup processing
$backupid = optional_param('backup', false, PARAM_ALPHANUM);
if (!($bc = backup_ui::load_controller($backupid))) {
    $bc = new backup_controller(backup::TYPE_1COURSE, $id, backup::FORMAT_MOODLE,
                    backup::INTERACTIVE_YES, backup::MODE_HUB, $USER->id);
}
$backup = new backup_ui($bc,
        array('id' => $id, 'courseid' => $courseid, 'huburl' => $huburl, 'hubname' => $hubname));
$backup->process();
if ($backup->get_stage() == backup_ui::STAGE_FINAL) {
    $backup->execute();
} else {
    $backup->save_controller();
}

if ($backup->get_stage() !== backup_ui::STAGE_COMPLETE) {
    $backuprenderer = $PAGE->get_renderer('core', 'backup');
    echo $backuprenderer->header();
    echo $backuprenderer->heading(get_string('backingupcourse', 'block_majhub'), 3, 'main');
    if ($backup->enforce_changed_dependencies()) {
        debugging('Your settings have been altered due to unmet dependencies', DEBUG_DEVELOPER);
    }
    echo $backuprenderer->progress_bar($backup->get_progress_bar());
    echo $backup->display($backuprenderer);
    echo $backuprenderer->footer();
    die();
}

//$backupfile = $backup->get_stage_results();
$backupfile = $bc->get_results();
$backupfile = $backupfile['backup_destination'];
//END backup processing

//Save the new file 
$storage = new majhub\storage();
$courseware = $DB->get_record('majhub_coursewares', array('courseid' => $course->id));
$courseware->timemodified = time();
$courseware->version = $courseware->timemodified;

//get until now back up filename for moodle hub, 
//base our name on that and add a date
//$oldpath = local_majhub_hub_fetch_course_filepath($course->id);
//$newpath =  str_replace('.mbz', '_' . $courseware->version . '.mbz',$oldpath);
$newpath  = local_majhub_fetch_versioned_filepath($courseware->hubcourseid, $courseware->timemodified);
$backupfile->copy_content_to($newpath);

//now update our majhub record
$filename = basename($newpath);
$file =  $storage->copy_to_storage($courseware->id,$newpath, $filename);

//Then tidyup our courseware record
$courseware->fileid = $file->get_id();
$courseware->filesize = $file->get_filesize();
$DB->update_record('majhub_coursewares', $courseware);

//if we got here it would also be good to update our version table
$versioninfo = new stdClass();
$versioninfo->coursewareid=$courseware->id;
$versioninfo->description = str_replace('.mbz','',$backupfile->get_filename());
$versioninfo->filesize = $courseware->filesize;
$versioninfo->fileid = $courseware->fileid;
$versioninfo->timecreated = $courseware->timemodified;
$DB->insert_record('majhub_courseware_versions',$versioninfo);


//Send the user on somewhere
$block_renderer = $PAGE->get_renderer('block_majhub');
$continueurl = new moodle_url('/blocks/majhub/manageversions.php', array('courseid'=>$course->id));
$block_renderer->continue_on($continueurl,get_string('savednewbackup', 'block_majhub'));
