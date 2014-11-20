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
 * Block Majhub renderer.
 * @package   block_majhub
 * @copyright 2014 Justin Hunt (poodllsupport@gmail.com)
 * @author    Justin Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_majhub_renderer extends plugin_renderer_base {
	
	/**
	 * Show a form
	 * @param mform $showform the form to display
	 * @param string $heading the title of the form
	 * @param string $message any status messages from previous actions
	 */
	function show_form($showform,$heading, $message=''){
	
		//if we have a status message, display it.
		echo '<br />';
		if($message){
			echo $this->output->heading($message,5,'main');
		}
		echo $this->output->heading($heading, 3, 'main');
		$showform->display();
	}
	
	function continue_on($continueurl,$message){
		redirect($continueurl, $message);
	}
	
	/**
	 * Show a message
	 * @param string $message any status messages from previous actions
	 */
	function show_title($title=''){
	
		//if we have a status message, display it.
		if($title){
			return $this->output->heading($title,2,'main');
		}
	}
	
	/**
	 * Show a message
	 * @param string $message any status messages from previous actions
	 */
	function show_message($message=''){
	
		//if we have a status message, display it.
		if($message){
			return $this->output->heading($message,3,'main');
		}
	}
	/**
	 * Show an error message
	 * @param string $message any status messages from previous actions
	 */
	function show_error($message=''){
		return $this->show_message($message);
	}
	
	function newversion_button($backupurl,$message){
		$ret = $this->output->single_button($backupurl,$message);
		$ret = '<center>' .  $ret . '</center>';
		return $ret;
	}
	
	
	function fetch_backuplink($courseid){
		$backupurl = new moodle_url('/blocks/majhub/manageversions.php', array('courseid'=>$courseid, 'action'=>'backup'));
		$backuplink= html_writer::link($backupurl,get_string('takebackup', 'block_majhub'));
		return $backuplink;
	}

}
