<?php // $Id: version.php 212 2013-02-07 01:38:19Z malu $

defined('MOODLE_INTERNAL') || die;

$plugin->version   = 2018012401;
$plugin->release   = '2.3, release 3';
$plugin->requires  = 2012062500.00; // Moodle 2.3.0
$plugin->component = 'block_majhub';

$plugin->dependencies = array(
    'local_majhub' => 2018011500,
);
