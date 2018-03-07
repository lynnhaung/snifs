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
 * Post services
 *
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\service;

use mod_hsuforum\attachments;
use mod_hsuforum\event\post_created;
use mod_hsuforum\response\json_response;
use mod_hsuforum\upload_file;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__DIR__).'/response/json_response.php');
require_once(dirname(__DIR__).'/upload_file.php');
require_once(dirname(dirname(__DIR__)).'/lib.php');

/**
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_service {
    /**
     * @var discussion_service
     */
    protected $discussionservice;

    /**
     * @var \moodle_database
     */
    protected $db;

    public function __construct(discussion_service $discussionservice = null, \moodle_database $db = null) {
        global $DB;

        if (is_null($discussionservice)) {
            $discussionservice = new discussion_service();
        }
        if (is_null($db)) {
            $db = $DB;
        }
        $this->discussionservice = $discussionservice;
        $this->db = $db;
    }

    /**
     * Does all the grunt work for adding a reply to a discussion
     *
     * @param object $course
     * @param object $cm
     * @param object $forum
     * @param \context_module $context
     * @param object $discussion
     * @param object $parent The parent post
     * @param array $options These override default post values, EG: set the post message with this
     * @return json_response
     */
    public function handle_reply($course, $cm, $forum, $context, $discussion, $parent, array $options) {
        $uploader = new upload_file(
            new attachments($context), \mod_hsuforum_post_form::attachment_options($forum)
        );

        $post   = $this->create_post_object($discussion, $parent, $context, $options);
        $errors = $this->validate_post($course, $cm, $forum, $context, $discussion, $post, $uploader);

        if (!empty($errors)) {
            return $this->create_error_response($errors);
        }
        $this->save_post($discussion, $post, $uploader);
        $this->trigger_post_created($course, $context, $cm, $forum, $discussion, $post);

        return new json_response((object) array(
            'eventaction'  => 'postcreated',
            'discussionid' => (int) $discussion->id,
            'postid'       => (int) $post->id,
            'livelog'      => get_string('postcreated', 'hsuforum'),
            'html'         => $this->discussionservice->render_discussion($discussion->id),
        ));
    }

    /**
     * Does all the grunt work for updating a post
     *
     * @param object $course
     * @param object $cm
     * @param object $forum
     * @param \context_module $context
     * @param object $discussion
     * @param object $post
     * @param array $deletefiles
     * @param array $options These override default post values, EG: set the post message with this
     * @return json_response
     */
    public function handle_update_post($course, $cm, $forum, $context, $discussion, $post, array $deletefiles = array(), array $options) {

        $this->require_can_edit_post($forum, $context, $discussion, $post);

        $uploader = new upload_file(
            new attachments($context, $deletefiles), \mod_hsuforum_post_form::attachment_options($forum)
        );

        // Apply updates to the post.
        foreach ($options as $name => $value) {
            if (property_exists($post, $name)) {
                $post->$name = $value;
            }
        }
        $post->itemid = empty($options['itemid']) ? 0 : $options['itemid'];

        $errors = $this->validate_post($course, $cm, $forum, $context, $discussion, $post, $uploader);
        if (!empty($errors)) {
            return $this->create_error_response($errors);
        }
        $this->save_post($discussion, $post, $uploader);

        // If the user has access to all groups and they are changing the group, then update the post.
        if (empty($post->parent) && has_capability('mod/hsuforum:movediscussions', $context)) {
            $this->db->set_field('hsuforum_discussions', 'groupid', $options['groupid'], array('id' => $discussion->id));
        }

        add_to_log($course->id, 'hsuforum', 'update post',
            "discuss.php?d=$discussion->id#p$post->id&amp;parent=$post->id", $post->id, $cm->id);

        return new json_response((object) array(
            'eventaction'  => 'postupdated',
            'discussionid' => (int) $discussion->id,
            'postid'       => (int) $post->id,
            'livelog'      => get_string('postwasupdated', 'hsuforum'),
            'html'         => $this->discussionservice->render_discussion($discussion->id),
        ));
    }

    /**
     * Require that the current user can edit the post or
     * discussion
     *
     * @param object $forum
     * @param \context_module $context
     * @param object $discussion
     * @param object $post
     */
    public function require_can_edit_post($forum, \context_module $context, $discussion, $post) {
        global $CFG, $USER;

        if (!($forum->type == 'news' && !$post->parent && $discussion->timestart > time())) {
            if (((time() - $post->created) > $CFG->maxeditingtime) and
                !has_capability('mod/hsuforum:editanypost', $context)
            ) {
                print_error('maxtimehaspassed', 'hsuforum', '', format_time($CFG->maxeditingtime));
            }
        }
        if (($post->userid <> $USER->id) && !has_capability('mod/hsuforum:editanypost', $context)) {
            print_error('cannoteditposts', 'hsuforum');
        }
    }

    /**
     * Creates the post object to be saved.
     *
     * @param object $discussion
     * @param object $parent The parent post
     * @param \context_module $context
     * @param array $options These override default post values, EG: set the post message with this
     * @return \stdClass
     */
    public function create_post_object($discussion, $parent, $context, array $options = array()) {
        $post                = new \stdClass;
        $post->course        = $discussion->course;
        $post->forum         = $discussion->forum;
        $post->discussion    = $discussion->id;
        $post->parent        = $parent->id;
        $post->reveal        = 0;
        $post->privatereply  = 0;
        $post->mailnow       = 0;
        $post->subject       = $parent->subject;
        $post->attachment    = '';
        $post->message       = '';
        $post->messageformat = FORMAT_MOODLE;
        $post->messagetrust  = trusttext_trusted($context);
        $post->itemid        = 0; // For text editor stuffs.
        $post->groupid       = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

        $strre = get_string('re', 'hsuforum');
        if (!(substr($post->subject, 0, strlen($strre)) == $strre)) {
            $post->subject = $strre.' '.$post->subject;
        }
        foreach ($options as $name => $value) {
            if (property_exists($post, $name)) {
                $post->$name = $value;
            }
        }
        return $post;
    }

    /**
     * Validates the submitted post and any submitted files
     *
     * @param object $course
     * @param object $cm
     * @param object $forum
     * @param \context_module $context
     * @param object $discussion
     * @param object $post
     * @param upload_file $uploader
     * @return moodle_exception[]
     */
    public function validate_post($course, $cm, $forum, $context, $discussion, $post, upload_file $uploader) {
        global $USER;

        $errors = array();
        if (!hsuforum_user_can_post($forum, $discussion, null, $cm, $course, $context)) {
            $errors[] = new \moodle_exception('nopostforum', 'hsuforum');
        }
        if (!empty($post->id)) {
            if (!(($post->userid == $USER->id && (has_capability('mod/hsuforum:replypost', $context)
                        || has_capability('mod/hsuforum:startdiscussion', $context))) ||
                has_capability('mod/hsuforum:editanypost', $context))
            ) {
                $errors[] = new \moodle_exception('cannotupdatepost', 'hsuforum');
            }
        }
        if (empty($post->id)) {
            try {
                hsuforum_check_throttling($forum, $cm, false);
            } catch (\Exception $e) {
                $errors[] = $e;
            }
        }
        $subject = trim($post->subject);
        if (empty($subject)) {
            $errors[] = new \moodle_exception('subjectisrequired', 'hsuforum');
        }
        $message = trim($post->message);
        if (empty($message)) {
            $errors[] = new \moodle_exception('messageisrequired', 'hsuforum');
        }
        if ($uploader->was_file_uploaded()) {
            try {
                $uploader->validate_files(empty($post->id) ? 0 : $post->id);
            } catch (\Exception $e) {
                $errors[] = $e;
            }
        }
        return $errors;
    }

    /**
     * Save the post to the DB
     *
     * @param object $discussion
     * @param object $post
     * @param upload_file $uploader
     */
    public function save_post($discussion, $post, upload_file $uploader) {
        $message = '';

        // Because the following functions require these...
        $post->forum     = $discussion->forum;
        $post->course    = $discussion->course;
        $post->timestart = $discussion->timestart;
        $post->timeend   = $discussion->timeend;

        if (!empty($post->id)) {
            hsuforum_update_post($post, null, $message, $uploader);
        } else {
            hsuforum_add_new_post($post, null, $message, $uploader);
        }
    }

    /**
     * Log, update completion info and trigger event
     *
     * @param object $course
     * @param \context_module $context
     * @param object $cm
     * @param object $forum
     * @param object $discussion
     * @param object $post
     */
    public function trigger_post_created($course, \context_module $context, $cm, $forum, $discussion, $post) {
        global $CFG;

        require_once($CFG->libdir.'/completionlib.php');

        add_to_log($course->id, 'hsuforum', 'add post',
            "discuss.php?d=$post->discussion&amp;parent=$post->id", $post->id, $cm->id);

        // Update completion state
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm) &&
            ($forum->completionreplies || $forum->completionposts)
        ) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        $event = post_created::create(array(
            'objectid' => $post->id,
            'courseid' => $course->id,
            'context'  => $context,
            'other'    => array(
                'discussionid' => $discussion->id,
            )
        ));
        $event->add_record_snapshot('hsuforum_discussions', $discussion);
        $event->trigger();
    }

    /**
     * @param array $errors
     * @return json_response
     */
    public function create_error_response(array $errors) {
        global $PAGE;

        /** @var \mod_hsuforum_renderer $renderer */
        $renderer = $PAGE->get_renderer('mod_hsuforum');

        return new json_response((object) array(
            'errors' => true,
            'html'   => $renderer->validation_errors($errors),
        ));
    }
}