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
 * Forms for MajHub Block
 *
 * @package    block_majhub
 * @author     Justin Hunt <poodllsupport@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2014 onwards Justin Hunt  http://poodll.com
 */

require_once($CFG->libdir . '/formslib.php');
require_once(dirname(__FILE__).'/locallib.php');

abstract class block_majhub_base_form extends moodleform {
	protected $target = 'replacethis';
	
	function tablify($elarray, $colcount, $id, $haveheader=true){
		$mform = & $this->_form;
		
		$starttable =  html_writer::start_tag('table',array('class'=>'block_majhub_form_table'));
		//$startheadrow=html_writer::start_tag('th'); 
		//$endheadrow=html_writer::end_tag('th'); 
		$startrow=html_writer::start_tag('tr'); 
		$startcell = html_writer::start_tag('td',array('class'=>'block_majhub_form_cell block_majhub_' . $id .'_col_@@'));
		$startheadcell = html_writer::start_tag('th',array('class'=>'block_majhub_form_cell block_majhub_' . $id .'_col_@@'));
		$endcell=html_writer::end_tag('td');
		$endheadcell=html_writer::end_tag('th');
		$endrow=html_writer::end_tag('tr');
		$endtable = html_writer::end_tag('table');
		
		//start the table 
		$tabledelements = array();
		$tabledelements[]=& $mform->createElement('static', 'table_start_' . $id, '', $starttable);
	
		
		//loop through rows
		for($row=0;$row<count($elarray);$row= $row+$colcount){
			//loop through cols
			for($col=0;$col<$colcount;$col++){
				//addrowstart
				if($col==0){
					$tabledelements[]=& $mform->createElement('static', 'tablerow_start_' . $id . '_' . $row, '', $startrow);
				}
				//add a th cell if this is first row, otherwise a td
				if($row==0 && $haveheader){
					$thestartcell = str_replace('@@', $col,$startheadcell);
					$theendcell = $endheadcell;
				}else{
					$thestartcell = str_replace('@@', $col,$startcell);
					$theendcell = $endcell;
				}
				$tabledelements[]=& $mform->createElement('static', 'tablecell_start_' . $id . '_' . $row .'_'. $col, '', $thestartcell);
				$tabledelements[]=& $elarray[$row+$col];
				$tabledelements[]=& $mform->createElement('static', 'tablecell_end_' . $id . '_' . $row .'_'. $col, '', $theendcell);

				//add row end
				if($col==$colcount-1){
					$tabledelements[]=& $mform->createElement('static', 'tablerow_end_' . $id . '_' . $row, '', $endrow);
				}
			}//end of col loop	
		}//end of row loop
		
		//close out our table and return it
		$tabledelements[]=& $mform->createElement('static', 'table_end_' . $id, '', $endtable);
		return $tabledelements;
	}

}


class block_majhub_edit extends block_majhub_base_form {

	protected $target = 'edit';
		
    public function definition() {
    	global $CFG, $USER, $OUTPUT;
        $strrequired = get_string('required');
    	$mform = & $this->_form;
    	
    	
    	//courseid
    	$attributes=array('size'=>'50');
    	$mform->addElement('text', 'description',get_string('description','block_majhub'), $attributes);
    	$mform->setType('description', PARAM_TEXT);
    	
    	//courseid
    	$mform->addElement('hidden', 'courseid');
    	$mform->setType('courseid', PARAM_INT);
    	
    	//courseid
    	$mform->addElement('hidden', 'versionid');
    	$mform->setType('versionid', PARAM_INT);
    	
    	//hidden fields
    	$mform->addElement('hidden', 'action', 'do' . $this->target);
        $mform->setType('action', PARAM_TEXT);
    	
    	//add buttons
		$this->add_action_buttons(true,get_string('savechanges'));
    }
}
class block_majhub_selectbackup extends block_majhub_base_form {

	protected $target = 'selectbackup';
		
    public function definition() {
    	global $CFG, $USER, $OUTPUT;
        $strrequired = get_string('required');
    	$mform = & $this->_form;
    	$versions = $this->_customdata['versions'];
    	$courseid = $this->_customdata['courseid'];
    	
    	//this situation should not occur.
    	if(!$versions){
    		$mform->addElement('static', 'noversions', get_string("noversions", "block_majhub"));
    		return;
    	}
		
		$i=0;
		$versionform = array();
		//header cells
		$versionform[] =& $mform->createElement('static', 'header_selected_', '', get_string("selected", "block_majhub"));
		$versionform[] =& $mform->createElement('static', 'header_description_', '', get_string("description","block_majhub"));
		$versionform[] =& $mform->createElement('static', 'header_filesize_', '', get_string("filesize","block_majhub"));
		$versionform[] =& $mform->createElement('static', 'header_timecreated_', '', get_string("timecreated","block_majhub"));
		$versionform[] =& $mform->createElement('static', 'header_edit_', '', get_string('edit'));
		$versionform[] =& $mform->createElement('static', 'header_delete_', '', get_string('delete'));
		foreach($versions as $version){
			$i++;
			$deleteurl = new moodle_url('/blocks/majhub/manageversions.php', array('courseid'=>$courseid, 'versionid'=> $version->id, 'action'=>'edit'));
			$deletelink= html_writer::link($deleteurl,get_string('edit'));
			$editurl = new moodle_url('/blocks/majhub/manageversions.php', array('courseid'=>$courseid,'versionid'=> $version->id,'action'=>'delete'));
			$editlink= html_writer::link($editurl,get_string('delete'));
			$versionform[] =& $mform->createElement('radio', 'fileid', '', '',$version->fileid);
			$versionform[]=& $mform->createElement('static', 'description_' . $i, '', $version->description) ;
			$versionform[]=& $mform->createElement('static', 'filesize_' . $i, '', $version->filesize) ;
			$versionform[]=& $mform->createElement('static', 'timecreated_' . $i, '', Date('j F Y h:i:s A',$version->timecreated));
			$versionform[]=& $mform->createElement('static', 'edit_' . $i, '', $editlink);
			$versionform[]=& $mform->createElement('static', 'delete_' . $i, '', $deletelink);
		}
		$versionform_els = $this->tablify($versionform,6,'versions');
		//adding this as 3rd param, looked bad:  get_string("pleaseselectversion", 'block_majhub')
		$mform->addGroup($versionform_els, 'versionform_table_group' ,'', array(' '), false);
    	
    	//courseid
    	$mform->addElement('hidden', 'courseid');
    	$mform->setType('courseid', PARAM_INT);
    	
    	//hidden fields
    	$mform->addElement('hidden', 'action', 'do' . $this->target);
        $mform->setType('action', PARAM_TEXT);
    	
    	//add buttons
		$mform->addElement('submit', 'submitbutton', get_string('savechanges'));
		//$this->add_action_buttons(true,get_string('savechanges'));
    }
}

class xblock_majhub_selectbackup extends block_majhub_base_form {

	protected $target = 'selectbackup';
		
    public function definition() {
        global $CFG, $USER, $OUTPUT;
        $strrequired = get_string('required');
        $mform = & $this->_form;
        
		//$backupfiles = $this->_customdata['backupfiles'];
		
		//task header
		$taskheader_element = $mform->createElement('static',$this->target . 'header',get_string($this->target . "header","block_majhub"));
			
		//select list for backup files 
		/*
		$backup_options = array();
		foreach ($backupfiles as $id => $details) {
                    $backup_options[$id] = $details;
       }
	   */
	   $backup_options = block_majhub_fetch_backupfilelist();
	   $mform->addElement('select', 'selectedbackup', get_string("selectbackupfile", "block_majhub"),$backup_options);
	   $mform->setType('selectedbackup', PARAM_TEXT);
	  
	  
		$mform->addElement('hidden', 'action', 'do' . $this->target);
        $mform->setType('action', PARAM_TEXT);
		$this->add_action_buttons(true,get_string('savechanges'));
    }
}

