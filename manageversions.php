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
//require_once(dirname(__FILE__).'/forms.php');
//require_once(dirname(__FILE__).'/locallib.php');

require_login();
if (isguestuser()) {
    die();
}


//get any params we might need
$action = optional_param('action','', PARAM_TEXT);
$coursewareid = optional_param('coursewareid',0, PARAM_INT);
$courseid = optional_param('courseid',0, PARAM_INT);


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

$url = new moodle_url('/blocks/majhub/manageversions.php', array('courseid'=>$courseid, 'action'=>$action, 'coursewareid'=>$coursewareid));
$PAGE->set_url($url);
$PAGE->set_heading($SITE->fullname);
$PAGE->set_pagelayout('course');
echo $OUTPUT->header();
echo "Manage Versions";
echo $OUTPUT->footer();
