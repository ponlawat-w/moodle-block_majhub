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

/**
 * Manage Versions Page for MAJHub  Block
 *
 * @package    block_majhub
 * @author     Justin Hunt <poodllsupport@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Justin Hunt  http://poodll.com
 */
global  $USER, $COURSE, $OUTPUT;	

require_once("../../config.php");
require_once(dirname(__FILE__).'/forms.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->dirroot . '/local/majhub//classes/storage.php');

require_login();
if (isguestuser()) {
    die();
}

//get any params we might need
$action = optional_param('action','selectbackup', PARAM_TEXT);
$courseid = optional_param('courseid',0, PARAM_INT);
$versionid = optional_param('versionid',0, PARAM_INT);


if( $courseid==0){
    $course = get_course($COURSE->id);
    $courseid = $course->id;
}else{
     $course = get_course($courseid);
}

$context = context_course::instance($courseid);
require_capability('block/majhub:manageversions', $context);

$PAGE->set_context($context);
$PAGE->set_course($course);

//backup form. Either redirect, or just prepare
if($action=='dobackup'){
	$params = array('sesskey' => sesskey(), 'id' => $courseid,);
    $backupprocessurl = new moodle_url("/blocks/majhub/backup.php", $params);
    redirect($backupprocessurl);
}


$url = new moodle_url('/blocks/majhub/manageversions.php', array('courseid'=>$courseid, 'action'=>$action));
$PAGE->set_url($url);
$PAGE->set_heading($SITE->fullname);
$PAGE->set_pagelayout('course');
$renderer = $PAGE->get_renderer('block_majhub');

//prepare our courseware object
$courseware = $DB->get_record('majhub_coursewares', array('courseid' => $course->id));

//in the case that we have no coursware record for this course. 
//It is not a course published on the hub
//we might have the option to send them off to a form that allows them to create a
//courseware record for the current course in future
if(!$courseware){
	echo $renderer->header();
	echo $renderer->show_title(get_string('manageversions', 'block_majhub'));
	echo $renderer->show_error(get_string('nocoursewarerecord', 'block_majhub'));
	echo $renderer->footer();
	return;
}

//fetch version information
$versions = $DB->get_records('majhub_courseware_versions', array('coursewareid'=>$courseware->id));
$theversion = false;
if($versionid && $versions){
	//get the current version
	foreach($versions as $version){
		if ($version->id ==$versionid){
			$theversion = $version;
			break;
		}
	}
}

switch($action){

	case 'edit':
	case 'doedit':
		$continueurl = new moodle_url('/blocks/majhub/manageversions.php', array('courseid'=>$courseid));
		//fail if we couldn't get the current version
		if(!$theversion){
			$renderer->continue_on($continueurl,get_string('theversionnotfound', 'block_majhub'));
			return;
		}
		
		//prepare edit form
		$editform = new block_majhub_edit();
		if($editform->is_cancelled()){
			$renderer->continue_on($continueurl,null);
			return;
		}	
		
		//if we are showing the form
		if($action=='edit'){
			echo $renderer->header();
			echo $renderer->show_title(get_string('manageversions', 'block_majhub'));
			$editformdata= new stdClass();
			$editformdata->courseid=$courseid;
			$editformdata->versionid=$theversion->id;
			$editformdata->description=$theversion->description;
			$editform->set_data($editformdata);
			$renderer->show_form($editform,get_string('editversionform', 'block_majhub'), '');
			echo $renderer->footer();
			return;
		}else{
			$editformdata = $editform->get_data();
			$version =new stdClass();
			$version->id = $editformdata->versionid;
			$version->description = $editformdata->description;
			$DB->update_record('majhub_courseware_versions', $version);
			$renderer->continue_on($continueurl,get_string('versionedited', 'block_majhub'));
			return;
		}

	case 'delete':
	case 'dodelete':

		$continueurl = new moodle_url('/blocks/majhub/manageversions.php', array('courseid'=>$courseid));
		
		//fail if we couldn't get the current version
		if(!$theversion){
			$renderer->continue_on($continueurl,get_string('theversionnotfound', 'block_majhub'));
			return;
		}
		//fail if asked to delete the current version
		if($courseware->fileid == $theversion->fileid){
			$renderer->continue_on($continueurl,get_string('cannnotdeletecurrentversion', 'block_majhub'));
			return;
		}
		
		if($action=='delete'){
			echo $renderer->header();
			echo $renderer->confirm(get_string("confirmversiondelete","block_majhub", $theversion->description), 
				new moodle_url('manageversions.php', array('action'=>'dodelete','courseid'=>$courseid,'versionid'=>$versionid)), 
				new moodle_url('manageversions.php', array('action'=>'selectbackup','courseid'=>$courseid)));
			echo $renderer->footer();
			return;
		}else{
			//default message (optimism)			
			$message = get_string('versiondeleted', 'block_majhub');
			
			//delete from DB
			$ok = $DB->delete_records('majhub_courseware_versions', array('id'=>$theversion->id));
			
			//delete files
			if($ok){
				$storage = new majhub\storage();
				$ok = $storage->remove_from_storage($theversion->fileid, $theversion->timecreated, $courseware->hubcourseid);
				if(!$ok){
					$message = get_string('versionnotdeletedfilesystem', 'block_majhub');
				}
			}else{
				$message = get_string('versionnotdeleteddb', 'block_majhub');
			}
			
			$continueurl = new moodle_url('/blocks/majhub/manageversions.php', array('courseid'=>$courseid));
			$renderer->continue_on($continueurl,get_string('versiondeleted', 'block_majhub'));
		}
		break;
	case 'selectbackup':
	case 'doselectbackup':
		$selectform = new block_majhub_selectbackup(null,array('versions'=>$versions, 'courseid'=>$courseid));
	
		//if going to the form prepare data
		//if arriving from the form, process data
		if($action=='selectbackup'){
			$chosenversion = $DB->get_record('majhub_courseware_versions', array('coursewareid'=>$courseware->id,'fileid'=>$courseware->fileid));
			//we might not get a version if an error had occurred previously while backing up
			if($chosenversion){
				$formdata = $chosenversion;
			}else{
				$formdata = new stdClass();
			}
			$formdata->courseid = $courseid;
			$selectform->set_data($formdata);
		}else{
			$formdata = $selectform->get_data();
			$courseware->fileid = $formdata->fileid;
			foreach($versions as $version){
				if($courseware->fileid == $version->fileid){
					$courseware->filesize = $version->filesize;
					break;
				}
			}
			$DB->update_record('majhub_coursewares',$courseware);
			$continueurl = new moodle_url('/blocks/majhub/manageversions.php', array('courseid'=>$courseid));
			$renderer->continue_on($continueurl,get_string('currentversionupdated', 'block_majhub'));
			return;
		}
		break;

}

//display page and forms
echo $renderer->header();
echo $renderer->show_title(get_string('manageversions', 'block_majhub'));
//$renderer->show_form($backupform,get_string('backupnewversionform', 'block_majhub'), '');
$backupurl = new moodle_url('/blocks/majhub/manageversions.php', array('courseid'=>$courseid, 'action'=>'dobackup'));
echo $renderer->newversion_button($backupurl,get_string('backupnewversionform', 'block_majhub'));
$renderer->show_form($selectform,get_string('selectcurrentversionform', 'block_majhub'),'');
echo $OUTPUT->footer();