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
 * @package mod-hsuforum
 * @copyright Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author Mark Nielsen
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_hsuforum_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $COURSE, $PAGE;

        $mform    =& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('forumname', 'hsuforum'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->add_intro_editor(true, get_string('forumintro', 'hsuforum'));

        $forumtypes = hsuforum_get_hsuforum_types();
        core_collator::asort($forumtypes, core_collator::SORT_STRING);
        $mform->addElement('select', 'type', get_string('forumtype', 'hsuforum'), $forumtypes);
        $mform->addHelpButton('type', 'forumtype', 'hsuforum');
        $mform->setDefault('type', 'general');

        $mform->addElement('advcheckbox', 'anonymous', get_string('anonymous', 'hsuforum'));
        $mform->addHelpButton('anonymous', 'anonymous', 'hsuforum');

        // Attachments and word count.
        $mform->addElement('header', 'attachmentswordcounthdr', get_string('attachmentswordcount', 'hsuforum'));

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, 0, $CFG->hsuforum_maxbytes);
        $choices[1] = get_string('uploadnotallowed');
        $mform->addElement('select', 'maxbytes', get_string('maxattachmentsize', 'hsuforum'), $choices);
        $mform->addHelpButton('maxbytes', 'maxattachmentsize', 'hsuforum');
        $mform->setDefault('maxbytes', $CFG->hsuforum_maxbytes);

        $choices = array(0,1,2,3,4,5,6,7,8,9,10,20,50,100);
        $mform->addElement('select', 'maxattachments', get_string('maxattachments', 'hsuforum'), $choices);
        $mform->addHelpButton('maxattachments', 'maxattachments', 'hsuforum');
        $mform->setDefault('maxattachments', $CFG->hsuforum_maxattachments);

        $mform->addElement('selectyesno', 'displaywordcount', get_string('displaywordcount', 'hsuforum'));
        $mform->addHelpButton('displaywordcount', 'displaywordcount', 'hsuforum');
        $mform->setDefault('displaywordcount', 0);

        // Subscription and tracking.
        $mform->addElement('header', 'subscriptionandtrackinghdr', get_string('subscriptionandtracking', 'hsuforum'));

        $options = array();
        $options[HSUFORUM_CHOOSESUBSCRIBE] = get_string('subscriptionoptional', 'hsuforum');
        $options[HSUFORUM_FORCESUBSCRIBE] = get_string('subscriptionforced', 'hsuforum');
        $options[HSUFORUM_INITIALSUBSCRIBE] = get_string('subscriptionauto', 'hsuforum');
        $options[HSUFORUM_DISALLOWSUBSCRIBE] = get_string('subscriptiondisabled','hsuforum');
        $mform->addElement('select', 'forcesubscribe', get_string('subscriptionmode', 'hsuforum'), $options);
        $mform->addHelpButton('forcesubscribe', 'subscriptionmode', 'hsuforum');

        $options = array();
        $options[HSUFORUM_TRACKING_OPTIONAL] = get_string('trackingoptional', 'hsuforum');
        $options[HSUFORUM_TRACKING_OFF] = get_string('trackingoff', 'hsuforum');
        if ($CFG->hsuforum_allowforcedreadtracking) {
            $options[HSUFORUM_TRACKING_FORCED] = get_string('trackingon', 'hsuforum');
        }
        $mform->addElement('select', 'trackingtype', get_string('trackingtype', 'hsuforum'), $options);
        $mform->addHelpButton('trackingtype', 'trackingtype', 'hsuforum');
        $default = $CFG->hsuforum_trackingtype;
        if ((!$CFG->hsuforum_allowforcedreadtracking) && ($default == HSUFORUM_TRACKING_FORCED)) {
            $default = HSUFORUM_TRACKING_OPTIONAL;
        }
        $mform->setDefault('trackingtype', $default);

        if ($CFG->enablerssfeeds && isset($CFG->hsuforum_enablerssfeeds) && $CFG->hsuforum_enablerssfeeds) {
//-------------------------------------------------------------------------------
            $mform->addElement('header', 'rssheader', get_string('rss'));
            $choices = array();
            $choices[0] = get_string('none');
            $choices[1] = get_string('discussions', 'hsuforum');
            $choices[2] = get_string('posts', 'hsuforum');
            $mform->addElement('select', 'rsstype', get_string('rsstype'), $choices);
            $mform->addHelpButton('rsstype', 'rsstype', 'hsuforum');

            $choices = array();
            $choices[0] = '0';
            $choices[1] = '1';
            $choices[2] = '2';
            $choices[3] = '3';
            $choices[4] = '4';
            $choices[5] = '5';
            $choices[10] = '10';
            $choices[15] = '15';
            $choices[20] = '20';
            $choices[25] = '25';
            $choices[30] = '30';
            $choices[40] = '40';
            $choices[50] = '50';
            $mform->addElement('select', 'rssarticles', get_string('rssarticles'), $choices);
            $mform->addHelpButton('rssarticles', 'rssarticles', 'hsuforum');
            $mform->disabledIf('rssarticles', 'rsstype', 'eq', '0');
        }

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'blockafterheader', get_string('blockafter', 'hsuforum'));
        $options = array();
        $options[0] = get_string('blockperioddisabled','hsuforum');
        $options[60*60*24]   = '1 '.get_string('day');
        $options[60*60*24*2] = '2 '.get_string('days');
        $options[60*60*24*3] = '3 '.get_string('days');
        $options[60*60*24*4] = '4 '.get_string('days');
        $options[60*60*24*5] = '5 '.get_string('days');
        $options[60*60*24*6] = '6 '.get_string('days');
        $options[60*60*24*7] = '1 '.get_string('week');
        $mform->addElement('select', 'blockperiod', get_string('blockperiod', 'hsuforum'), $options);
        $mform->addHelpButton('blockperiod', 'blockperiod', 'hsuforum');

        $mform->addElement('text', 'blockafter', get_string('blockafter', 'hsuforum'));
        $mform->setType('blockafter', PARAM_INT);
        $mform->setDefault('blockafter', '0');
        $mform->addRule('blockafter', null, 'numeric', null, 'client');
        $mform->addHelpButton('blockafter', 'blockafter', 'hsuforum');
        $mform->disabledIf('blockafter', 'blockperiod', 'eq', 0);

        $mform->addElement('text', 'warnafter', get_string('warnafter', 'hsuforum'));
        $mform->setType('warnafter', PARAM_INT);
        $mform->setDefault('warnafter', '0');
        $mform->addRule('warnafter', null, 'numeric', null, 'client');
        $mform->addHelpButton('warnafter', 'warnafter', 'hsuforum');
        $mform->disabledIf('warnafter', 'blockperiod', 'eq', 0);

        $coursecontext = context_course::instance($COURSE->id);
        plagiarism_get_form_elements_module($mform, $coursecontext, 'mod_hsuforum');

//-------------------------------------------------------------------------------

        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();

        $mform->addElement('select', 'gradetype', get_string('gradetype', 'hsuforum'), hsuforum_get_grading_types());
        $mform->setDefault('gradetype', HSUFORUM_GRADETYPE_NONE);
        $mform->setType('gradetype', PARAM_INT);
        $mform->addHelpButton('gradetype', 'gradetype', 'hsuforum');

        $mform->insertElementBefore($mform->removeElement('gradetype'), 'grade');
        $scale = $mform->insertElementBefore($mform->removeElement('scale'), 'grade');
        $scale->setLabel(get_string('grade'));

        // Done abusing this poor fellow...
        $mform->removeElement('grade');

        if ($this->_features->advancedgrading) {
            foreach ($this->current->_advancedgradingdata['areas'] as $areaname => $areadata) {
                $mform->disabledIf('advancedgradingmethod_'.$areaname, 'gradetype', 'neq', HSUFORUM_GRADETYPE_MANUAL);
            }
        }
        $key = array_search('scale', $mform->_dependencies['assessed']['eq'][0]);
        if ($key !== false) {
            unset($mform->_dependencies['assessed']['eq'][0][$key]);
        }
        $mform->disabledIf('gradecat', 'gradetype', 'eq', HSUFORUM_GRADETYPE_NONE);
//-------------------------------------------------------------------------------
// buttons
        $this->add_action_buttons();

        if (!$this->_features->advancedgrading) {
            /** @var $renderer mod_hsuforum_renderer */
            $renderer = $PAGE->get_renderer('mod_hsuforum');
            $PAGE->requires->js_init_call('M.mod_hsuforum.init_modform', array(HSUFORUM_GRADETYPE_MANUAL), false, $renderer->get_js_module());
        }
    }

    function standard_grading_coursemodule_elements() {
        $this->_features->rating = false;
        parent::standard_grading_coursemodule_elements();
        $this->_features->rating = true;
    }

    function definition_after_data() {
        $this->_features->rating = false;
        parent::definition_after_data();
        $this->_features->rating = true;

        $mform     =& $this->_form;
        $type      =& $mform->getElement('type');
        $typevalue = $mform->getElementValue('type');

        //we don't want to have these appear as possible selections in the form but
        //we want the form to display them if they are set.
        if ($typevalue[0]=='news') {
            $type->addOption(get_string('namenews', 'hsuforum'), 'news');
            $mform->addHelpButton('type', 'namenews', 'hsuforum');
            $type->freeze();
            $type->setPersistantFreeze(true);
        }
        if ($typevalue[0]=='social') {
            $type->addOption(get_string('namesocial', 'hsuforum'), 'social');
            $type->freeze();
            $type->setPersistantFreeze(true);
        }

    }

    function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        // Set up the completion checkboxes which aren't part of standard data.
        // We also make the default value (if you turn on the checkbox) for those
        // numbers to be 1, this will not apply unless checkbox is ticked.
        $default_values['completiondiscussionsenabled']=
            !empty($default_values['completiondiscussions']) ? 1 : 0;
        if (empty($default_values['completiondiscussions'])) {
            $default_values['completiondiscussions']=1;
        }
        $default_values['completionrepliesenabled']=
            !empty($default_values['completionreplies']) ? 1 : 0;
        if (empty($default_values['completionreplies'])) {
            $default_values['completionreplies']=1;
        }
        $default_values['completionpostsenabled']=
            !empty($default_values['completionposts']) ? 1 : 0;
        if (empty($default_values['completionposts'])) {
            $default_values['completionposts']=1;
        }
    }

      function add_completion_rules() {
        $mform =& $this->_form;

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completionpostsenabled', '', get_string('completionposts','hsuforum'));
        $group[] =& $mform->createElement('text', 'completionposts', '', array('size'=>3));
        $mform->setType('completionposts',PARAM_INT);
        $mform->addGroup($group, 'completionpostsgroup', get_string('completionpostsgroup','hsuforum'), array(' '), false);
        $mform->disabledIf('completionposts','completionpostsenabled','notchecked');

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completiondiscussionsenabled', '', get_string('completiondiscussions','hsuforum'));
        $group[] =& $mform->createElement('text', 'completiondiscussions', '', array('size'=>3));
        $mform->setType('completiondiscussions',PARAM_INT);
        $mform->addGroup($group, 'completiondiscussionsgroup', get_string('completiondiscussionsgroup','hsuforum'), array(' '), false);
        $mform->disabledIf('completiondiscussions','completiondiscussionsenabled','notchecked');

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completionrepliesenabled', '', get_string('completionreplies','hsuforum'));
        $group[] =& $mform->createElement('text', 'completionreplies', '', array('size'=>3));
        $mform->setType('completionreplies',PARAM_INT);
        $mform->addGroup($group, 'completionrepliesgroup', get_string('completionrepliesgroup','hsuforum'), array(' '), false);
        $mform->disabledIf('completionreplies','completionrepliesenabled','notchecked');

        return array('completiondiscussionsgroup','completionrepliesgroup','completionpostsgroup');
    }

    function completion_rule_enabled($data) {
        return (!empty($data['completiondiscussionsenabled']) && $data['completiondiscussions']!=0) ||
            (!empty($data['completionrepliesenabled']) && $data['completionreplies']!=0) ||
            (!empty($data['completionpostsenabled']) && $data['completionposts']!=0);
    }

    function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return false;
        }
        // Turn off completion settings if the checkboxes aren't ticked
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion==COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completiondiscussionsenabled) || !$autocompletion) {
                $data->completiondiscussions = 0;
            }
            if (empty($data->completionrepliesenabled) || !$autocompletion) {
                $data->completionreplies = 0;
            }
            if (empty($data->completionpostsenabled) || !$autocompletion) {
                $data->completionposts = 0;
            }
        }
        return $data;
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['completionusegrade'])) {
            // This is the same logic as in hsuforum_grade_item_update() for determining that the gradetype is GRADE_TYPE_NONE
            // If GRADE_TYPE_NONE, then we cannot have this completion criteria because there may be no grade item!
            if ($data['gradetype'] == HSUFORUM_GRADETYPE_NONE or ($data['gradetype'] == HSUFORUM_GRADETYPE_RATING and !$data['assessed']) or $data['scale'] == 0) {
                $errors['completionusegrade'] = get_string('completionusegradeerror', 'hsuforum');
            }
        }
        return $errors;
    }
}

