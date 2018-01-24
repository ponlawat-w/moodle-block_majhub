<?php

require_once __DIR__.'/../../local/majhub/classes/courseware.php';
require_once __DIR__.'/../../local/majhub/classes/capability.php';
require_once __DIR__.'/../../local/majhub/classes/point.php';

/**
 *  MAJ Hub block
 *  
 *  @author  VERSION2, Inc. (http://ver2.jp)
 *  @version $Id: block_majhub.php 215 2013-02-21 09:37:24Z malu $
 */
class block_majhub extends block_base
{
    const MAX_REVIEWS = 3;

    public function init()
    {
        $this->title   = get_string('blocktitle', __CLASS__);
        $this->version = 2013013001;
    }

    public function applicable_formats()
    {
        return array('course' => true, 'course-category' => false);
    }

    public function instance_can_be_docked()
    {
        return false; // AJAX won't work with Dock
    }

    /**
     *  Gets a block content
     *  
     *  @global object $USER
     *  @global moodle_database $DB
     *  @global core_renderer $OUTPUT
     *  @return object|string
     */
    public function get_content()
    {
        global $USER, $DB, $OUTPUT;

        if ($this->content !== null)
            return $this->content;

        if (empty($this->instance) || empty($USER->id))
            return $this->content = '';

        $courseware = majhub\courseware::from_courseid($this->page->course->id);
        if (empty($courseware))
            return $this->content = '';

        $html = '';

        $isowner = $courseware->userid == $USER->id;
        $isadmin = majhub\capability::is_admin($USER);
        $ismoderator = majhub\capability::is_moderator($USER);

        $paid = $DB->record_exists('majhub_courseware_downloads',
            array('userid' => $USER->id, 'coursewareid' => $courseware->id));
        $userpoint = majhub\point::from_userid($USER->id);

        if ($ismoderator && optional_param('givebonuspointsforquality', null, PARAM_TEXT)) {
            $bonuspointsforquality = optional_param('bonuspointsforquality', 0, PARAM_INT);
            if ($bonuspointsforquality != 0) {
                $bonuspoint = new stdClass;
                $bonuspoint->coursewareid = $courseware->id;
                $bonuspoint->siteid = $courseware->siteid;
                $bonuspoint->sitecourseid = $courseware->sitecourseid;
                $bonuspoint->userid       = $USER->id;
                $bonuspoint->reason       = 'quality';
                $bonuspoint->points       = $bonuspointsforquality;
                $bonuspoint->timecreated  = time();
                $DB->insert_record('majhub_bonus_points', $bonuspoint);
            }
            redirect($this->page->url);
        }

        if ($pro = optional_param('pro', 0, PARAM_INT) or $con = optional_param('con', 0, PARAM_INT)) {
            $reviewid = $pro ?: $con;
            if (!$DB->record_exists('majhub_review_proscons',
                array('userid' => $USER->id, 'reviewid' => $reviewid)))
            {
                $proscons = new stdClass;
                $proscons->userid      = $USER->id;
                $proscons->reviewid    = $reviewid;
                $proscons->procon      = $pro ? 'pro' : 'con';
                $proscons->timecreated = time();
                $DB->insert_record('majhub_review_proscons', $proscons);
            }
            redirect($this->page->url);
        }

        $userlink = $OUTPUT->action_link(
            new moodle_url('/user/profile.php', array('id' => $courseware->user->id)),
            fullname($courseware->user)
            );
        $dateformat = get_string('strftimedateshort', 'langconfig');
        $fixedrows = array(
            get_string('title', 'local_majhub')       => $courseware->fullname,
            get_string('contributor', 'local_majhub') => $userlink,
            get_string('uploadedat', 'local_majhub')  => userdate($courseware->timerestored, $dateformat),
            get_string('filesize', 'local_majhub')    => display_size($courseware->filesize),
			get_string('moodleversion', 'local_majhub')     => $courseware->backuprelease
            );

        if ($isowner || $isadmin /* || $ismoderator */) {
            $html .= html_writer::tag('div',
                $OUTPUT->action_link(
                    new moodle_url('/local/majhub/edit.php', array('id' => $courseware->id)),
                    $OUTPUT->pix_icon('i/edit', get_string('edit')) . get_string('editmetadata', 'block_majhub')
                    ),
                array('class' => 'editmetadata')
                );
        }
		
		//echo "html:" . $html;
		
        $html .= html_writer::start_tag('table', array('class' => 'metadata'));
        foreach ($fixedrows as $name => $value) {
            $html .= self::render_row($name, $value);
        }
        $html .= self::render_row(get_string('demourl', 'local_majhub'), self::render_url($courseware->demourl));
        foreach ($courseware->metadata as $metadatum) {
            $html .= self::render_row($metadatum->name, $metadatum->render(', '),
                $metadatum->optional ? array('class' => 'optional') : null);
        }
        $html .= html_writer::end_tag('table');

        $html .= html_writer::empty_tag('hr');

        //Manage Versions
        $course = $this->page->course;
        $coursecontext = context_course::instance($course->id);
        if(has_capability('block/majhub:manageversions',$coursecontext)) {
            $downloadurl = new moodle_url('/blocks/majhub/manageversions.php', array('courseid'=>$course->id));
            $strdownload = $OUTPUT->pix_icon('t/download', '') .  get_string('manageversions', 'block_majhub');
            $attrdownload = array('title' => get_string('manageversions', 'block_majhub'));
            $html .= html_writer::start_tag('div', array('class' => 'action manageversions'));
            $html .= $OUTPUT->action_link($downloadurl, $strdownload, null, $attrdownload);
            $html .= html_writer::end_tag('div');
            $html .= html_writer::empty_tag('hr');
        }
		
        // actions
        $downloadcost = majhub\point::get_settings()->pointsfordownloading;
        $downloadurl = new moodle_url('/local/majhub/download.php', array('id' => $courseware->id));
        $strdownload = $OUTPUT->pix_icon('t/download', '') . get_string('download');
        $attrdownload = array('title' => get_string('downloadthiscourseware', 'local_majhub'));
        if ($paid) {
            $html .= html_writer::start_tag('div', array('class' => 'action download'));
            $html .= $OUTPUT->action_link($downloadurl, $strdownload, null, $attrdownload);
            $html .= html_writer::end_tag('div');
        } elseif ($userpoint->total >= $downloadcost) {
            $confirmpurchase = new confirm_action(get_string('confirm:payfordownload', 'local_majhub', $downloadcost));
            $html .= html_writer::start_tag('div', array('class' => 'action download'));
            $html .= $OUTPUT->action_link($downloadurl, $strdownload, $confirmpurchase, $attrdownload);
            $html .= html_writer::end_tag('div');
        } else {
            $html .= html_writer::tag('div', $strdownload, array('class' => 'action download grayed'));
        }
		
		
        if (!$paid) {
            $html .= html_writer::tag('div',
                get_string('costspoints', 'local_majhub', $downloadcost), array('class' => 'points'));
            $html .= html_writer::tag('div',
                get_string('youhavepoints', 'local_majhub', $userpoint->total), array('class' => 'points'));
            if ($userpoint->total < $downloadcost) {
                $html .= html_writer::tag('div',
                    get_string('error:youdonthaveenoughpoints', 'local_majhub'), array('class' => 'warning'));
                $html .= html_writer::tag('div', get_string('howtogetpoints', 'local_majhub') . ':');
                $html .= html_writer::tag('div',
                    get_string('howtogetpoints.desc', 'local_majhub', majhub\point::get_settings()),
                    array('class' => 'howto')
                    );
            }
        }

        $html .= html_writer::empty_tag('hr');

        // reviews
        $reviews = $courseware->get_reviews(optional_param('showallreviews', 0, PARAM_INT) ? 0 : self::MAX_REVIEWS);
        $reviewed = $courseware->is_reviewed_by($USER->id);
        $html .= html_writer::start_tag('div', array('class' => 'reviews'));
        $html .= html_writer::tag('div',
            html_writer::tag('span', get_string('overallrating', 'local_majhub')) .
            self::render_rating($courseware->avarage_rating),
            array('class' => 'overall')
            );

        $editreview = optional_param('editreview', null, PARAM_TEXT);
        if ($editreview) {

            $rating  = optional_param('rating', 0, PARAM_INT);
            $comment = trim(optional_param('comment', '', PARAM_TEXT));

            if($reviewed && $rating == 0 && $comment == '') {
                $review = $DB->get_record('majhub_courseware_reviews', array('userid' => $USER->id, 'coursewareid' => $courseware->id));
                if ($review) {
                    $rating = $review->rating;
                    $comment = $review->comment;
                }
                else {
                    $rating = 0;
                    $comment = '';
                }
            }

            $stars = array();
            foreach (range(1, 5) as $r) {
                $attrs = ($r * 2 == $rating) ? array('checked' => 'checked') : null;
                $stars[] = html_writer::tag('label', self::render_input('rating', $r * 2, 'radio', $attrs) . $r);
            }
            $html .= html_writer::start_tag('form',
                array('action' => new moodle_url('/local/majhub/postreview.php'), 'method' => 'post', 'class' => 'mform review')
                );
            $html .= self::render_input('courseid', $this->page->course->id, 'hidden');
            $html .= html_writer::start_tag('div', array('class' => 'review'));
            $html .= html_writer::tag('div', get_string('rating', 'local_majhub') . ': ' . implode(' ', $stars));
            $html .= html_writer::tag('div',
                html_writer::tag('textarea', $comment, array('name' => 'comment', 'cols' => 30, 'rows' => 8))
                );
            $html .= html_writer::tag('div',
                get_string('reviewinletters', 'local_majhub', majhub\point::get_settings()->lengthforreviewing)
                );
            $html .= html_writer::tag('div',
                self::render_input('editreview', get_string('submit'), 'submit'), array('class' => 'action')
                );
            $html .= html_writer::end_tag('div');
            $html .= html_writer::end_tag('form');
        } else {

            $reviewbuttonntext = $reviewed ? get_string('editreview', 'block_majhub') : get_string('writereview', 'block_majhub');
            $iconpix = $reviewed ? $OUTPUT->pix_icon('i/edit', '') : $OUTPUT->pix_icon('t/add', '');

            $html .= html_writer::tag('div',
                html_writer::link(
                    new moodle_url($this->page->url, array('editreview' => 1)),
                    $iconpix . $reviewbuttonntext
                    ),
                array('class' => 'action review', 'title' => $reviewbuttonntext)
                );
        }

        $moderatoricon = $OUTPUT->pix_icon('f/moodle', get_string('moderator', 'local_majhub'));
        foreach ($reviews as $review) {
            $fullname = fullname($review->user);
            if (majhub\capability::is_moderator($review->user)) {
                $fullname = html_writer::tag('span', $moderatoricon . $fullname, array('class' => 'moderator'));
            }
            $html .= html_writer::start_tag('div', array('class' => 'review'));
            $html .= html_writer::tag('div',
                html_writer::tag('div', $OUTPUT->user_picture($review->user), array('class' => 'picture')) .
                html_writer::tag('div', self::render_rating($review->rating), array('class' => 'rating')) .
                html_writer::tag('div', $this->render_proscons($review->id), array('class' => 'proscons')) .
                html_writer::tag('div',
                    html_writer::tag('a', $fullname, array('href' => new moodle_url('/user/view.php', array('id' => $review->userid, 'course' => $this->page->course->id)))),
                    array('class' => 'fullname')),
                array('class' => 'userinfo')
                );
            $html .= html_writer::tag('div', nl2br(clean_text($review->comment)), array('class' => 'comment'));
            $html .= html_writer::end_tag('div');
        }
        if (count($reviews) < $courseware->number_of_reviews) {
            $html .= html_writer::tag('div',
                get_string('latestreviews', 'local_majhub',
                    (object)array('latest' => count($reviews), 'total' => $courseware->number_of_reviews)) .
                ' ' .
                $OUTPUT->action_link(new moodle_url($this->page->url, array('showallreviews' => 1)), '...'),
                array('class' => 'ellipsis')
                );
        }
        $html .= html_writer::end_tag('div'); // reviews
		
        // moderator actions
        if ($ismoderator) {
            $html .= html_writer::empty_tag('hr');
            $html .= html_writer::start_tag('form',
                array('action' => $this->page->url, 'method' => 'post', 'class' => 'mform bonuspoints')
                );
            $html .= self::render_input('id', $this->page->url->param('id'), 'hidden');
            $html .= get_string('pointsforquality', 'local_majhub') . ': ';
 
            //changed for siteid and sitecourseid
            /*
        	$ret = $DB->get_record_sql(
                'SELECT SUM(points) AS pointtotal FROM {majhub_bonus_points} 
                 WHERE coursewareid = :coursewareid AND reason = :reason',
                array('coursewareid' => $courseware->id, 'reason' => 'quality')
                );
                */
            
            $ret = $DB->get_record_sql(
                'SELECT SUM(points) AS pointtotal FROM {majhub_bonus_points} 
                 WHERE siteid = :siteid AND sitecourseid = :sitecourseid AND reason = :reason',
                array('siteid' => $courseware->siteid, 'sitecourseid' => $courseware->sitecourseid,'reason' => 'quality')
                );

			if(is_object($ret) && is_numeric($ret->pointtotal) && $ret->pointtotal > 0 ){
            	$bonuspoints = $ret->pointtotal;
            	unset($ret);
            }else{
            	$bonuspoints = 0;
            }
	
			/*	
            if(is_object($ret) && is_int($ret->pointtotal)){
            	return $this->_cache[$name] = $ret->pointtotal;
            	unset($ret);
            }else{
            	return $this->_cache[$name] = 0;
            }
			*/
            /*
            $bonuspoints = $DB->count_records_sql(
                'SELECT SUM(points) FROM {majhub_bonus_points}
                 WHERE coursewareid = :coursewareid AND reason = :reason',
                array('coursewareid' => $courseware->id, 'reason' => 'quality')
                );
            */
			
            
            if ($bonuspoints > 0) {
                $html .= html_writer::tag('span', $bonuspoints);
            } else {
                $html .= html_writer::start_tag('div');
                $html .= self::render_input('bonuspointsforquality',
                    majhub\point::get_settings()->pointsforquality, 'text', array('size' => 2, 'class' => 'points')
                    );
                $html .= self::render_input('givebonuspointsforquality', get_string('give', 'local_majhub'), 'submit');
                $html .= html_writer::end_tag('div');
            }
            $html .= html_writer::end_tag('form');
        }
		

        $this->page->requires->js_init_call('M.block_majhub.init');
        $this->page->requires->string_for_js('optionalfields', 'local_majhub');
		
        return $this->content = (object)array('text' => $html);
    }

    /**
     *  Renders pros and cons
     *  
     *  @global object $USER
     *  @global moodle_database $DB
     *  @global core_renderer $OUTPUT
     *  @param int $reviewid
     *  @return string
     */
    private function render_proscons($reviewid)
    {
        global $USER, $DB, $OUTPUT;

        $pros = $DB->count_records('majhub_review_proscons', array('reviewid' => $reviewid, 'procon' => 'pro'));
        $cons = $DB->count_records('majhub_review_proscons', array('reviewid' => $reviewid, 'procon' => 'con'));
        if ($DB->record_exists('majhub_review_proscons', array('reviewid' => $reviewid, 'userid' => $USER->id))) {
            $pro = $OUTPUT->pix_icon('s/yes', '');
            $con = $OUTPUT->pix_icon('s/no', '');
            return $pro . $pros . ' ' . $con . $cons;
        }
        $pro = $OUTPUT->action_icon(
            new moodle_url($this->page->url, array('pro' => $reviewid)),
            new pix_icon('s/yes', '')
            );
        $con = $OUTPUT->action_icon(
            new moodle_url($this->page->url, array('con' => $reviewid)),
            new pix_icon('s/no', '')
            );
        return $pro . $pros . ' ' . $con . $cons;
    }

    /**
     *  Renders a row
     *  
     *  @param string $name
     *  @param string $value
     *  @param array $attributes
     *  @return string
     */
    private static function render_row($name, $value, array $attributes = null)
    {
        return html_writer::tag('tr',
            html_writer::tag('th', $name) . html_writer::tag('td', $value),
            $attributes
            );
    }

    /**
     *  Renders an input element
     *  
     *  @param string $name
     *  @param string $value
     *  @param string $type
     *  @param array $attributes
     *  @return string
     */
    private static function render_input($name, $value, $type = 'text', array $attributes = null)
    {
        $attrs = array('type' => $type, 'name' => $name, 'value' => $value);
        if ($attributes !== null)
            $attrs += $attributes;
        return html_writer::empty_tag('input', $attrs);
    }

    /**
     *  Renders a URL link
     *  
     *  @param string $url
     *  @return string
     */
    private static function render_url($url)
    {
        return !empty($url) && ($host = @parse_url($url, PHP_URL_HOST))
             ? html_writer::link($url, $host, array('title' => $url))
             : $url;
    }
	
    /**
     *  Renders rating starts
     *  
     *  @global core_renderer $OUTPUT
     *  @param float $rating
     *  @return string
     */
    private static function render_rating($rating)
    {
        global $OUTPUT;
        static $stars = null;
        if ($stars === null) {
            $stars = array(
                0 => $OUTPUT->pix_icon('star-none', '', 'local_majhub'),
                1 => $OUTPUT->pix_icon('star-half', '', 'local_majhub'),
                2 => $OUTPUT->pix_icon('star-full', '', 'local_majhub'),
                );
        }
        $rating = (int)max(0, min(round($rating), 10));
        $html = html_writer::start_tag('span', array('class' => 'stars'));
        for ($i = 0; $i < 5; $i++)
            $html .= $stars[max(0, min($rating - $i * 2, 2))];
        $html .= html_writer::end_tag('span');
        return $html;
    }
}
