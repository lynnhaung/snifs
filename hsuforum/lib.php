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
 * @package    mod
 * @subpackage hsuforum
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author Mark Nielsen
 */

defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/eventslib.php');
require_once($CFG->dirroot.'/user/selector/lib.php');

/// CONSTANTS ///////////////////////////////////////////////////////////

define('HSUFORUM_MODE_FLATOLDEST', 1);
define('HSUFORUM_MODE_FLATNEWEST', -1);
define('HSUFORUM_MODE_FLATFIRSTNAME', 4);
define('HSUFORUM_MODE_FLATLASTNAME', 5);
define('HSUFORUM_MODE_THREADED', 2);
define('HSUFORUM_MODE_NESTED', 3);

define('HSUFORUM_CHOOSESUBSCRIBE', 0);
define('HSUFORUM_FORCESUBSCRIBE', 1);
define('HSUFORUM_INITIALSUBSCRIBE', 2);
define('HSUFORUM_DISALLOWSUBSCRIBE',3);

/**
 * HSUFORUM_TRACKING_OFF - Tracking is not available for this forum.
 */
define('HSUFORUM_TRACKING_OFF', 0);

/**
 * HSUFORUM_TRACKING_OPTIONAL - Tracking is based on user preference.
 */
define('HSUFORUM_TRACKING_OPTIONAL', 1);

/**
 * HSUFORUM_TRACKING_FORCED - Tracking is on, regardless of user setting.
 * Treated as HSUFORUM_TRACKING_OPTIONAL if $CFG->hsuforum_allowforcedreadtracking is off.
 */
define('HSUFORUM_TRACKING_FORCED', 2);

/**
 * HSUFORUM_TRACKING_ON - deprecated alias for HSUFORUM_TRACKING_FORCED.
 * @deprecated since 2.6
 */
define('HSUFORUM_TRACKING_ON', 2);

define ('HSUFORUM_GRADETYPE_NONE', 0);
define ('HSUFORUM_GRADETYPE_MANUAL', 1);
define ('HSUFORUM_GRADETYPE_RATING', 2);

define('HSUFORUM_MAILED_PENDING', 0);
define('HSUFORUM_MAILED_SUCCESS', 1);
define('HSUFORUM_MAILED_ERROR', 2);

if (!defined('HSUFORUM_CRON_USER_CACHE')) {
    /** Defines how many full user records are cached in forum cron. */
    define('HSUFORUM_CRON_USER_CACHE', 5000);
}

/// STANDARD FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $forum add forum instance
 * @param mod_hsuforum_mod_form $mform
 * @return int intance id
 */
function hsuforum_add_instance($forum, $mform = null) {
    global $CFG, $DB;

    $forum->timemodified = time();

    if ($forum->gradetype != HSUFORUM_GRADETYPE_MANUAL) {
        foreach ($forum as $name => $value) {
            if (strpos($name, 'advancedgradingmethod_') !== false) {
                $forum->$name = '';
            }
        }
    }

    if (empty($forum->assessed)) {
        $forum->assessed = 0;
    }

    if (empty($forum->ratingtime) or empty($forum->assessed)) {
        $forum->assesstimestart  = 0;
        $forum->assesstimefinish = 0;
    }

    $forum->id = $DB->insert_record('hsuforum', $forum);
    $modcontext = context_module::instance($forum->coursemodule);

    if ($forum->type == 'single') {  // Create related discussion.
        $discussion = new stdClass();
        $discussion->course        = $forum->course;
        $discussion->forum         = $forum->id;
        $discussion->name          = $forum->name;
        $discussion->assessed      = $forum->assessed;
        $discussion->message       = $forum->intro;
        $discussion->messageformat = $forum->introformat;
        $discussion->messagetrust  = trusttext_trusted(context_course::instance($forum->course));
        $discussion->mailnow       = false;
        $discussion->groupid       = -1;

        $message = '';

        $discussion->id = hsuforum_add_discussion($discussion, null, $message);

        if ($mform and $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $discussion = $DB->get_record('hsuforum_discussions', array('id'=>$discussion->id), '*', MUST_EXIST);
            $post = $DB->get_record('hsuforum_posts', array('id'=>$discussion->firstpost), '*', MUST_EXIST);

            $options = array('subdirs'=>true); // Use the same options as intro field!
            $post->message = file_save_draft_area_files($draftid, $modcontext->id, 'mod_hsuforum', 'post', $post->id, $options, $post->message);
            $DB->set_field('hsuforum_posts', 'message', $post->message, array('id'=>$post->id));
        }
    }

    if ($forum->forcesubscribe == HSUFORUM_INITIALSUBSCRIBE) {
        $users = hsuforum_get_potential_subscribers($modcontext, 0, 'u.id, u.email');
        foreach ($users as $user) {
            hsuforum_subscribe($user->id, $forum->id);
        }
    }

    hsuforum_grade_item_update($forum);

    return $forum->id;
}


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $forum forum instance (with magic quotes)
 * @return bool success
 */
function hsuforum_update_instance($forum, $mform) {
    global $DB, $OUTPUT, $USER;

    $forum->timemodified = time();
    $forum->id           = $forum->instance;

    if ($forum->gradetype != HSUFORUM_GRADETYPE_MANUAL) {
        foreach ($forum as $name => $value) {
            if (strpos($name, 'advancedgradingmethod_') !== false) {
                $forum->$name = '';
            }
        }
    }
    if (empty($forum->assessed)) {
        $forum->assessed = 0;
    }

    if (empty($forum->ratingtime) or empty($forum->assessed)) {
        $forum->assesstimestart  = 0;
        $forum->assesstimefinish = 0;
    }

    $oldforum = $DB->get_record('hsuforum', array('id'=>$forum->id));

    // MDL-3942 - if the aggregation type or scale (i.e. max grade) changes then recalculate the grades for the entire forum
    // if  scale changes - do we need to recheck the ratings, if ratings higher than scale how do we want to respond?
    // for count and sum aggregation types the grade we check to make sure they do not exceed the scale (i.e. max score) when calculating the grade
    if (($oldforum->assessed<>$forum->assessed) or ($oldforum->scale<>$forum->scale)) {
        hsuforum_update_grades($forum); // recalculate grades for the forum
    }

    if ($forum->type == 'single') {  // Update related discussion and post.
        $discussions = $DB->get_records('hsuforum_discussions', array('forum'=>$forum->id), 'timemodified ASC');
        if (!empty($discussions)) {
            if (count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'hsuforum'));
            }
            $discussion = array_pop($discussions);
        } else {
            // try to recover by creating initial discussion - MDL-16262
            $discussion = new stdClass();
            $discussion->course          = $forum->course;
            $discussion->forum           = $forum->id;
            $discussion->name            = $forum->name;
            $discussion->assessed        = $forum->assessed;
            $discussion->message         = $forum->intro;
            $discussion->messageformat   = $forum->introformat;
            $discussion->messagetrust    = true;
            $discussion->mailnow         = false;
            $discussion->groupid         = -1;

            $message = '';

            hsuforum_add_discussion($discussion, null, $message);

            if (! $discussion = $DB->get_record('hsuforum_discussions', array('forum'=>$forum->id))) {
                print_error('cannotadd', 'hsuforum');
            }
        }
        if (! $post = $DB->get_record('hsuforum_posts', array('id'=>$discussion->firstpost))) {
            print_error('cannotfindfirstpost', 'hsuforum');
        }

        $cm         = get_coursemodule_from_instance('hsuforum', $forum->id);
        $modcontext = context_module::instance($cm->id, MUST_EXIST);

        $post = $DB->get_record('hsuforum_posts', array('id'=>$discussion->firstpost), '*', MUST_EXIST);
        $post->subject       = $forum->name;
        $post->message       = $forum->intro;
        $post->messageformat = $forum->introformat;
        $post->messagetrust  = trusttext_trusted($modcontext);
        $post->modified      = $forum->timemodified;
        $post->userid        = $USER->id;    // MDL-18599, so that current teacher can take ownership of activities.

        if ($mform and $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $options = array('subdirs'=>true); // Use the same options as intro field!
            $post->message = file_save_draft_area_files($draftid, $modcontext->id, 'mod_hsuforum', 'post', $post->id, $options, $post->message);
        }

        $DB->update_record('hsuforum_posts', $post);
        $discussion->name = $forum->name;
        $DB->update_record('hsuforum_discussions', $discussion);
    }

    $DB->update_record('hsuforum', $forum);

    $modcontext = context_module::instance($forum->coursemodule);
    if (($forum->forcesubscribe == HSUFORUM_INITIALSUBSCRIBE) && ($oldforum->forcesubscribe <> $forum->forcesubscribe)) {
        $users = hsuforum_get_potential_subscribers($modcontext, 0, 'u.id, u.email', '');
        foreach ($users as $user) {
            hsuforum_subscribe($user->id, $forum->id);
        }
    }

    hsuforum_grade_item_update($forum);

    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id forum instance id
 * @return bool success
 */
function hsuforum_delete_instance($id) {
    global $DB;

    if (!$forum = $DB->get_record('hsuforum', array('id'=>$id))) {
        return false;
    }
    if (!$cm = get_coursemodule_from_instance('hsuforum', $forum->id)) {
        return false;
    }
    if (!$course = $DB->get_record('course', array('id'=>$cm->course))) {
        return false;
    }

    $context = context_module::instance($cm->id);

    // now get rid of all files
    $fs = get_file_storage();
    $fs->delete_area_files($context->id);

    $result = true;

    if ($discussions = $DB->get_records('hsuforum_discussions', array('forum'=>$forum->id))) {
        foreach ($discussions as $discussion) {
            if (!hsuforum_delete_discussion($discussion, true, $course, $cm, $forum)) {
                $result = false;
            }
        }
    }

    if (!$DB->delete_records('hsuforum_digests', array('forum' => $forum->id))) {
        $result = false;
    }

    if (!$DB->delete_records('hsuforum_subscriptions', array('forum'=>$forum->id))) {
        $result = false;
    }

    hsuforum_tp_delete_read_records(-1, -1, -1, $forum->id);

    if (!$DB->delete_records('hsuforum', array('id'=>$forum->id))) {
        $result = false;
    }

    hsuforum_grade_item_delete($forum);

    return $result;
}


/**
 * Indicates API features that the forum supports.
 *
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_COMPLETION_HAS_RULES
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 */
function hsuforum_supports($feature) {
    global $CFG;

    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_RATE:                    return true;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_ADVANCED_GRADING:        return (!empty($CFG->mod_hsuforum_grading_interface));
        case FEATURE_PLAGIARISM:              return true;

        default: return null;
    }
}

/**
 * Lists all gradable areas for the advanced grading methods
 *
 * @return array
 */
function hsuforum_grading_areas_list() {
    return array('posts' => get_string('posts', 'hsuforum'));
}

/**
 * Obtains the automatic completion state for this forum based on any conditions
 * in forum settings.
 *
 * @global object
 * @global object
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function hsuforum_get_completion_state($course,$cm,$userid,$type) {
    global $CFG,$DB;

    // Get forum details
    if (!($forum=$DB->get_record('hsuforum',array('id'=>$cm->instance)))) {
        throw new Exception("Can't find forum {$cm->instance}");
    }

    $result=$type; // Default return value

    $postcountparams=array('userid'=>$userid,'forumid'=>$forum->id);
    $postcountsql="
SELECT
    COUNT(1)
FROM
    {hsuforum_posts} fp
    INNER JOIN {hsuforum_discussions} fd ON fp.discussion=fd.id
WHERE
    fp.userid=:userid AND fd.forum=:forumid";

    if ($forum->completiondiscussions) {
        $value = $forum->completiondiscussions <=
                 $DB->count_records('hsuforum_discussions',array('forum'=>$forum->id,'userid'=>$userid));
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($forum->completionreplies) {
        $value = $forum->completionreplies <=
                 $DB->get_field_sql( $postcountsql.' AND fp.parent<>0',$postcountparams);
        if ($type==COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($forum->completionposts) {
        $value = $forum->completionposts <= $DB->get_field_sql($postcountsql,$postcountparams);
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }

    return $result;
}

/**
 * Create a message-id string to use in the custom headers of forum notification emails
 *
 * message-id is used by email clients to identify emails and to nest conversations
 *
 * @param int $postid The ID of the forum post we are notifying the user about
 * @param int $usertoid The ID of the user being notified
 * @param string $hostname The server's hostname
 * @return string A unique message-id
 */
function hsuforum_get_email_message_id($postid, $usertoid, $hostname) {
    return '<'.hash('sha256',$postid.'to'.$usertoid).'@'.$hostname.'>';
}

/**
 * Removes properties from user record that are not necessary
 * for sending post notifications.
 * @param stdClass $user
 * @return void, $user parameter is modified
 */
function hsuforum_cron_minimise_user_record(stdClass $user) {

    // We store large amount of users in one huge array,
    // make sure we do not store info there we do not actually need
    // in mail generation code or messaging.

    unset($user->institution);
    unset($user->department);
    unset($user->address);
    unset($user->city);
    unset($user->url);
    unset($user->currentlogin);
    unset($user->description);
    unset($user->descriptionformat);
}

/**
 * Function to be run periodically according to the moodle cron
 * Finds all posts that have yet to be mailed out, and mails them
 * out to all subscribers
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses CONTEXT_COURSE
 * @uses SITEID
 * @uses FORMAT_PLAIN
 * @return void
 */
function hsuforum_cron() {
    global $CFG, $USER, $DB;

    $site = get_site();

    // All users that are subscribed to any post that needs sending,
    // please increase $CFG->extramemorylimit on large sites that
    // send notifications to a large number of users.
    $users = array();
    $userscount = 0; // Cached user counter - count($users) in PHP is horribly slow!!!

    // status arrays
    $mailcount  = array();
    $errorcount = array();

    // caches
    $discussions     = array();
    $forums          = array();
    $courses         = array();
    $coursemodules   = array();
    $subscribedusers = array();
    $discussionsubscribers = array();

    require_once(__DIR__.'/repository/discussion.php');
    $discussionrepo = new hsuforum_repository_discussion();

    // Posts older than 2 days will not be mailed.  This is to avoid the problem where
    // cron has not been running for a long time, and then suddenly people are flooded
    // with mail from the past few weeks or months
    $timenow   = time();
    $endtime   = $timenow - $CFG->maxeditingtime;
    $starttime = $endtime - 48 * 3600;   // Two days earlier

    // Get the list of forum subscriptions for per-user per-forum maildigest settings.
    $digestsset = $DB->get_recordset('hsuforum_digests', null, '', 'id, userid, forum, maildigest');
    $digests = array();
    foreach ($digestsset as $thisrow) {
        if (!isset($digests[$thisrow->forum])) {
            $digests[$thisrow->forum] = array();
        }
        $digests[$thisrow->forum][$thisrow->userid] = $thisrow->maildigest;
    }
    $digestsset->close();

    if ($posts = hsuforum_get_unmailed_posts($starttime, $endtime, $timenow)) {
        // Mark them all now as being mailed.  It's unlikely but possible there
        // might be an error later so that a post is NOT actually mailed out,
        // but since mail isn't crucial, we can accept this risk.  Doing it now
        // prevents the risk of duplicated mails, which is a worse problem.

        if (!hsuforum_mark_old_posts_as_mailed($endtime)) {
            mtrace('Errors occurred while trying to mark some posts as being mailed.');
            return false;  // Don't continue trying to mail them, in case we are in a cron loop
        }

        // checking post validity, and adding users to loop through later
        foreach ($posts as $pid => $post) {

            $discussionid = $post->discussion;
            if (!isset($discussions[$discussionid])) {
                if ($discussion = $DB->get_record('hsuforum_discussions', array('id'=> $post->discussion))) {
                    $discussions[$discussionid] = $discussion;
                } else {
                    mtrace('Could not find discussion '.$discussionid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $forumid = $discussions[$discussionid]->forum;
            if (!isset($forums[$forumid])) {
                if ($forum = $DB->get_record('hsuforum', array('id' => $forumid))) {
                    $forums[$forumid] = $forum;
                } else {
                    mtrace('Could not find forum '.$forumid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $courseid = $forums[$forumid]->course;
            if (!isset($courses[$courseid])) {
                if ($course = $DB->get_record('course', array('id' => $courseid))) {
                    $courses[$courseid] = $course;
                } else {
                    mtrace('Could not find course '.$courseid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            if (!isset($coursemodules[$forumid])) {
                if ($cm = get_coursemodule_from_instance('hsuforum', $forumid, $courseid)) {
                    $coursemodules[$forumid] = $cm;
                } else {
                    mtrace('Could not find course module for forum '.$forumid);
                    unset($posts[$pid]);
                    continue;
                }
            }


            // caching subscribed users of each forum
            if (!isset($subscribedusers[$forumid])) {
                $modcontext = context_module::instance($coursemodules[$forumid]->id);
                if ($subusers = hsuforum_subscribed_users($courses[$courseid], $forums[$forumid], 0, $modcontext, "u.*")) {
                    foreach ($subusers as $postuser) {
                        // this user is subscribed to this forum
                        $subscribedusers[$forumid][$postuser->id] = $postuser->id;
                        $userscount++;
                        if ($userscount > HSUFORUM_CRON_USER_CACHE) {
                            // Store minimal user info.
                            $minuser = new stdClass();
                            $minuser->id = $postuser->id;
                            $users[$postuser->id] = $minuser;
                        } else {
                            // Cache full user record.
                            hsuforum_cron_minimise_user_record($postuser);
                            $users[$postuser->id] = $postuser;
                        }
                    }
                    // Release memory.
                    unset($subusers);
                    unset($postuser);
                }
            }

            // caching subscribed users of each discussion
            if (!isset($discussionsubscribers[$discussionid])) {
                $modcontext = context_module::instance($coursemodules[$forumid]->id);
                if ($subusers = $discussionrepo->get_subscribed_users($forums[$forumid], $discussions[$discussionid], $modcontext, 0, null, array(), 'u.email ASC')) {
                    // Get a list of the users subscribed to discussions in the hsuforum.
                    foreach ($subusers as $postuser) {
                        unset($postuser->description); // not necessary
                        // the user is subscribed to this discussion
                        $discussionsubscribers[$discussionid][$postuser->id] = $postuser->id;
                        // this user is a user we have to process later
                        $users[$postuser->id] = $postuser;
                    }
                }
            }

            $mailcount[$pid] = 0;
            $errorcount[$pid] = 0;
        }
    }

    if ($users && $posts) {

        $urlinfo = parse_url($CFG->wwwroot);
        $hostname = $urlinfo['host'];

        foreach ($users as $userto) {

            @set_time_limit(120); // terminate if processing of any account takes longer than 2 minutes

            mtrace('Processing user '.$userto->id);

            // Init user caches - we keep the cache for one cycle only,
            // otherwise it could consume too much memory.
            if (isset($userto->username)) {
                $userto = clone($userto);
            } else {
                $userto = $DB->get_record('user', array('id' => $userto->id));
                hsuforum_cron_minimise_user_record($userto);
            }
            $userto->viewfullnames = array();
            $userto->canpost       = array();
            $userto->markposts     = array();

            // set this so that the capabilities are cached, and environment matches receiving user
            cron_setup_user($userto);

            // reset the caches
            foreach ($coursemodules as $forumid=>$unused) {
                $coursemodules[$forumid]->cache       = new stdClass();
                $coursemodules[$forumid]->cache->caps = array();
                unset($coursemodules[$forumid]->uservisible);
            }

            foreach ($posts as $pid => $post) {

                // Set up the environment for the post, discussion, forum, course
                $discussion = $discussions[$post->discussion];
                $forum      = $forums[$discussion->forum];
                $course     = $courses[$forum->course];
                $cm         =& $coursemodules[$forum->id];

                // Do some checks  to see if we can bail out now
                // Only active enrolled users are in the list of subscribers
                if (!isset($subscribedusers[$forum->id][$userto->id])) {
                    if (!isset($discussionsubscribers[$post->discussion][$userto->id])) {
                        continue; // user does not subscribe to this forum
                    }
                }

                // Don't send email if the forum is Q&A and the user has not posted
                // Initial topics are still mailed
                if ($forum->type == 'qanda' && !hsuforum_get_user_posted_time($discussion->id, $userto->id) && $pid != $discussion->firstpost) {
                    mtrace('Did not email '.$userto->id.' because user has not posted in discussion');
                    continue;
                }

                // Get info about the sending user
                if (array_key_exists($post->userid, $users)) { // we might know him/her already
                    $userfrom = $users[$post->userid];
                    if (!isset($userfrom->idnumber)) {
                        // Minimalised user info, fetch full record.
                        $userfrom = $DB->get_record('user', array('id' => $userfrom->id));
                        hsuforum_cron_minimise_user_record($userfrom);
                    }

                } else if ($userfrom = $DB->get_record('user', array('id' => $post->userid))) {
                    hsuforum_cron_minimise_user_record($userfrom);
                    // Fetch only once if possible, we can add it to user list, it will be skipped anyway.
                    if ($userscount <= HSUFORUM_CRON_USER_CACHE) {
                        $userscount++;
                        $users[$userfrom->id] = $userfrom;
                    }

                } else {
                    mtrace('Could not find user '.$post->userid);
                    continue;
                }

                //if we want to check that userto and userfrom are not the same person this is probably the spot to do it

                // setup global $COURSE properly - needed for roles and languages
                cron_setup_user($userto, $course);

                // Fill caches
                if (!isset($userto->viewfullnames[$forum->id])) {
                    $modcontext = context_module::instance($cm->id);
                    $userto->viewfullnames[$forum->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                }
                if (!isset($userto->canpost[$discussion->id])) {
                    $modcontext = context_module::instance($cm->id);
                    $userto->canpost[$discussion->id] = hsuforum_user_can_post($forum, $discussion, $userto, $cm, $course, $modcontext);
                }
                if (!isset($userfrom->groups[$forum->id])) {
                    if (!isset($userfrom->groups)) {
                        $userfrom->groups = array();
                        if (isset($users[$userfrom->id])) {
                            $users[$userfrom->id]->groups = array();
                        }
                    }
                    $userfrom->groups[$forum->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                    if (isset($users[$userfrom->id])) {
                        $users[$userfrom->id]->groups[$forum->id] = $userfrom->groups[$forum->id];
                    }
                }

                // Make sure groups allow this user to see this email
                if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
                    if (!groups_group_exists($discussion->groupid)) { // Can't find group
                        continue;                           // Be safe and don't send it to anyone
                    }

                    if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $modcontext)) {
                        // do not send posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
                        continue;
                    }
                }

                // Make sure we're allowed to see it...
                if (!hsuforum_user_can_see_post($forum, $discussion, $post, NULL, $cm)) {
                    mtrace('user '.$userto->id. ' can not see '.$post->id);
                    continue;
                }

                // OK so we need to send the email.

                // Does the user want this post in a digest?  If so postpone it for now.
                $maildigest = hsuforum_get_user_maildigest_bulk($digests, $userto, $forum->id);

                if ($maildigest > 0) {
                    // This user wants the mails to be in digest form
                    $queue = new stdClass();
                    $queue->userid       = $userto->id;
                    $queue->discussionid = $discussion->id;
                    $queue->postid       = $post->id;
                    $queue->timemodified = $post->created;
                    $DB->insert_record('hsuforum_queue', $queue);
                    continue;
                }


                // Prepare to actually send the post now, and build up the content

                $cleanforumname = str_replace('"', "'", strip_tags(format_string($forum->name)));

                $userfrom->customheaders = array (  // Headers to make emails easier to track
                           'Precedence: Bulk',
                           'List-Id: "'.$cleanforumname.'" <moodleforum'.$forum->id.'@'.$hostname.'>',
                           'List-Help: '.$CFG->wwwroot.'/mod/hsuforum/view.php?f='.$forum->id,
                           'Message-ID: '.hsuforum_get_email_message_id($post->id, $userto->id, $hostname),
                           'X-Course-Id: '.$course->id,
                           'X-Course-Name: '.format_string($course->fullname, true)
                );

                if ($post->parent) {  // This post is a reply, so add headers for threading (see MDL-22551)
                    $userfrom->customheaders[] = 'In-Reply-To: '.hsuforum_get_email_message_id($post->parent, $userto->id, $hostname);
                    $userfrom->customheaders[] = 'References: '.hsuforum_get_email_message_id($post->parent, $userto->id, $hostname);
                }

                $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

                $postsubject = html_to_text("$shortname: ".format_string($post->subject, true));
                $posttext = hsuforum_make_mail_text($course, $cm, $forum, $discussion, $post, $userfrom, $userto);
                $posthtml = hsuforum_make_mail_html($course, $cm, $forum, $discussion, $post, $userfrom, $userto);

                // Send the post now!

                mtrace('Sending ', '');

                $postuser = hsuforum_anonymize_user($userfrom, $forum, $post);

                $eventdata = new stdClass();
                $eventdata->component        = 'mod_hsuforum';
                $eventdata->name             = 'posts';
                $eventdata->userfrom         = $postuser;
                $eventdata->userto           = $userto;
                $eventdata->subject          = $postsubject;
                $eventdata->fullmessage      = $posttext;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml  = $posthtml;
                $eventdata->notification = 1;

                // If hsuforum_replytouser is not set then send mail using the noreplyaddress.
                if (empty($CFG->hsuforum_replytouser)) {
                    // Clone userfrom as it is referenced by $users.
                    $cloneduserfrom = clone($userfrom);
                    $cloneduserfrom->email = $CFG->noreplyaddress;
                    $eventdata->userfrom = $cloneduserfrom;
                }

                $smallmessagestrings = new stdClass();
                $smallmessagestrings->user = fullname($postuser);
                $smallmessagestrings->forumname = "$shortname: ".format_string($forum->name,true).": ".$discussion->name;
                $smallmessagestrings->message = $post->message;
                //make sure strings are in message recipients language
                $eventdata->smallmessage = get_string_manager()->get_string('smallmessage', 'hsuforum', $smallmessagestrings, $userto->lang);

                $eventdata->contexturl = "{$CFG->wwwroot}/mod/hsuforum/discuss.php?d={$discussion->id}#p{$post->id}";
                $eventdata->contexturlname = $discussion->name;

                $mailresult = message_send($eventdata);
                if (!$mailresult){
                    mtrace("Error: mod/hsuforum/lib.php hsuforum_cron(): Could not send out mail for id $post->id to user $userto->id".
                         " ($userto->email) .. not trying again.");
                    add_to_log($course->id, 'hsuforum', 'mail error', "discuss.php?d=$discussion->id#p$post->id",
                               substr(format_string($post->subject,true),0,30), $cm->id, $userto->id);
                    $errorcount[$post->id]++;
                } else {
                    $mailcount[$post->id]++;

                // Mark post as read if hsuforum_usermarksread is set off
                    if (!$CFG->hsuforum_usermarksread) {
                        $userto->markposts[$post->id] = $post->id;
                    }
                }

                mtrace('post '.$post->id. ': '.$post->subject);
            }

            // mark processed posts as read
            hsuforum_tp_mark_posts_read($userto, $userto->markposts);
            unset($userto);
        }
    }

    if ($posts) {
        foreach ($posts as $post) {
            mtrace($mailcount[$post->id]." users were sent post $post->id, '$post->subject'");
            if ($errorcount[$post->id]) {
                $DB->set_field('hsuforum_posts', 'mailed', HSUFORUM_MAILED_ERROR, array('id' => $post->id));
            }
        }
    }

    // release some memory
    unset($subscribedusers);
    unset($mailcount);
    unset($errorcount);

    cron_setup_user();

    $sitetimezone = $CFG->timezone;

    // Now see if there are any digest mails waiting to be sent, and if we should send them

    mtrace('Starting digest processing...');

    @set_time_limit(300); // terminate if not able to fetch all digests in 5 minutes

    if (!isset($CFG->hsuforum_digestmailtimelast)) {    // To catch the first time
        set_config('hsuforum_digestmailtimelast', 0);
    }

    $timenow = time();
    $digesttime = usergetmidnight($timenow, $sitetimezone) + ($CFG->hsuforum_digestmailtime * 3600);

    // Delete any really old ones (normally there shouldn't be any)
    $weekago = $timenow - (7 * 24 * 3600);
    $DB->delete_records_select('hsuforum_queue', "timemodified < ?", array($weekago));
    mtrace ('Cleaned old digest records');

    if ($CFG->hsuforum_digestmailtimelast < $digesttime and $timenow > $digesttime) {

        mtrace('Sending forum digests: '.userdate($timenow, '', $sitetimezone));

        $digestposts_rs = $DB->get_recordset_select('hsuforum_queue', "timemodified < ?", array($digesttime));

        if ($digestposts_rs->valid()) {

            // We have work to do
            $usermailcount = 0;

            //caches - reuse the those filled before too
            $discussionposts = array();
            $userdiscussions = array();

            foreach ($digestposts_rs as $digestpost) {
                if (!isset($posts[$digestpost->postid])) {
                    if ($post = $DB->get_record('hsuforum_posts', array('id' => $digestpost->postid))) {
                        $posts[$digestpost->postid] = $post;
                    } else {
                        continue;
                    }
                }
                $discussionid = $digestpost->discussionid;
                if (!isset($discussions[$discussionid])) {
                    if ($discussion = $DB->get_record('hsuforum_discussions', array('id' => $discussionid))) {
                        $discussions[$discussionid] = $discussion;
                    } else {
                        continue;
                    }
                }
                $forumid = $discussions[$discussionid]->forum;
                if (!isset($forums[$forumid])) {
                    if ($forum = $DB->get_record('hsuforum', array('id' => $forumid))) {
                        $forums[$forumid] = $forum;
                    } else {
                        continue;
                    }
                }

                $courseid = $forums[$forumid]->course;
                if (!isset($courses[$courseid])) {
                    if ($course = $DB->get_record('course', array('id' => $courseid))) {
                        $courses[$courseid] = $course;
                    } else {
                        continue;
                    }
                }

                if (!isset($coursemodules[$forumid])) {
                    if ($cm = get_coursemodule_from_instance('hsuforum', $forumid, $courseid)) {
                        $coursemodules[$forumid] = $cm;
                    } else {
                        continue;
                    }
                }
                $userdiscussions[$digestpost->userid][$digestpost->discussionid] = $digestpost->discussionid;
                $discussionposts[$digestpost->discussionid][$digestpost->postid] = $digestpost->postid;
            }
            $digestposts_rs->close(); /// Finished iteration, let's close the resultset

            // Data collected, start sending out emails to each user
            foreach ($userdiscussions as $userid => $thesediscussions) {

                @set_time_limit(120); // terminate if processing of any account takes longer than 2 minutes

                cron_setup_user();

                mtrace(get_string('processingdigest', 'hsuforum', $userid), '... ');

                // First of all delete all the queue entries for this user
                $DB->delete_records_select('hsuforum_queue', "userid = ? AND timemodified < ?", array($userid, $digesttime));

                // Init user caches - we keep the cache for one cycle only,
                // otherwise it would unnecessarily consume memory.
                if (array_key_exists($userid, $users) and isset($users[$userid]->username)) {
                    $userto = clone($users[$userid]);
                } else {
                    $userto = $DB->get_record('user', array('id' => $userid));
                    hsuforum_cron_minimise_user_record($userto);
                }
                $userto->viewfullnames = array();
                $userto->canpost       = array();
                $userto->markposts     = array();

                // Override the language and timezone of the "current" user, so that
                // mail is customised for the receiver.
                cron_setup_user($userto);

                $postsubject = get_string('digestmailsubject', 'hsuforum', format_string($site->shortname, true));

                $headerdata = new stdClass();
                $headerdata->sitename = format_string($site->fullname, true);
                $headerdata->userprefs = $CFG->wwwroot.'/user/edit.php?id='.$userid.'&amp;course='.$site->id;

                $posttext = get_string('digestmailheader', 'hsuforum', $headerdata)."\n\n";
                $headerdata->userprefs = '<a target="_blank" href="'.$headerdata->userprefs.'">'.get_string('digestmailprefs', 'hsuforum').'</a>';

                $posthtml = "<head>";
/*                foreach ($CFG->stylesheets as $stylesheet) {
                    //TODO: MDL-21120
                    $posthtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />'."\n";
                }*/
                $posthtml .= "</head>\n<body id=\"email\">\n";
                $posthtml .= '<p>'.get_string('digestmailheader', 'hsuforum', $headerdata).'</p><br /><hr size="1" noshade="noshade" />';

                foreach ($thesediscussions as $discussionid) {

                    @set_time_limit(120);   // to be reset for each post

                    $discussion = $discussions[$discussionid];
                    $forum      = $forums[$discussion->forum];
                    $course     = $courses[$forum->course];
                    $cm         = $coursemodules[$forum->id];

                    //override language
                    cron_setup_user($userto, $course);

                    // Fill caches
                    if (!isset($userto->viewfullnames[$forum->id])) {
                        $modcontext = context_module::instance($cm->id);
                        $userto->viewfullnames[$forum->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                    }
                    if (!isset($userto->canpost[$discussion->id])) {
                        $modcontext = context_module::instance($cm->id);
                        $userto->canpost[$discussion->id] = hsuforum_user_can_post($forum, $discussion, $userto, $cm, $course, $modcontext);
                    }

                    $strforums      = get_string('forums', 'hsuforum');
                    $canunsubscribe = ! hsuforum_is_forcesubscribed($forum);
                    $canreply       = $userto->canpost[$discussion->id];
                    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

                    $posttext .= "\n \n";
                    $posttext .= '=====================================================================';
                    $posttext .= "\n \n";
                    $posttext .= "$shortname -> $strforums -> ".format_string($forum->name,true);
                    if ($discussion->name != $forum->name) {
                        $posttext  .= " -> ".format_string($discussion->name,true);
                    }
                    $posttext .= "\n";
                    $posttext .= $CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.$discussion->id;
                    $posttext .= "\n";

                    $posthtml .= "<p><font face=\"sans-serif\">".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$shortname</a> -> ".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/hsuforum/index.php?id=$course->id\">$strforums</a> -> ".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id\">".format_string($forum->name,true)."</a>";
                    if ($discussion->name == $forum->name) {
                        $posthtml .= "</font></p>";
                    } else {
                        $posthtml .= " -> <a target=\"_blank\" href=\"$CFG->wwwroot/mod/hsuforum/discuss.php?d=$discussion->id\">".format_string($discussion->name,true)."</a></font></p>";
                    }
                    $posthtml .= '<p>';

                    $postsarray = $discussionposts[$discussionid];
                    sort($postsarray);

                    foreach ($postsarray as $postid) {
                        $post = $posts[$postid];

                        if (array_key_exists($post->userid, $users)) { // we might know him/her already
                            $userfrom = $users[$post->userid];
                            if (!isset($userfrom->idnumber)) {
                                $userfrom = $DB->get_record('user', array('id' => $userfrom->id));
                                hsuforum_cron_minimise_user_record($userfrom);
                            }

                        } else if ($userfrom = $DB->get_record('user', array('id' => $post->userid))) {
                            hsuforum_cron_minimise_user_record($userfrom);
                            if ($userscount <= HSUFORUM_CRON_USER_CACHE) {
                                $userscount++;
                                $users[$userfrom->id] = $userfrom;
                            }

                        } else {
                            mtrace('Could not find user '.$post->userid);
                            continue;
                        }

                        if (!isset($userfrom->groups[$forum->id])) {
                            if (!isset($userfrom->groups)) {
                                $userfrom->groups = array();
                                if (isset($users[$userfrom->id])) {
                                    $users[$userfrom->id]->groups = array();
                                }
                            }
                            $userfrom->groups[$forum->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                            if (isset($users[$userfrom->id])) {
                                $users[$userfrom->id]->groups[$forum->id] = $userfrom->groups[$forum->id];
                            }
                        }

                        $userfrom->customheaders = array ("Precedence: Bulk");

                        $maildigest = hsuforum_get_user_maildigest_bulk($digests, $userto, $forum->id);
                        if ($maildigest == 2) {
                            $postuser = hsuforum_anonymize_user($userfrom, $forum, $post);
                            // Subjects and link only
                            $posttext .= "\n";
                            $posttext .= $CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.$discussion->id;
                            $by = new stdClass();
                            $by->name = fullname($postuser);
                            $by->date = userdate($post->modified);
                            $posttext .= "\n".format_string($post->subject,true).' '.get_string("bynameondate", "hsuforum", $by);
                            $posttext .= "\n---------------------------------------------------------------------";


                            if (!hsuforum_is_anonymous_user($postuser)) {
                                $by->name = "<a target=\"_blank\" href=\"$CFG->wwwroot/user/view.php?id=$postuser->id&amp;course=$course->id\">$by->name</a>";
                            }
                            $posthtml .= '<div><a target="_blank" href="'.$CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.$discussion->id.'#p'.$post->id.'">'.format_string($post->subject,true).'</a> '.get_string("bynameondate", "hsuforum", $by).'</div>';

                        } else {
                            // The full treatment
                            $posttext .= hsuforum_make_mail_text($course, $cm, $forum, $discussion, $post, $userfrom, $userto, true);
                            $posthtml .= hsuforum_make_mail_post($course, $cm, $forum, $discussion, $post, $userfrom, $userto, false, $canreply, true, false);

                        // Create an array of postid's for this user to mark as read.
                            if (!$CFG->hsuforum_usermarksread) {
                                $userto->markposts[$post->id] = $post->id;
                            }
                        }
                    }
                    $footerlinks = array();
                    if ($canunsubscribe) {
                        $footerlinks[] = "<a href=\"$CFG->wwwroot/mod/hsuforum/subscribe.php?id=$forum->id\">" . get_string("unsubscribe", "hsuforum") . "</a>";
                    } else {
                        $footerlinks[] = get_string("everyoneissubscribed", "hsuforum");
                    }
                    $footerlinks[] = "<a href='{$CFG->wwwroot}/mod/hsuforum/index.php?id={$forum->course}'>" . get_string("digestmailpost", "hsuforum") . '</a>';
                    $posthtml .= "\n<div class='mdl-right'><font size=\"1\">" . implode('&nbsp;', $footerlinks) . '</font></div>';
                    $posthtml .= '<hr size="1" noshade="noshade" /></p>';
                }
                $posthtml .= '</body>';

                if (empty($userto->mailformat) || $userto->mailformat != 1) {
                    // This user DOESN'T want to receive HTML
                    $posthtml = '';
                }

                $attachment = $attachname='';
                // Directly email forum digests rather than sending them via messaging, use the
                // site shortname as 'from name', the noreply address will be used by email_to_user.
                $mailresult = email_to_user($userto, $site->shortname, $postsubject, $posttext, $posthtml, $attachment, $attachname);

                if (!$mailresult) {
                    mtrace("ERROR!");
                    echo "Error: mod/hsuforum/cron.php: Could not send out digest mail to user $userto->id ($userto->email)... not trying again.\n";
                    add_to_log($course->id, 'hsuforum', 'mail digest error', '', '', $cm->id, $userto->id);
                } else {
                    mtrace("success.");
                    $usermailcount++;

                    // Mark post as read if hsuforum_usermarksread is set off
                    hsuforum_tp_mark_posts_read($userto, $userto->markposts);
                }
            }
        }
    /// We have finishied all digest emails, update $CFG->hsuforum_digestmailtimelast
        set_config('hsuforum_digestmailtimelast', $timenow);
    }

    cron_setup_user();

    if (!empty($usermailcount)) {
        mtrace(get_string('digestsentusers', 'hsuforum', $usermailcount));
    }

    if (!empty($CFG->hsuforum_lastreadclean)) {
        $timenow = time();
        if ($CFG->hsuforum_lastreadclean + (24*3600) < $timenow) {
            set_config('hsuforum_lastreadclean', $timenow);
            mtrace('Removing old forum read tracking info...');
            hsuforum_tp_clean_read_records();
        }
    } else {
        set_config('hsuforum_lastreadclean', time());
    }


    return true;
}

/**
 * Builds and returns the body of the email notification in plain text.
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param object $course
 * @param object $cm
 * @param object $forum
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @param boolean $bare
 * @return string The email body in plain text format.
 */
function hsuforum_make_mail_text($course, $cm, $forum, $discussion, $post, $userfrom, $userto, $bare = false) {
    global $CFG, $USER;

    $modcontext = context_module::instance($cm->id);

    if (!isset($userto->viewfullnames[$forum->id])) {
        $viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
    } else {
        $viewfullnames = $userto->viewfullnames[$forum->id];
    }

    if (!isset($userto->canpost[$discussion->id])) {
        $canreply = hsuforum_user_can_post($forum, $discussion, $userto, $cm, $course, $modcontext);
    } else {
        $canreply = $userto->canpost[$discussion->id];
    }

    $postuser = hsuforum_anonymize_user($userfrom, $forum, $post);

    $by = New stdClass;
    $by->name = fullname($postuser, $viewfullnames);
    $by->date = userdate($post->modified, "", $userto->timezone);

    $strbynameondate = get_string('bynameondate', 'hsuforum', $by);

    $strforums = get_string('forums', 'hsuforum');

    $canunsubscribe = ! hsuforum_is_forcesubscribed($forum);

    $posttext = '';

    if (!$bare) {
        $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
        $posttext  = "$shortname -> $strforums -> ".format_string($forum->name,true);

        if ($discussion->name != $forum->name) {
            $posttext  .= " -> ".format_string($discussion->name,true);
        }
    }

    // add absolute file links
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_hsuforum', 'post', $post->id);

    $posttext .= "\n";
    $posttext .= $CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.$discussion->id;
    $posttext .= "\n---------------------------------------------------------------------\n";
    $posttext .= format_string($post->subject,true);
    if ($bare) {
        $posttext .= " ($CFG->wwwroot/mod/hsuforum/discuss.php?d=$discussion->id#p$post->id)";
    }
    $posttext .= "\n".$strbynameondate."\n";
    $posttext .= "---------------------------------------------------------------------\n";
    $posttext .= format_text_email($post->message, $post->messageformat);
    $posttext .= "\n\n";
    $posttext .= hsuforum_print_attachments($post, $cm, "text");

    if (!$bare && $canreply) {
        $posttext .= "---------------------------------------------------------------------\n";
        $posttext .= get_string("postmailinfo", "hsuforum", $shortname)."\n";
        $posttext .= "$CFG->wwwroot/mod/hsuforum/post.php?reply=$post->id\n";
    }
    if (!$bare && $canunsubscribe) {
        $posttext .= "\n---------------------------------------------------------------------\n";
        $posttext .= get_string("unsubscribe", "hsuforum");
        $posttext .= ": $CFG->wwwroot/mod/hsuforum/subscribe.php?id=$forum->id\n";
    }

    $posttext .= "\n---------------------------------------------------------------------\n";
    $posttext .= get_string("digestmailpost", "hsuforum");
    $posttext .= ": {$CFG->wwwroot}/mod/hsuforum/index.php?id={$forum->course}\n";

    return $posttext;
}

/**
 * Builds and returns the body of the email notification in html format.
 *
 * @global object
 * @param object $course
 * @param object $cm
 * @param object $forum
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @return string The email text in HTML format
 */
function hsuforum_make_mail_html($course, $cm, $forum, $discussion, $post, $userfrom, $userto) {
    global $CFG;

    if ($userto->mailformat != 1) {  // Needs to be HTML
        return '';
    }

    if (!isset($userto->canpost[$discussion->id])) {
        $canreply = hsuforum_user_can_post($forum, $discussion, $userto, $cm, $course);
    } else {
        $canreply = $userto->canpost[$discussion->id];
    }

    $strforums = get_string('forums', 'hsuforum');
    $canunsubscribe = ! hsuforum_is_forcesubscribed($forum);
    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

    $posthtml = '<head>';
/*    foreach ($CFG->stylesheets as $stylesheet) {
        //TODO: MDL-21120
        $posthtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />'."\n";
    }*/
    $posthtml .= '</head>';
    $posthtml .= "\n<body id=\"email\">\n\n";

    $posthtml .= '<div class="navbar">'.
    '<a target="_blank" href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.$shortname.'</a> &raquo; '.
    '<a target="_blank" href="'.$CFG->wwwroot.'/mod/hsuforum/index.php?id='.$course->id.'">'.$strforums.'</a> &raquo; '.
    '<a target="_blank" href="'.$CFG->wwwroot.'/mod/hsuforum/view.php?f='.$forum->id.'">'.format_string($forum->name,true).'</a>';
    if ($discussion->name == $forum->name) {
        $posthtml .= '</div>';
    } else {
        $posthtml .= ' &raquo; <a target="_blank" href="'.$CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.$discussion->id.'">'.
                     format_string($discussion->name,true).'</a></div>';
    }
    $posthtml .= hsuforum_make_mail_post($course, $cm, $forum, $discussion, $post, $userfrom, $userto, false, $canreply, true, false);

    $footerlinks = array();
    if ($canunsubscribe) {
        $footerlinks[] = '<a href="' . $CFG->wwwroot . '/mod/hsuforum/subscribe.php?id=' . $forum->id . '">' . get_string('unsubscribe', 'hsuforum') . '</a>';
        $footerlinks[] = '<a href="' . $CFG->wwwroot . '/mod/hsuforum/unsubscribeall.php">' . get_string('unsubscribeall', 'hsuforum') . '</a>';
    }
    $footerlinks[] = "<a href='{$CFG->wwwroot}/mod/hsuforum/index.php?id={$forum->course}'>" . get_string('digestmailpost', 'hsuforum') . '</a>';
    $posthtml .= '<hr /><div class="mdl-align unsubscribelink">' . implode('&nbsp;', $footerlinks) . '</div>';

    $posthtml .= '</body>';

    return $posthtml;
}


/**
 *
 * @param object $course
 * @param object $user
 * @param object $mod TODO this is not used in this function, refactor
 * @param object $forum
 * @return object A standard object with 2 variables: info (number of posts for this user) and time (last modified)
 */
function hsuforum_user_outline($course, $user, $mod, $forum) {
    global $CFG;
    require_once("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course->id, 'mod', 'hsuforum', $forum->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
    }

    $count = hsuforum_count_user_posts($forum->id, $user->id);

    if ($count && $count->postcount > 0) {
        $result = new stdClass();
        $result->info = get_string("numposts", "hsuforum", $count->postcount);
        $result->time = $count->lastpost;
        if ($grade) {
            $result->info .= ', ' . get_string('grade') . ': ' . $grade->str_long_grade;
        }
        return $result;
    } else if ($grade) {
        $result = new stdClass();
        $result->info = get_string('grade') . ': ' . $grade->str_long_grade;

        //datesubmitted == time created. dategraded == time modified or time overridden
        //if grade was last modified by the user themselves use date graded. Otherwise use date submitted
        //TODO: move this copied & pasted code somewhere in the grades API. See MDL-26704
        if ($grade->usermodified == $user->id || empty($grade->datesubmitted)) {
            $result->time = $grade->dategraded;
        } else {
            $result->time = $grade->datesubmitted;
        }

        return $result;
    }
    return NULL;
}


/**
 * @global object
 * @global object
 * @param object $coure
 * @param object $user
 * @param object $mod
 * @param object $forum
 */
function hsuforum_user_complete($course, $user, $mod, $forum) {
    global $CFG,$USER, $OUTPUT;
    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'hsuforum', $forum->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
        if ($grade->str_feedback) {
            echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
        }
    }

    if ($posts = hsuforum_get_user_posts($forum->id, $user->id)) {

        if (!$cm = get_coursemodule_from_instance('hsuforum', $forum->id, $course->id)) {
            print_error('invalidcoursemodule');
        }
        $discussions = hsuforum_get_user_involved_discussions($forum->id, $user->id);

        foreach ($posts as $post) {
            if (!isset($discussions[$post->discussion])) {
                continue;
            }
            $discussion = $discussions[$post->discussion];

            hsuforum_print_post($post, $discussion, $forum, $cm, $course, false, false, false);
        }
    } else {
        echo "<p>".get_string("noposts", "hsuforum")."</p>";
    }
}






/**
 * @global object
 * @global object
 * @global object
 * @param array $courses
 * @param array $htmlarray
 */
function hsuforum_print_overview($courses,&$htmlarray) {
    global $USER, $CFG, $DB, $SESSION;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$forums = get_all_instances_in_courses('hsuforum',$courses)) {
        return;
    }

    // Courses to search for new posts
    $coursessqls = array();
    $params = array();
    foreach ($courses as $course) {

        // If the user has never entered into the course all posts are pending
        if ($course->lastaccess == 0) {
            $coursessqls[] = '(f.course = ?)';
            $params[] = $course->id;

        // Only posts created after the course last access
        } else {
            $coursessqls[] = '(f.course = ? AND p.created > ?)';
            $params[] = $course->id;
            $params[] = $course->lastaccess;
        }
    }
    $params[] = $USER->id;
    $coursessql = implode(' OR ', $coursessqls);

    $sql = "SELECT f.id, COUNT(*) as count "
                .'FROM {hsuforum} f '
                .'JOIN {hsuforum_discussions} d ON d.forum  = f.id '
                .'JOIN {hsuforum_posts} p ON p.discussion = d.id '
                ."WHERE ($coursessql) "
                .'AND p.userid != ? '
                .'GROUP BY f.id';

    if (!$new = $DB->get_records_sql($sql, $params)) {
        $new = array(); // avoid warnings
    }

    // also get all forum tracking stuff ONCE.
    $trackingforums = array();
    foreach ($forums as $forum) {
        if (hsuforum_tp_can_track_forums($forum)) {
            $trackingforums[$forum->id] = $forum;
        }
    }

    if (count($trackingforums) > 0) {
        $cutoffdate = isset($CFG->hsuforum_oldpostdays) ? (time() - ($CFG->hsuforum_oldpostdays*24*60*60)) : 0;
        $sql = 'SELECT d.forum,d.course,COUNT(p.id) AS count '.
            ' FROM {hsuforum_posts} p '.
            ' JOIN {hsuforum_discussions} d ON p.discussion = d.id '.
            ' LEFT JOIN {hsuforum_read} r ON r.postid = p.id AND r.userid = ? WHERE (';
        $params = array($USER->id);

        foreach ($trackingforums as $track) {
            $sql .= '(d.forum = ? AND (d.groupid = -1 OR d.groupid = 0 OR d.groupid = ?)) OR ';
            $params[] = $track->id;
            if (isset($SESSION->currentgroup[$track->course])) {
                $groupid =  $SESSION->currentgroup[$track->course];
            } else {
                // get first groupid
                $groupids = groups_get_all_groups($track->course, $USER->id);
                if ($groupids) {
                    reset($groupids);
                    $groupid = key($groupids);
                    $SESSION->currentgroup[$track->course] = $groupid;
                } else {
                    $groupid = 0;
                }
                unset($groupids);
            }
            $params[] = $groupid;
        }
        $sql = substr($sql,0,-3); // take off the last OR
        $sql .= ') AND p.modified >= ? AND r.id is NULL GROUP BY d.forum,d.course';
        $params[] = $cutoffdate;

        if (!$unread = $DB->get_records_sql($sql, $params)) {
            $unread = array();
        }
    } else {
        $unread = array();
    }

    if (empty($unread) and empty($new)) {
        return;
    }

    $strforum = get_string('modulename','hsuforum');

    foreach ($forums as $forum) {
        $str = '';
        $count = 0;
        $thisunread = 0;
        $showunread = false;
        // either we have something from logs, or trackposts, or nothing.
        if (array_key_exists($forum->id, $new) && !empty($new[$forum->id])) {
            $count = $new[$forum->id]->count;
        }
        if (array_key_exists($forum->id,$unread)) {
            $thisunread = $unread[$forum->id]->count;
            $showunread = true;
        }
        if ($count > 0 || $thisunread > 0) {
            $str .= '<div class="overview forum"><div class="name">'.$strforum.': <a title="'.$strforum.'" href="'.$CFG->wwwroot.'/mod/hsuforum/view.php?f='.$forum->id.'">'.
                $forum->name.'</a></div>';
            $str .= '<div class="info"><span class="postsincelogin">';
            $str .= get_string('overviewnumpostssince', 'hsuforum', $count)."</span>";
            if (!empty($showunread)) {
                $str .= '<div class="unreadposts">'.get_string('overviewnumunread', 'hsuforum', $thisunread).'</div>';
            }
            $str .= '</div></div>';
        }
        if (!empty($str)) {
            if (!array_key_exists($forum->course,$htmlarray)) {
                $htmlarray[$forum->course] = array();
            }
            if (!array_key_exists('hsuforum',$htmlarray[$forum->course])) {
                $htmlarray[$forum->course]['hsuforum'] = ''; // initialize, avoid warnings
            }
            $htmlarray[$forum->course]['hsuforum'] .= $str;
        }
    }
}

/**
 * Given a course and a date, prints a summary of all the new
 * messages posted in the course since that date
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $course
 * @param bool $viewfullnames capability
 * @param int $timestart
 * @return bool success
 */
function hsuforum_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    // do not use log table if possible, it may be huge and is expensive to join with other tables

    $allnamefields = user_picture::fields('u', null, 'duserid');
    if (!$posts = $DB->get_records_sql("SELECT p.*, f.anonymous as forumanonymous, f.type AS forumtype, d.forum, d.groupid,
                                              d.timestart, d.timeend, $allnamefields
                                         FROM {hsuforum_posts} p
                                              JOIN {hsuforum_discussions} d ON d.id = p.discussion
                                              JOIN {hsuforum} f             ON f.id = d.forum
                                              JOIN {user} u              ON u.id = p.userid
                                        WHERE p.created > ? AND f.course = ? AND (p.privatereply = 0 OR p.privatereply = ? OR p.userid = ?)
                                     ORDER BY p.id ASC", array($timestart, $course->id, $USER->id, $USER->id))) { // order by initial posting date
         return false;
    }

    $modinfo = get_fast_modinfo($course);

    $groupmodes = array();
    $cms    = array();

    $strftimerecent = get_string('strftimerecent');

    $printposts = array();
    foreach ($posts as $post) {
        if (!isset($modinfo->instances['hsuforum'][$post->forum])) {
            // not visible
            continue;
        }
        $cm = $modinfo->instances['hsuforum'][$post->forum];
        if (!$cm->uservisible) {
            continue;
        }
        $context = context_module::instance($cm->id);

        if (!has_capability('mod/hsuforum:viewdiscussion', $context)) {
            continue;
        }

        if (!empty($CFG->hsuforum_enabletimedposts) and $USER->id != $post->duserid
          and (($post->timestart > 0 and $post->timestart > time()) or ($post->timeend > 0 and $post->timeend < time()))) {
            if (!has_capability('mod/hsuforum:viewhiddentimedposts', $context)) {
                continue;
            }
        }

        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($groupmode) {
            if ($post->groupid == -1 or $groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $context)) {
                // oki (Open discussions have groupid -1)
            } else {
                // separate mode
                if (isguestuser()) {
                    // shortcut
                    continue;
                }

                if (!in_array($post->groupid, $modinfo->get_groups($cm->groupingid))) {
                    continue;
                }
            }
        }

        $printposts[] = $post;
    }
    unset($posts);

    if (!$printposts) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newforumposts', 'hsuforum').':', 3);
    echo "\n<ul class='unlist'>\n";

    foreach ($printposts as $post) {
        $subjectclass = empty($post->parent) ? ' bold' : '';

        $postuser = new stdClass();
        $postuser->id = $post->userid;
        $postuser->firstname = $post->firstname;
        $postuser->lastname = $post->lastname;

        $postuser = hsuforum_anonymize_user($postuser, (object) array(
            'id' => $post->forum,
            'course' => $course->id,
            'anonymous' => $post->forumanonymous
        ), $post);

        echo '<li><div class="head">'.
               '<div class="date">'.userdate($post->modified, $strftimerecent).'</div>'.
               '<div class="name">'.fullname($postuser, $viewfullnames).'</div>'.
             '</div>';
        echo '<div class="info'.$subjectclass.'">';
        if (empty($post->parent)) {
            echo '"<a href="'.$CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.$post->discussion.'">';
        } else {
            echo '"<a href="'.$CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.$post->discussion.'&amp;parent='.$post->parent.'#p'.$post->id.'">';
        }
        $post->subject = break_up_long_words(format_string($post->subject, true));
        echo $post->subject;
        echo "</a>\"</div></li>\n";
    }

    echo "</ul>\n";

    return true;
}

/**
 * @param $forum
 * @param $userid
 * @return bool|string
 * @author Mark Nielsen
 */
function hsuforum_get_user_formatted_rating_grade($forum, $userid) {
    $grades = hsuforum_get_user_rating_grades($forum, $userid);
    if (!empty($grades) and array_key_exists($userid, $grades)) {
        $gradeitem = grade_item::fetch(array(
            'courseid'     => $forum->course,
            'itemtype'     => 'mod',
            'itemmodule'   => 'hsuforum',
            'iteminstance' => $forum->id,
            'itemnumber'   => 0,
        ));
        return grade_format_gradevalue($grades[$userid]->rawgrade, $gradeitem);
    }
    return false;
}

/**
 * Return rating grades for given user or all users.
 *
 * @global object
 * @global object
 * @param object $forum
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 * @author Mark Nielsen
 */
function hsuforum_get_user_rating_grades($forum, $userid = 0) {
    global $CFG;

    if (!$forum->assessed) {
        return false;
    }
    require_once($CFG->dirroot.'/rating/lib.php');

    $ratingoptions = new stdClass;
    $ratingoptions->component = 'mod_hsuforum';
    $ratingoptions->ratingarea = 'post';

    //need these to work backwards to get a context id. Is there a better way to get contextid from a module instance?
    $ratingoptions->modulename = 'hsuforum';
    $ratingoptions->moduleid   = $forum->id;
    $ratingoptions->userid = $userid;
    $ratingoptions->aggregationmethod = $forum->assessed;
    $ratingoptions->scaleid = $forum->scale;
    $ratingoptions->itemtable = 'hsuforum_posts';
    $ratingoptions->itemtableusercolumn = 'userid';

    $rm = new rating_manager();
    return $rm->get_user_grades($ratingoptions);
}

/**
 * Return grade for given user or all users.
 *
 * @global object
 * @global object
 * @param object $forum
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function hsuforum_get_user_grades($forum, $userid = 0) {
    if ($forum->gradetype != HSUFORUM_GRADETYPE_RATING) {
        return false;
    }
    return hsuforum_get_user_rating_grades($forum, $userid);
}

/**
 * Update activity grades
 *
 * @category grade
 * @param object $forum
 * @param int $userid specific user only, 0 means all
 * @param boolean $nullifnone return null if grade does not exist
 * @return void
 */
function hsuforum_update_grades($forum, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if ($forum->gradetype == HSUFORUM_GRADETYPE_NONE or ($forum->gradetype == HSUFORUM_GRADETYPE_RATING and !$forum->assessed)) {
        hsuforum_grade_item_update($forum);

    } else if ($grades = hsuforum_get_user_grades($forum, $userid)) {
        hsuforum_grade_item_update($forum, $grades);

    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = NULL;
        hsuforum_grade_item_update($forum, $grade);

    } else {
        hsuforum_grade_item_update($forum);
    }
}

/**
 * Update all grades in gradebook.
 * @global object
 */
function hsuforum_upgrade_grades() {
    global $DB;

    $sql = "SELECT COUNT('x')
              FROM {hsuforum} f, {course_modules} cm, {modules} m
             WHERE m.name='hsuforum' AND m.id=cm.module AND cm.instance=f.id";
    $count = $DB->count_records_sql($sql);

    $sql = "SELECT f.*, cm.idnumber AS cmidnumber, f.course AS courseid
              FROM {hsuforum} f, {course_modules} cm, {modules} m
             WHERE m.name='hsuforum' AND m.id=cm.module AND cm.instance=f.id";
    $rs = $DB->get_recordset_sql($sql);
    if ($rs->valid()) {
        $pbar = new progress_bar('forumupgradegrades', 500, true);
        $i=0;
        foreach ($rs as $forum) {
            $i++;
            upgrade_set_timeout(60*5); // set up timeout, may also abort execution
            hsuforum_update_grades($forum, 0, false);
            $pbar->update($i, $count, "Updating Forum grades ($i/$count).");
        }
    }
    $rs->close();
}

/**
 * Create/update grade item for given forum
 *
 * @category grade
 * @uses GRADE_TYPE_NONE
 * @uses GRADE_TYPE_VALUE
 * @uses GRADE_TYPE_SCALE
 * @param stdClass $forum Forum object with extra cmidnumber
 * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok
 */
function hsuforum_grade_item_update($forum, $grades=NULL) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    $params = array('itemname'=>$forum->name, 'idnumber'=>$forum->cmidnumber);

    if ($forum->gradetype == HSUFORUM_GRADETYPE_NONE or ($forum->gradetype == HSUFORUM_GRADETYPE_RATING and !$forum->assessed) or $forum->scale == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;

    } else if ($forum->scale > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $forum->scale;
        $params['grademin']  = 0;

    } else if ($forum->scale < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$forum->scale;
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/hsuforum', $forum->course, 'mod', 'hsuforum', $forum->id, 0, $grades, $params);
}

/**
 * Delete grade item for given forum
 *
 * @category grade
 * @param stdClass $forum Forum object
 * @return grade_item
 */
function hsuforum_grade_item_delete($forum) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/hsuforum', $forum->course, 'mod', 'hsuforum', $forum->id, 0, NULL, array('deleted'=>1));
}

/**
 * This function returns if a scale is being used by one forum
 *
 * @global object
 * @param int $forumid
 * @param int $scaleid negative number
 * @return bool
 */
function hsuforum_scale_used ($forumid,$scaleid) {
    global $DB;
    $return = false;

    $rec = $DB->get_record("hsuforum",array("id" => "$forumid","scale" => "-$scaleid"));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of forum
 *
 * This is used to find out if scale used anywhere
 *
 * @global object
 * @param $scaleid int
 * @return boolean True if the scale is used by any forum
 */
function hsuforum_scale_used_anywhere($scaleid) {
    global $DB;
    if ($scaleid and $DB->record_exists('hsuforum', array('scale' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

// SQL FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Gets a post with all info ready for hsuforum_print_post
 * Most of these joins are just to get the forum id
 *
 * @global object
 * @global object
 * @param int $postid
 * @return mixed array of posts or false
 */
function hsuforum_get_post_full($postid) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_record_sql("SELECT p.*, d.forum, $allnames, u.email, u.picture, u.imagealt
                             FROM {hsuforum_posts} p
                                  JOIN {hsuforum_discussions} d ON p.discussion = d.id
                                  LEFT JOIN {user} u ON p.userid = u.id
                            WHERE p.id = ?", array($postid));
}

/**
 * Gets posts with all info ready for hsuforum_print_post
 * We pass forumid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @return mixed array of posts or false
 */
function hsuforum_get_discussion_posts($discussion, $sort, $forumid) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $forumid AS forum, $allnames, u.email, u.picture, u.imagealt
                              FROM {hsuforum_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.discussion = ?
                               AND p.parent > 0 $sort", array($discussion));
}

/**
 * Gets all posts in discussion including top parent.
 *
 * @global object
 * @global object
 * @global object
 * @param int $discussionid
 * @param string $sort
 * @param bool $tracking does user track the forum?
 * @return array of posts
 */
function hsuforum_get_all_discussion_posts($discussionid, $sort, $tracking=false, $conditions = array()) {
    global $CFG, $DB, $USER;

    $tr_sel  = "";
    $tr_join = "";
    $params = array();

    if ($tracking) {
        $now = time();
        $cutoffdate = $now - ($CFG->hsuforum_oldpostdays * 24 * 3600);
        $tr_sel  = ", fr.id AS postread";
        $tr_join = "LEFT JOIN {hsuforum_read} fr ON (fr.postid = p.id AND fr.userid = ?)";
        $params[] = $USER->id;
    }

    $allnames = get_all_user_name_fields(true, 'u');
    $params[] = $discussionid;
    $params[] = $USER->id;
    $params[] = $USER->id;

    $conditionsql = '';
    foreach ($conditions as $field => $value) {
        $conditionsql .= " AND $field = ?";
        $params[] = $value;
    }
    if (!$posts = $DB->get_records_sql("SELECT p.*, $allnames, u.email, u.picture, u.imagealt $tr_sel
                                     FROM {hsuforum_posts} p
                                          LEFT JOIN {user} u ON p.userid = u.id
                                          $tr_join
                                    WHERE p.discussion = ?
                                      AND (p.privatereply = 0 OR p.privatereply = ? OR p.userid = ?)
                                      $conditionsql
                                 ORDER BY $sort", $params)) {
        return array();
    }

    foreach ($posts as $pid=>$p) {
        if ($tracking) {
            if (hsuforum_tp_is_post_old($p)) {
                 $posts[$pid]->postread = true;
            }
        }
        if (!$p->parent) {
            continue;
        }
        if (!isset($posts[$p->parent])) {
            continue; // parent does not exist??
        }
        if (!isset($posts[$p->parent]->children)) {
            $posts[$p->parent]->children = array();
        }
        $posts[$p->parent]->children[$pid] =& $posts[$pid];
    }

    return $posts;
}

/**
 * Gets posts with all info ready for hsuforum_print_post
 * We pass forumid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @param int $parent
 * @param int $forumid
 * @return array
 */
function hsuforum_get_child_posts($parent, $forumid) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $forumid AS forum, $allnames, u.email, u.picture, u.imagealt
                              FROM {hsuforum_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.parent = ?
                          ORDER BY p.created ASC", array($parent));
}

/**
 * An array of forum objects that the user is allowed to read/search through.
 *
 * @global object
 * @global object
 * @global object
 * @param int $userid
 * @param int $courseid if 0, we look for forums throughout the whole site.
 * @return array of forum objects, or false if no matches
 *         Forum objects have the following attributes:
 *         id, type, course, cmid, cmvisible, cmgroupmode, accessallgroups,
 *         viewhiddentimedposts
 */
function hsuforum_get_readable_forums($userid, $courseid=0) {

    global $CFG, $DB, $USER;
    require_once($CFG->dirroot.'/course/lib.php');

    if (!$forummod = $DB->get_record('modules', array('name' => 'hsuforum'))) {
        print_error('notinstalled', 'hsuforum');
    }

    if ($courseid) {
        $courses = $DB->get_records('course', array('id' => $courseid));
    } else {
        // If no course is specified, then the user can see SITE + his courses.
        $courses1 = $DB->get_records('course', array('id' => SITEID));
        $courses2 = enrol_get_users_courses($userid, true, array('modinfo'));
        $courses = array_merge($courses1, $courses2);
    }
    if (!$courses) {
        return array();
    }

    $readableforums = array();

    foreach ($courses as $course) {

        $modinfo = get_fast_modinfo($course);

        if (empty($modinfo->instances['hsuforum'])) {
            // hmm, no forums?
            continue;
        }

        $courseforums = $DB->get_records('hsuforum', array('course' => $course->id));

        foreach ($modinfo->instances['hsuforum'] as $forumid => $cm) {
            if (!$cm->uservisible or !isset($courseforums[$forumid])) {
                continue;
            }
            $context = context_module::instance($cm->id);
            $forum = $courseforums[$forumid];
            $forum->context = $context;
            $forum->cm = $cm;

            if (!has_capability('mod/hsuforum:viewdiscussion', $context)) {
                continue;
            }

         /// group access
            if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {

                $forum->onlygroups = $modinfo->get_groups($cm->groupingid);
                $forum->onlygroups[] = -1;
            }

        /// hidden timed discussions
            $forum->viewhiddentimedposts = true;
            if (!empty($CFG->hsuforum_enabletimedposts)) {
                if (!has_capability('mod/hsuforum:viewhiddentimedposts', $context)) {
                    $forum->viewhiddentimedposts = false;
                }
            }

        /// qanda access
            if ($forum->type == 'qanda'
                    && !has_capability('mod/hsuforum:viewqandawithoutposting', $context)) {

                // We need to check whether the user has posted in the qanda forum.
                $forum->onlydiscussions = array();  // Holds discussion ids for the discussions
                                                    // the user is allowed to see in this forum.
                if ($discussionspostedin = hsuforum_discussions_user_has_posted_in($forum->id, $USER->id)) {
                    foreach ($discussionspostedin as $d) {
                        $forum->onlydiscussions[] = $d->id;
                    }
                }
            }

            $readableforums[$forum->id] = $forum;
        }

        unset($modinfo);

    } // End foreach $courses

    return $readableforums;
}

/**
 * Returns a list of posts found using an array of search terms.
 *
 * @global object
 * @global object
 * @global object
 * @param array $searchterms array of search terms, e.g. word +word -word
 * @param int $courseid if 0, we search through the whole site
 * @param int $limitfrom
 * @param int $limitnum
 * @param int &$totalcount
 * @param string $extrasql
 * @return array|bool Array of posts found or false
 */
function hsuforum_search_posts($searchterms, $courseid=0, $limitfrom=0, $limitnum=50,
                            &$totalcount, $extrasql='') {
    global $CFG, $DB, $USER;
    require_once($CFG->libdir.'/searchlib.php');

    $forums = hsuforum_get_readable_forums($USER->id, $courseid);

    if (count($forums) == 0) {
        $totalcount = 0;
        return false;
    }

    $now = round(time(), -2); // db friendly

    $fullaccess = array();
    $where = array();
    $params = array('privatereply1' => $USER->id, 'privatereply2' => $USER->id);

    foreach ($forums as $forumid => $forum) {
        $select = array();

        if (!$forum->viewhiddentimedposts) {
            $select[] = "(d.userid = :userid{$forumid} OR (d.timestart < :timestart{$forumid} AND (d.timeend = 0 OR d.timeend > :timeend{$forumid})))";
            $params = array_merge($params, array('userid'.$forumid=>$USER->id, 'timestart'.$forumid=>$now, 'timeend'.$forumid=>$now));
        }

        $cm = $forum->cm;
        $context = $forum->context;

        if ($forum->type == 'qanda'
            && !has_capability('mod/hsuforum:viewqandawithoutposting', $context)) {
            if (!empty($forum->onlydiscussions)) {
                list($discussionid_sql, $discussionid_params) = $DB->get_in_or_equal($forum->onlydiscussions, SQL_PARAMS_NAMED, 'qanda'.$forumid.'_');
                $params = array_merge($params, $discussionid_params);
                $select[] = "(d.id $discussionid_sql OR p.parent = 0)";
            } else {
                $select[] = "p.parent = 0";
            }
        }

        if (!empty($forum->onlygroups)) {
            list($groupid_sql, $groupid_params) = $DB->get_in_or_equal($forum->onlygroups, SQL_PARAMS_NAMED, 'grps'.$forumid.'_');
            $params = array_merge($params, $groupid_params);
            $select[] = "d.groupid $groupid_sql";
        }

        if ($select) {
            $selects = implode(" AND ", $select);
            $where[] = "(d.forum = :forum{$forumid} AND $selects)";
            $params['forum'.$forumid] = $forumid;
        } else {
            $fullaccess[] = $forumid;
        }
    }

    if ($fullaccess) {
        list($fullid_sql, $fullid_params) = $DB->get_in_or_equal($fullaccess, SQL_PARAMS_NAMED, 'fula');
        $params = array_merge($params, $fullid_params);
        $where[] = "(d.forum $fullid_sql)";
    }

    $selectdiscussion = "(".implode(" OR ", $where).")";

    $messagesearch = '';
    $searchstring = '';

    // Need to concat these back together for parser to work.
    foreach($searchterms as $searchterm){
        if ($searchstring != '') {
            $searchstring .= ' ';
        }
        $searchstring .= $searchterm;
    }

    // We need to allow quoted strings for the search. The quotes *should* be stripped
    // by the parser, but this should be examined carefully for security implications.
    $searchstring = str_replace("\\\"","\"",$searchstring);
    $parser = new search_parser();
    $lexer = new search_lexer($parser);

    if ($lexer->parse($searchstring)) {
        $parsearray = $parser->get_parsed_array();
    // Experimental feature under 1.8! MDL-8830
    // Use alternative text searches if defined
    // This feature only works under mysql until properly implemented for other DBs
    // Requires manual creation of text index for hsuforum_posts before enabling it:
    // CREATE FULLTEXT INDEX foru_post_tix ON [prefix]hsuforum_posts (subject, message)
    // Experimental feature under 1.8! MDL-8830
        if (!empty($CFG->hsuforum_usetextsearches)) {
            list($messagesearch, $msparams) = search_generate_text_SQL($parsearray, 'p.message', 'p.subject',
                                                 'p.userid', 'u.id', 'u.firstname',
                                                 'u.lastname', 'p.modified', 'd.forum');
        } else {
            list($messagesearch, $msparams) = search_generate_SQL($parsearray, 'p.message', 'p.subject',
                                                 'p.userid', 'u.id', 'u.firstname',
                                                 'u.lastname', 'p.modified', 'd.forum');
        }
        $params = array_merge($params, $msparams);
    }

    $fromsql = "{hsuforum_posts} p,
                  {hsuforum_discussions} d,
                  {user} u";

    $selectsql = "(p.privatereply = 0 OR p.privatereply = :privatereply1 OR p.userid = :privatereply2)
               AND $messagesearch
               AND p.discussion = d.id
               AND p.userid = u.id
               AND $selectdiscussion
                   $extrasql";

    $countsql = "SELECT COUNT(*)
                   FROM $fromsql
                  WHERE $selectsql";

    $allnames = get_all_user_name_fields(true, 'u');
    $searchsql = "SELECT p.*,
                         d.forum,
                         $allnames,
                         u.email,
                         u.picture,
                         u.imagealt,
                         u.email
                    FROM $fromsql
                   WHERE $selectsql
                ORDER BY p.modified DESC";

    $totalcount = $DB->count_records_sql($countsql, $params);

    return $DB->get_records_sql($searchsql, $params, $limitfrom, $limitnum);
}

/**
 * Returns a list of ratings for a particular post - sorted.
 *
 * TODO: Check if this function is actually used anywhere.
 * Up until the fix for MDL-27471 this function wasn't even returning.
 *
 * @param stdClass $context
 * @param int $postid
 * @param string $sort
 * @return array Array of ratings or false
 */
function hsuforum_get_ratings($context, $postid, $sort = "u.firstname ASC") {
    $options = new stdClass;
    $options->context = $context;
    $options->component = 'mod_hsuforum';
    $options->ratingarea = 'post';
    $options->itemid = $postid;
    $options->sort = "ORDER BY $sort";

    $rm = new rating_manager();
    return $rm->get_all_ratings_for_item($options);
}

/**
 * Load ratings for a bunch of posts.
 *
 * @param context_module $context
 * @param object $forum
 * @param array $posts Ratings will be assigned to these items
 * @param null|string $returnurl
 * @param null|int $userid
 */
function hsuforum_get_ratings_for_posts(context_module $context, $forum, array $posts, $returnurl = null, $userid = null) {
    global $CFG, $USER;

    require_once($CFG->dirroot.'/rating/lib.php');

    if ($forum->assessed == RATING_AGGREGATE_NONE) {
        return;
    }
    if (empty($userid)) {
        $userid = $USER->id;
    }
    if (empty($returnurl)) {
        $returnurl = "$CFG->wwwroot/mod/hsuforum/view.php?id={$context->instanceid}";
    }
    $ratingoptions                   = new stdClass;
    $ratingoptions->context          = $context;
    $ratingoptions->component        = 'mod_hsuforum';
    $ratingoptions->ratingarea       = 'post';
    $ratingoptions->items            = $posts;
    $ratingoptions->aggregate        = $forum->assessed;
    $ratingoptions->scaleid          = $forum->scale;
    $ratingoptions->userid           = $userid;
    $ratingoptions->returnurl        = $returnurl;
    $ratingoptions->assesstimestart  = $forum->assesstimestart;
    $ratingoptions->assesstimefinish = $forum->assesstimefinish;

    $rm = new rating_manager();
    $rm->get_ratings($ratingoptions);
}

/**
 * Returns a list of all new posts that have not been mailed yet
 *
 * @param int $starttime posts created after this time
 * @param int $endtime posts created before this
 * @param int $now used for timed discussions only
 * @return array
 */
function hsuforum_get_unmailed_posts($starttime, $endtime, $now=null) {
    global $CFG, $DB;

    $params = array();
    $params['mailed'] = HSUFORUM_MAILED_PENDING;
    $params['ptimestart'] = $starttime;
    $params['ptimeend'] = $endtime;
    $params['mailnow'] = 1;

    if (!empty($CFG->hsuforum_enabletimedposts)) {
        if (empty($now)) {
            $now = time();
        }
        $timedsql = "AND (d.timestart < :dtimestart AND (d.timeend = 0 OR d.timeend > :dtimeend))";
        $params['dtimestart'] = $now;
        $params['dtimeend'] = $now;
    } else {
        $timedsql = "";
    }

    return $DB->get_records_sql("SELECT p.*, d.course, d.forum
                                 FROM {hsuforum_posts} p
                                 JOIN {hsuforum_discussions} d ON d.id = p.discussion
                                 WHERE p.mailed = :mailed
                                 AND p.created >= :ptimestart
                                 AND (p.created < :ptimeend OR p.mailnow = :mailnow)
                                 $timedsql
                                 ORDER BY p.modified ASC", $params);
}

/**
 * Marks posts before a certain time as being mailed already
 *
 * @global object
 * @global object
 * @param int $endtime
 * @param int $now Defaults to time()
 * @return bool
 */
function hsuforum_mark_old_posts_as_mailed($endtime, $now=null) {
    global $CFG, $DB;

    if (empty($now)) {
        $now = time();
    }

    $params = array();
    $params['mailedsuccess'] = HSUFORUM_MAILED_SUCCESS;
    $params['now'] = $now;
    $params['endtime'] = $endtime;
    $params['mailnow'] = 1;
    $params['mailedpending'] = HSUFORUM_MAILED_PENDING;

    if (empty($CFG->hsuforum_enabletimedposts)) {
        return $DB->execute("UPDATE {hsuforum_posts}
                             SET mailed = :mailedsuccess
                             WHERE (created < :endtime OR mailnow = :mailnow)
                             AND mailed = :mailedpending", $params);
    } else {
        return $DB->execute("UPDATE {hsuforum_posts}
                             SET mailed = :mailedsuccess
                             WHERE discussion NOT IN (SELECT d.id
                                                      FROM {hsuforum_discussions} d
                                                      WHERE d.timestart > :now)
                             AND (created < :endtime OR mailnow = :mailnow)
                             AND mailed = :mailedpending", $params);
    }
}

/**
 * Get all the posts for a user in a forum suitable for hsuforum_print_post
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @return array
 */
function hsuforum_get_user_posts($forumid, $userid, context_module $context = null) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($forumid, $userid);

    if (!empty($CFG->hsuforum_enabletimedposts)) {
        if (is_null($context)) {
            $cm = get_coursemodule_from_instance('hsuforum', $forumid);
            $context = context_module::instance($cm->id);
        }
        if (!has_capability('mod/hsuforum:viewhiddentimedposts' , $context)) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, d.forum, $allnames, u.email, u.picture, u.imagealt
                              FROM {hsuforum} f
                                   JOIN {hsuforum_discussions} d ON d.forum = f.id
                                   JOIN {hsuforum_posts} p       ON p.discussion = d.id
                                   JOIN {user} u              ON u.id = p.userid
                             WHERE f.id = ?
                                   AND p.userid = ?
                                   $timedsql
                          ORDER BY p.modified ASC", $params);
}

/**
 * Get all the discussions user participated in
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param int $forumid
 * @param int $userid
 * @return array Array or false
 */
function hsuforum_get_user_involved_discussions($forumid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($forumid, $userid);
    if (!empty($CFG->hsuforum_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('hsuforum', $forumid);
        if (!has_capability('mod/hsuforum:viewhiddentimedposts' , context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB->get_records_sql("SELECT DISTINCT d.*
                              FROM {hsuforum} f
                                   JOIN {hsuforum_discussions} d ON d.forum = f.id
                                   JOIN {hsuforum_posts} p       ON p.discussion = d.id
                             WHERE f.id = ?
                                   AND p.userid = ?
                                   $timedsql", $params);
}

/**
 * Get all the posts for a user in a forum suitable for hsuforum_print_post
 *
 * @global object
 * @global object
 * @param int $forumid
 * @param int $userid
 * @return array of counts or false
 */
function hsuforum_count_user_posts($forumid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($forumid, $userid);
    if (!empty($CFG->hsuforum_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('hsuforum', $forumid);
        if (!has_capability('mod/hsuforum:viewhiddentimedposts' , context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB->get_record_sql("SELECT COUNT(p.id) AS postcount, MAX(p.modified) AS lastpost
                             FROM {hsuforum} f
                                  JOIN {hsuforum_discussions} d ON d.forum = f.id
                                  JOIN {hsuforum_posts} p       ON p.discussion = d.id
                                  JOIN {user} u              ON u.id = p.userid
                            WHERE f.id = ?
                                  AND p.userid = ?
                                  $timedsql", $params);
}

/**
 * Given a log entry, return the forum post details for it.
 *
 * @global object
 * @global object
 * @param object $log
 * @return array|null
 */
function hsuforum_get_post_from_log($log) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    if ($log->action == "add post") {

        return $DB->get_record_sql("SELECT p.*, f.type AS forumtype, d.forum, d.groupid, $allnames, u.email, u.picture
                                 FROM {hsuforum_discussions} d,
                                      {hsuforum_posts} p,
                                      {hsuforum} f,
                                      {user} u
                                WHERE p.id = ?
                                  AND d.id = p.discussion
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.forum", array($log->info));


    } else if ($log->action == "add discussion") {

        return $DB->get_record_sql("SELECT p.*, f.type AS forumtype, d.forum, d.groupid, $allnames, u.email, u.picture
                                 FROM {hsuforum_discussions} d,
                                      {hsuforum_posts} p,
                                      {hsuforum} f,
                                      {user} u
                                WHERE d.id = ?
                                  AND d.firstpost = p.id
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.forum", array($log->info));
    }
    return NULL;
}

/**
 * Given a discussion id, return the first post from the discussion
 *
 * @global object
 * @global object
 * @param int $dicsussionid
 * @return array
 */
function hsuforum_get_firstpost_from_discussion($discussionid) {
    global $CFG, $DB;

    return $DB->get_record_sql("SELECT p.*
                             FROM {hsuforum_discussions} d,
                                  {hsuforum_posts} p
                            WHERE d.id = ?
                              AND d.firstpost = p.id ", array($discussionid));
}

/**
 * @global object
 * @global object
 * @global object
 * @staticvar array $cache
 * @param object $forum
 * @param object $cm
 * @param object $course
 * @return mixed
 */
function hsuforum_count_discussions($forum, $cm, $course) {
    global $CFG, $DB, $USER;

    static $cache = array();

    $now = round(time(), -2); // db cache friendliness

    $params = array($course->id);

    if (!isset($cache[$course->id])) {
        if (!empty($CFG->hsuforum_enabletimedposts)) {
            $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
            $params[] = $now;
            $params[] = $now;
        } else {
            $timedsql = "";
        }

        $sql = "SELECT f.id, COUNT(d.id) as dcount
                  FROM {hsuforum} f
                       JOIN {hsuforum_discussions} d ON d.forum = f.id
                 WHERE f.course = ?
                       $timedsql
              GROUP BY f.id";

        if ($counts = $DB->get_records_sql($sql, $params)) {
            foreach ($counts as $count) {
                $counts[$count->id] = $count->dcount;
            }
            $cache[$course->id] = $counts;
        } else {
            $cache[$course->id] = array();
        }
    }

    if (empty($cache[$course->id][$forum->id])) {
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $cache[$course->id][$forum->id];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
        return $cache[$course->id][$forum->id];
    }

    require_once($CFG->dirroot.'/course/lib.php');

    $modinfo = get_fast_modinfo($course);

    $mygroups = $modinfo->get_groups($cm->groupingid);

    // add all groups posts
    $mygroups[-1] = -1;

    list($mygroups_sql, $params) = $DB->get_in_or_equal($mygroups);
    $params[] = $forum->id;

    if (!empty($CFG->hsuforum_enabletimedposts)) {
        $timedsql = "AND d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    $sql = "SELECT COUNT(d.id)
              FROM {hsuforum_discussions} d
             WHERE d.groupid $mygroups_sql AND d.forum = ?
                   $timedsql";

    return $DB->get_field_sql($sql, $params);
}

/**
 * How many posts by other users are unrated by a given user in the given discussion?
 *
 * TODO: Is this function still used anywhere?
 *
 * @param int $discussionid
 * @param int $userid
 * @return mixed
 */
function hsuforum_count_unrated_posts($discussionid, $userid) {
    global $CFG, $DB;

    $sql = "SELECT COUNT(*) as num
              FROM {hsuforum_posts}
             WHERE parent > 0
               AND discussion = :discussionid
               AND userid <> :userid";
    $params = array('discussionid' => $discussionid, 'userid' => $userid);
    $posts = $DB->get_record_sql($sql, $params);
    if ($posts) {
        $sql = "SELECT count(*) as num
                  FROM {hsuforum_posts} p,
                       {rating} r
                 WHERE p.discussion = :discussionid AND
                       p.id = r.itemid AND
                       r.userid = userid AND
                       r.component = 'mod_hsuforum' AND
                       r.ratingarea = 'post'";
        $rated = $DB->get_record_sql($sql, $params);
        if ($rated) {
            if ($posts->num > $rated->num) {
                return $posts->num - $rated->num;
            } else {
                return 0;    // Just in case there was a counting error
            }
        } else {
            return $posts->num;
        }
    } else {
        return 0;
    }
}

/**
 * Get all discussions in a forum
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @param string $forumsort
 * @param bool $forumselect True == All post data, False == limited post data, String == custom select fields
 * @param int $limit
 * @param bool $userlastmodified
 * @param int $page
 * @param int $perpage
 * @return moodle_recordset|array
 */
function hsuforum_get_discussions($cm, $forumsort="d.timemodified DESC", $forumselect=true, $limit=-1, $userlastmodified=false, $page=-1, $perpage=0, $returnrs = true) {
    global $CFG, $DB, $USER;

    require_once(__DIR__.'/lib/discussion/subscribe.php');

    $timelimit = '';

    $now = round(time(), -2);
    $cutoffdate = $now - ($CFG->hsuforum_oldpostdays*24*60*60);
    $params = array($cm->instance, $USER->id, $USER->id);

    $modcontext = context_module::instance($cm->id);

    if (!has_capability('mod/hsuforum:viewdiscussion', $modcontext)) { /// User must have perms to view discussions
        return array();
    }

    $forum = $DB->get_record('hsuforum', array('id' => $cm->instance), '*', MUST_EXIST);

    if (hsuforum_tp_is_tracked($forum)) {
        $trackselect = ' unread.unread, dunread.postread, ';
        $tracksql    = 'LEFT OUTER JOIN (
            SELECT d.id, COUNT(p.id) AS unread
              FROM {hsuforum_discussions} d
              JOIN {hsuforum_posts} p ON p.discussion = d.id
         LEFT JOIN {hsuforum_read} r ON (r.postid = p.id AND r.userid = ?)
             WHERE d.forum = ?
               AND p.modified >= ? AND r.id is NULL
               AND (p.privatereply = 0 OR p.privatereply = ? OR p.userid = ?)
          GROUP BY d.id
        ) unread ON d.id = unread.id

   LEFT OUTER JOIN (
            SELECT d.id, CASE WHEN r.id IS NULL THEN 0 ELSE 1 END AS postread
              FROM {hsuforum_discussions} d
              JOIN {hsuforum_posts} p ON p.discussion = d.id AND p.parent = 0
   LEFT OUTER JOIN {hsuforum_read} r ON (r.postid = p.id AND r.userid = ?)
             WHERE d.forum = ?
               AND p.modified >= ?
        ) dunread ON d.id = dunread.id';

        $params = array_merge($params, array($USER->id, $cm->instance, $cutoffdate, $USER->id, $USER->id, $USER->id, $cm->instance, $cutoffdate));
    } else {
        $trackselect = $tracksql = '';
    }
    $subscribe = new hsuforum_lib_discussion_subscribe($forum, $modcontext);
    if ($subscribe->can_subscribe()) {
        $subscribeselect = ' sd.id AS subscriptionid, ';
        $subscribesql = 'LEFT OUTER JOIN {hsuforum_subscriptions_disc} sd ON d.id = sd.discussion AND sd.userid = ?';
        $params[] = $USER->id;
    } else {
        $subscribeselect = $subscribesql = '';
    }
    $params[] = $cm->instance;

    if (!empty($CFG->hsuforum_enabletimedposts)) { /// Users must fulfill timed posts

        if (!has_capability('mod/hsuforum:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= " OR d.userid = ?";
                $params[] = $USER->id;
            }
            $timelimit .= ")";
        }
    }

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum  = $limit;
    } else if ($page != -1) {
        $limitfrom = $page*$perpage;
        $limitnum  = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum  = 0;
    }

    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        if (empty($modcontext)) {
            $modcontext = context_module::instance($cm->id);
        }

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            //seprate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }


    if (empty($forumsort)) {
        $forumsort = "d.timemodified DESC";
    }
    if (empty($forumselect)) {
        $postdata = "p.id,p.subject,p.modified,p.discussion,p.userid,p.reveal,p.flags,p.privatereply";
    } else {
        $postdata = "p.*";
    }

    if (empty($userlastmodified)) {  // We don't need to know this
        $umfields = "";
        $umtable  = "";
    } else {
        $umfields = ', up.reveal AS umreveal, ' . get_all_user_name_fields(true, 'um', null, 'um');
        $umtable  = " LEFT JOIN {user} um ON (d.usermodified = um.id)
                      LEFT OUTER JOIN {hsuforum_posts} up ON extra.lastpostid = up.id";
    }

    // Sort of hacky, but allows for custom select
    if (is_string($forumselect) and !empty($forumselect)) {
        $selectsql = $forumselect;
    } else {
        $allnames  = get_all_user_name_fields(true, 'u');
        $selectsql = "$postdata, d.name, d.timemodified, d.usermodified, d.groupid, d.timestart, d.timeend, d.assessed,
                           d.firstpost, extra.replies, extra.lastpostid,$trackselect$subscribeselect
                           $allnames, u.email, u.picture, u.imagealt $umfields";
    }

    $sql = "SELECT $selectsql
              FROM {hsuforum_discussions} d
                   JOIN {hsuforum_posts} p ON p.discussion = d.id
                   JOIN {user} u ON p.userid = u.id
        LEFT OUTER JOIN (SELECT p.discussion, COUNT(p.id) AS replies, MAX(p.id) AS lastpostid
                           FROM {hsuforum_posts} p
                           JOIN {hsuforum_discussions} d ON p.discussion = d.id
                          WHERE p.parent > 0
                            AND d.forum = ?
                            AND (p.privatereply = 0 OR p.privatereply = ? OR p.userid = ?)
                          GROUP BY p.discussion) extra ON d.id = extra.discussion
                   $tracksql
                   $subscribesql
                   $umtable
             WHERE d.forum = ? AND p.parent = 0
                   $timelimit $groupselect
          ORDER BY $forumsort";

    if ($returnrs) {
        return $DB->get_recordset_sql($sql, $params, $limitfrom, $limitnum);
    }
    return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
}

/**
 * @global object
 * @global object
 * @global object
 * @uses CONEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @return int
 */
function hsuforum_get_discussions_count($cm) {
    global $CFG, $DB, $USER;

    $now = round(time(), -2);
    $params = array($cm->instance);
    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = context_module::instance($cm->id);

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            //seprate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }

    $cutoffdate = $now - ($CFG->hsuforum_oldpostdays*24*60*60);

    $timelimit = "";

    if (!empty($CFG->hsuforum_enabletimedposts)) {

        $modcontext = context_module::instance($cm->id);

        if (!has_capability('mod/hsuforum:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= " OR d.userid = ?";
                $params[] = $USER->id;
            }
            $timelimit .= ")";
        }
    }

    $sql = "SELECT COUNT(d.id)
              FROM {hsuforum_discussions} d
                   JOIN {hsuforum_posts} p ON p.discussion = d.id
             WHERE d.forum = ? AND p.parent = 0
                   $groupselect $timelimit";

    return $DB->get_field_sql($sql, $params);
}


/**
 * Get all discussions started by a particular user in a course (or group)
 * This function no longer used ...
 *
 * @todo Remove this function if no longer used
 * @global object
 * @global object
 * @param int $courseid
 * @param int $userid
 * @param int $groupid
 * @return array
 */
function hsuforum_get_user_discussions($courseid, $userid, $groupid=0) {
    global $CFG, $DB;
    $params = array($courseid, $userid);
    if ($groupid) {
        $groupselect = " AND d.groupid = ? ";
        $params[] = $groupid;
    } else  {
        $groupselect = "";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, d.groupid, $allnames, u.email, u.picture, u.imagealt,
                                   f.type as forumtype, f.name as forumname, f.id as forumid
                              FROM {hsuforum_discussions} d,
                                   {hsuforum_posts} p,
                                   {user} u,
                                   {hsuforum} f
                             WHERE d.course = ?
                               AND p.discussion = d.id
                               AND p.parent = 0
                               AND p.userid = u.id
                               AND u.id = ?
                               AND d.forum = f.id $groupselect
                          ORDER BY p.created DESC", $params);
}

/**
 * Get the list of potential subscribers to a forum.
 *
 * @param object $forumcontext the forum context.
 * @param integer $groupid the id of a group, or 0 for all groups.
 * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
 * @param string $sort sort order. As for get_users_by_capability.
 * @return array list of users.
 */
function hsuforum_get_potential_subscribers($forumcontext, $groupid, $fields, $sort = '') {
    global $DB;

    // only active enrolled users or everybody on the frontpage
    list($esql, $params) = get_enrolled_sql($forumcontext, 'mod/hsuforum:allowforcesubscribe', $groupid, true);
    if (!$sort) {
        list($sort, $sortparams) = users_order_by_sql('u');
        $params = array_merge($params, $sortparams);
    }

    $sql = "SELECT $fields
              FROM {user} u
              JOIN ($esql) je ON je.id = u.id
          ORDER BY $sort";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Returns list of user objects that are subscribed to this forum
 *
 * @global object
 * @global object
 * @param object $course the course
 * @param forum $forum the forum
 * @param integer $groupid group id, or 0 for all.
 * @param object $context the forum context, to save re-fetching it where possible.
 * @param string $fields requested user fields (with "u." table prefix)
 * @return array list of users.
 */
function hsuforum_subscribed_users($course, $forum, $groupid=0, $context = null, $fields = null) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    if (empty($fields)) {
        $fields ="u.id,
                  u.username,
                  $allnames,
                  u.maildisplay,
                  u.mailformat,
                  u.maildigest,
                  u.imagealt,
                  u.email,
                  u.emailstop,
                  u.city,
                  u.country,
                  u.lastaccess,
                  u.lastlogin,
                  u.picture,
                  u.timezone,
                  u.theme,
                  u.lang,
                  u.trackforums,
                  u.mnethostid";
    }

    if (empty($context)) {
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id, $course->id);
        $context = context_module::instance($cm->id);
    }

    if (hsuforum_is_forcesubscribed($forum)) {
        $results = hsuforum_get_potential_subscribers($context, $groupid, $fields, "u.email ASC");

    } else {
        // only active enrolled users or everybody on the frontpage
        list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
        $params['forumid'] = $forum->id;
        $results = $DB->get_records_sql("SELECT $fields
                                           FROM {user} u
                                           JOIN ($esql) je ON je.id = u.id
                                           JOIN {hsuforum_subscriptions} s ON s.userid = u.id
                                          WHERE s.forum = :forumid
                                       ORDER BY u.email ASC", $params);
    }

    // Guest user should never be subscribed to a forum.
    unset($results[$CFG->siteguest]);

    return $results;
}



// OTHER FUNCTIONS ///////////////////////////////////////////////////////////


/**
 * @global object
 * @global object
 * @param int $courseid
 * @param string $type
 */
function hsuforum_get_course_forum($courseid, $type) {
// How to set up special 1-per-course forums
    global $CFG, $DB, $OUTPUT, $USER;

    if ($forums = $DB->get_records_select("hsuforum", "course = ? AND type = ?", array($courseid, $type), "id ASC")) {
        // There should always only be ONE, but with the right combination of
        // errors there might be more.  In this case, just return the oldest one (lowest ID).
        foreach ($forums as $forum) {
            return $forum;   // ie the first one
        }
    }

    // Doesn't exist, so create one now.
    $forum = new stdClass();
    $forum->course = $courseid;
    $forum->type = "$type";
    if (!empty($USER->htmleditor)) {
        $forum->introformat = $USER->htmleditor;
    }
    switch ($forum->type) {
        case "news":
            $forum->name  = get_string("namenews", "hsuforum");
            $forum->intro = get_string("intronews", "hsuforum");
            $forum->forcesubscribe = HSUFORUM_FORCESUBSCRIBE;
            $forum->assessed = 0;
            if ($courseid == SITEID) {
                $forum->name  = get_string("sitenews");
                $forum->forcesubscribe = 0;
            }
            break;
        case "social":
            $forum->name  = get_string("namesocial", "hsuforum");
            $forum->intro = get_string("introsocial", "hsuforum");
            $forum->assessed = 0;
            $forum->forcesubscribe = 0;
            break;
        case "blog":
            $forum->name = get_string('blogforum', 'hsuforum');
            $forum->intro = get_string('introblog', 'hsuforum');
            $forum->assessed = 0;
            $forum->forcesubscribe = 0;
            break;
        default:
            echo $OUTPUT->notification("That forum type doesn't exist!");
            return false;
            break;
    }

    $forum->timemodified = time();
    $forum->id = $DB->insert_record("hsuforum", $forum);

    if (! $module = $DB->get_record("modules", array("name" => "hsuforum"))) {
        echo $OUTPUT->notification("Could not find hsuforum module!!");
        return false;
    }
    $mod = new stdClass();
    $mod->course = $courseid;
    $mod->module = $module->id;
    $mod->instance = $forum->id;
    $mod->section = 0;
    include_once("$CFG->dirroot/course/lib.php");
    if (! $mod->coursemodule = add_course_module($mod) ) {
        echo $OUTPUT->notification("Could not add a new course module to the course '" . $courseid . "'");
        return false;
    }
    $sectionid = course_add_cm_to_section($courseid, $mod->coursemodule, 0);
    return $DB->get_record("hsuforum", array("id" => "$forum->id"));
}


/**
 * Given the data about a posting, builds up the HTML to display it and
 * returns the HTML in a string.  This is designed for sending via HTML email.
 *
 * @global object
 * @param object $course
 * @param object $cm
 * @param object $forum
 * @param object $discussion
 * @param object $post
 * @param object $userform
 * @param object $userto
 * @param bool $ownpost
 * @param bool $reply
 * @param bool $link
 * @param bool $rate
 * @param string $footer
 * @return string
 */
function hsuforum_make_mail_post($course, $cm, $forum, $discussion, $post, $userfrom, $userto,
                              $ownpost=false, $reply=false, $link=false, $rate=false, $footer="") {

    global $CFG, $OUTPUT;

    $modcontext = context_module::instance($cm->id);

    if (!isset($userto->viewfullnames[$forum->id])) {
        $viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
    } else {
        $viewfullnames = $userto->viewfullnames[$forum->id];
    }

    $postuser = hsuforum_anonymize_user($userfrom, $forum, $post);

    // add absolute file links
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_hsuforum', 'post', $post->id);

    // format the post body
    $options = new stdClass();
    $options->para = true;
    $formattedtext = format_text($post->message, $post->messageformat, $options, $course->id);

    $output = '<table border="0" cellpadding="3" cellspacing="0" class="forumpost">';

    $output .= '<tr class="header"><td width="35" valign="top" class="picture left">';
    $output .= $OUTPUT->user_picture($postuser, array('courseid'=>$course->id, 'link' => (!hsuforum_is_anonymous_user($postuser))));
    $output .= '</td>';

    if ($post->parent) {
        $output .= '<td class="topic">';
    } else {
        $output .= '<td class="topic starter">';
    }
    $output .= '<div class="subject">'.format_string($post->subject).'</div>';

    $fullname = fullname($postuser, $viewfullnames);
    $by = new stdClass();
    if (!hsuforum_is_anonymous_user($postuser)) {
        $by->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$postuser->id.'&amp;course='.$course->id.'">'.$fullname.'</a>';
    } else {
        $by->name = $fullname;
    }
    $by->date = userdate($post->modified, '', $userto->timezone);
    $output .= '<div class="author">'.get_string('bynameondate', 'hsuforum', $by).'</div>';

    $output .= '</td></tr>';

    $output .= '<tr><td class="left side" valign="top">';

    if (isset($userfrom->groups)) {
        $groups = $userfrom->groups[$forum->id];
    } else {
        $groups = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
    }

    if ($groups) {
        $output .= print_group_picture($groups, $course->id, false, true, true);
    } else {
        $output .= '&nbsp;';
    }

    $output .= '</td><td class="content">';

    $attachments = hsuforum_print_attachments($post, $cm, 'html');
    if ($attachments !== '') {
        $output .= '<div class="attachments">';
        $output .= $attachments;
        $output .= '</div>';
    }

    $output .= $formattedtext;

// Commands
    $commands = array();

    if ($post->parent) {
        $commands[] = '<a target="_blank" href="'.$CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.
                      $post->discussion.'&amp;parent='.$post->parent.'">'.get_string('parent', 'hsuforum').'</a>';
    }

    if ($reply) {
        $commands[] = '<a target="_blank" href="'.$CFG->wwwroot.'/mod/hsuforum/post.php?reply='.$post->id.'">'.
                      get_string('reply', 'hsuforum').'</a>';
    }

    $output .= '<div class="commands">';
    $output .= implode(' | ', $commands);
    $output .= '</div>';

// Context link to post if required
    if ($link) {
        $output .= '<div class="link">';
        $output .= '<a target="_blank" href="'.$CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.$post->discussion.'#p'.$post->id.'">'.
                     get_string('postincontext', 'hsuforum').'</a>';
        $output .= '</div>';
    }

    if ($footer) {
        $output .= '<div class="footer">'.$footer.'</div>';
    }
    $output .= '</td></tr></table>'."\n\n";

    return $output;
}

/**
 * Print a forum post
 *
 * @global object
 * @global object
 * @uses HSUFORUM_MODE_THREADED
 * @uses PORTFOLIO_FORMAT_PLAINHTML
 * @uses PORTFOLIO_FORMAT_FILE
 * @uses PORTFOLIO_FORMAT_RICHHTML
 * @uses PORTFOLIO_ADD_TEXT_LINK
 * @uses CONTEXT_MODULE
 * @param object $post The post to print.
 * @param object $discussion
 * @param object $forum
 * @param object $cm
 * @param object $course
 * @param boolean $ownpost Whether this post belongs to the current user.
 * @param boolean $reply Whether to print a 'reply' link at the bottom of the message.
 * @param boolean $link Just print a shortened version of the post as a link to the full post.
 * @param string $footer Extra stuff to print after the message.
 * @param string $highlight Space-separated list of terms to highlight.
 * @param int $post_read true, false or -99. If we already know whether this user
 *          has read this post, pass that in, otherwise, pass in -99, and this
 *          function will work it out.
 * @param boolean $dummyifcantsee When hsuforum_user_can_see_post says that
 *          the current user can't see this post, if this argument is true
 *          (the default) then print a dummy 'you can't see this post' post.
 *          If false, don't output anything at all.
 * @param bool|null $istracked
 * @return void|string
 */
function hsuforum_print_post($post, $discussion, $forum, &$cm, $course, $ownpost=false, $reply=false, $link=false,
                          $footer="", $highlight="", $postisread=null, $dummyifcantsee=true, $istracked=null, $return=false, $commandsoverride = null) {
    global $USER, $CFG, $OUTPUT, $PAGE;

    require_once($CFG->libdir . '/filelib.php');

    // String cache
    static $str;

    $modcontext = context_module::instance($cm->id);

    $post->course = $course->id;
    $post->forum  = $forum->id;
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_hsuforum', 'post', $post->id);
    if (!empty($CFG->enableplagiarism)) {
        require_once($CFG->libdir.'/plagiarismlib.php');
        $post->message .= plagiarism_get_links(array('userid' => $post->userid,
            'content' => $post->message,
            'cmid' => $cm->id,
            'course' => $post->course,
            'hsuforum' => $post->forum));
    }

    // caching
    if (!isset($cm->cache)) {
        $cm->cache = new stdClass;
    }

    if (!isset($cm->cache->caps)) {
        $cm->cache->caps = array();
        $cm->cache->caps['mod/hsuforum:viewdiscussion']   = has_capability('mod/hsuforum:viewdiscussion', $modcontext);
        $cm->cache->caps['moodle/site:viewfullnames']  = has_capability('moodle/site:viewfullnames', $modcontext);
        $cm->cache->caps['mod/hsuforum:editanypost']      = has_capability('mod/hsuforum:editanypost', $modcontext);
        $cm->cache->caps['mod/hsuforum:splitdiscussions'] = has_capability('mod/hsuforum:splitdiscussions', $modcontext);
        $cm->cache->caps['mod/hsuforum:deleteownpost']    = has_capability('mod/hsuforum:deleteownpost', $modcontext);
        $cm->cache->caps['mod/hsuforum:deleteanypost']    = has_capability('mod/hsuforum:deleteanypost', $modcontext);
        $cm->cache->caps['mod/hsuforum:viewanyrating']    = has_capability('mod/hsuforum:viewanyrating', $modcontext);
        $cm->cache->caps['mod/hsuforum:exportpost']       = has_capability('mod/hsuforum:exportpost', $modcontext);
        $cm->cache->caps['mod/hsuforum:exportownpost']    = has_capability('mod/hsuforum:exportownpost', $modcontext);
    }

    if (!isset($cm->uservisible)) {
        $cm->uservisible = coursemodule_visible_for_user($cm);
    }

    if ($istracked && is_null($postisread)) {
        $postisread = hsuforum_tp_is_post_read($USER->id, $post);
    }

    if (!hsuforum_user_can_see_post($forum, $discussion, $post, NULL, $cm)) {
        $output = '';
        if (!$dummyifcantsee) {
            if ($return) {
                return $output;
            }
            echo $output;
            return;
        }
        $output .= html_writer::tag('a', '', array('id'=>'p'.$post->id));
        $output .= html_writer::start_tag('div', array('id' => 'postid'.$post->id, 'class'=>'forumpost clearfix'));
        $output .= html_writer::start_tag('div', array('id' => 'postid'.$post->id, 'class' => 'forumpost clearfix',
                                                       'role' => 'region',
                                                       'aria-label' => get_string('hiddenforumpost', 'hsuforum')));
        $output .= html_writer::start_tag('div', array('class'=>'row header'));
        $output .= html_writer::tag('div', '', array('class'=>'left picture')); // Picture
        if ($post->parent) {
            $output .= html_writer::start_tag('div', array('class'=>'topic'));
        } else {
            $output .= html_writer::start_tag('div', array('class'=>'topic starter'));
        }
        $output .= html_writer::tag('div', get_string('forumsubjecthidden','hsuforum'), array('class' => 'subject',
                                                                                           'role' => 'header')); // Subject.
        $output .= html_writer::tag('div', get_string('forumauthorhidden', 'hsuforum'), array('class' => 'author',
                                                                                           'role' => 'header')); // Author.
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div'); // row
        $output .= html_writer::start_tag('div', array('class'=>'row maincontent clearfix'));
        $output .= html_writer::tag('div', '&nbsp;', array('class'=>'left')); // Groups
        $output .= html_writer::start_tag('div', array('class'=>'no-overflow'));
        $output .= html_writer::tag('div', get_string('forumbodyhidden','hsuforum'), array('class'=>'content')); // Content
        $output .= html_writer::end_tag('div'); // no-overflow
        $output .= html_writer::end_tag('div'); // row
        $output .= html_writer::end_tag('div'); // forumpost

        if ($return) {
            return $output;
        }
        echo $output;
        return;
    }

    if (empty($str) or $str->forumid != $forum->id) {
        $str = new stdClass;
        $str->forumid      = $forum->id;
        $str->edit         = get_string('edit', 'hsuforum');
        $str->delete       = get_string('delete', 'hsuforum');
        $str->reply        = get_string('reply', 'hsuforum');
        $str->parent       = get_string('parent', 'hsuforum');
        $str->pruneheading = get_string('pruneheading', 'hsuforum');
        $str->prune        = get_string('prune', 'hsuforum');
        $str->displaymode  = hsuforum_get_layout_mode($forum);
        $str->markread     = get_string('markread', 'hsuforum');
        $str->markunread   = get_string('markunread', 'hsuforum');
    }

    $discussionlink = new moodle_url('/mod/hsuforum/discuss.php', array('d'=>$post->discussion));

    // Build an object that represents the posting user
    $postuser = new stdClass;
    $postuserfields = explode(',', user_picture::fields());
    $postuser = username_load_fields_from_object($postuser, $post, null, $postuserfields);
    $postuser->id = $post->userid;
    $postuser->fullname    = fullname($postuser, $cm->cache->caps['moodle/site:viewfullnames']);
    $postuser->profilelink = new moodle_url('/user/view.php', array('id'=>$post->userid, 'course'=>$course->id));

    $postuser = hsuforum_anonymize_user($postuser, $forum, $post);

    // Prepare the groups the posting user belongs to
    if (isset($cm->cache->usersgroups)) {
        $groups = array();
        if (isset($cm->cache->usersgroups[$post->userid])) {
            foreach ($cm->cache->usersgroups[$post->userid] as $gid) {
                $groups[$gid] = $cm->cache->groups[$gid];
            }
        }
    } else {
        $groups = groups_get_all_groups($course->id, $post->userid, $cm->groupingid);
    }

    // Prepare the attachements for the post, files then images
    list($attachments, $attachedimages) = hsuforum_print_attachments($post, $cm, 'separateimages');

    // Determine if we need to shorten this post
    $shortenpost = ($link && (strlen(strip_tags($post->message)) > $CFG->hsuforum_longpost));


    // Prepare an array of commands
    $commands = array();

    // SPECIAL CASE: The front page can display a news item post to non-logged in users.
    // Don't display the mark read / unread controls in this case.
    if ($istracked && $CFG->hsuforum_usermarksread && isloggedin()) {
        $url = new moodle_url($discussionlink, array('postid'=>$post->id, 'mark'=>'unread'));
        $text = $str->markunread;
        if (!$postisread) {
            $url->param('mark', 'read');
            $text = $str->markread;
        }
        if ($str->displaymode == HSUFORUM_MODE_THREADED) {
            $url->param('parent', $post->parent);
        } else {
            $url->set_anchor('p'.$post->id);
        }
        $commands[] = array('url'=>$url, 'text'=>$text);
    }

    // Zoom in to the parent specifically
    if ($post->parent) {
        $url = new moodle_url($discussionlink);
        if ($str->displaymode == HSUFORUM_MODE_THREADED) {
            $url->param('parent', $post->parent);
        } else {
            $url->set_anchor('p'.$post->parent);
        }
        $commands[] = array('url'=>$url, 'text'=>$str->parent);
    }

    // Hack for allow to edit news posts those are not displayed yet until they are displayed
    $age = time() - $post->created;
    if (!$post->parent && $forum->type == 'news' && $discussion->timestart > time()) {
        $age = 0;
    }

    if ($forum->type == 'single' and $discussion->firstpost == $post->id) {
        if (has_capability('moodle/course:manageactivities', $modcontext)) {
            // The first post in single simple is the forum description.
            $commands[] = array('url'=>new moodle_url('/course/modedit.php', array('update'=>$cm->id, 'sesskey'=>sesskey(), 'return'=>1)), 'text'=>$str->edit);
        }
    } else if (($ownpost && $age < $CFG->maxeditingtime) || $cm->cache->caps['mod/hsuforum:editanypost']) {
        $commands[] = array('url'=>new moodle_url('/mod/hsuforum/post.php', array('edit'=>$post->id)), 'text'=>$str->edit);
    }

    if ($cm->cache->caps['mod/hsuforum:splitdiscussions'] && $post->parent && $forum->type != 'single') {
        $commands[] = array('url'=>new moodle_url('/mod/hsuforum/post.php', array('prune'=>$post->id)), 'text'=>$str->prune, 'title'=>$str->pruneheading);
    }

    if ($forum->type == 'single' and $discussion->firstpost == $post->id) {
        // Do not allow deleting of first post in single simple type.
    } else if (($ownpost && $age < $CFG->maxeditingtime && $cm->cache->caps['mod/hsuforum:deleteownpost']) || $cm->cache->caps['mod/hsuforum:deleteanypost']) {
        $commands[] = array('url'=>new moodle_url('/mod/hsuforum/post.php', array('delete'=>$post->id)), 'text'=>$str->delete);
    }

    if (!property_exists($post, 'privatereply')) {
        throw new coding_exception('Must set post\'s privatereply property!');
    }
    if ($reply and empty($post->privatereply)) {
        $commands[] = array('url'=>new moodle_url('/mod/hsuforum/post.php#mformforum', array('reply'=>$post->id)), 'text'=>$str->reply);
    }

    if ($CFG->enableportfolios && empty($forum->anonymous) && ($cm->cache->caps['mod/hsuforum:exportpost'] || ($ownpost && $cm->cache->caps['mod/hsuforum:exportownpost']))) {
        $p = array('postid' => $post->id);
        require_once($CFG->libdir.'/portfoliolib.php');
        $button = new portfolio_add_button();
        $button->set_callback_options('hsuforum_portfolio_caller', array('postid' => $post->id), 'mod_hsuforum');
        if (empty($attachments)) {
            $button->set_formats(PORTFOLIO_FORMAT_PLAINHTML);
        } else {
            $button->set_formats(PORTFOLIO_FORMAT_RICHHTML);
        }

        $porfoliohtml = $button->to_html(PORTFOLIO_ADD_TEXT_LINK);
        if (!empty($porfoliohtml)) {
            $commands[] = $porfoliohtml;
        }
    }
    // Finished building commands


    // Begin output

    $output  = '';

    if ($istracked) {
        if ($postisread) {
            $forumpostclass = ' read';
        } else {
            $forumpostclass = ' unread';
            $output .= html_writer::tag('a', '', array('name'=>'unread'));
        }
    } else {
        // ignore trackign status if not tracked or tracked param missing
        $forumpostclass = '';
    }

    $topicclass = '';
    if (empty($post->parent)) {
        $topicclass = ' firstpost starter';
    }

    $postbyuser = new stdClass;
    $postbyuser->post = $post->subject;
    $postbyuser->user = $postuser->fullname;
    $discussionbyuser = get_string('postbyuser', 'hsuforum', $postbyuser);
    $output .= html_writer::tag('a', '', array('id'=>'p'.$post->id));
    $output .= html_writer::start_tag('div', array('id' => 'postid'.$post->id, 'class'=>'forumpost clearfix'.$forumpostclass.$topicclass,
                                                   'role' => 'region',
                                                   'aria-label' => $discussionbyuser));
    $output .= html_writer::start_tag('div', array('class'=>'row header clearfix'));
    $output .= html_writer::start_tag('div', array('class'=>'left picture'));
    $output .= $OUTPUT->user_picture($postuser, array('courseid'=>$course->id, 'link' => (!hsuforum_is_anonymous_user($postuser))));
    $output .= html_writer::end_tag('div');


    $output .= html_writer::start_tag('div', array('class'=>'topic'.$topicclass));

    $postsubject = $post->subject;
    if (empty($post->subjectnoformat)) {
        $postsubject = format_string($postsubject);
    }
    $postsubject .= $PAGE->get_renderer('mod_hsuforum')->post_flags($post, $modcontext);
    $output .= html_writer::tag('div', $postsubject, array('class'=>'subject',
                                                       'role' => 'heading',
                                                       'aria-level' => '2'));

    $by = new stdClass();
    if (!hsuforum_is_anonymous_user($postuser)) {
        if (has_capability('moodle/course:manageactivities', $modcontext, $postuser->id)) {
            $postuser->fullname = html_writer::tag('span', $postuser->fullname, array('class' => 'hsuforum_highlightposter'));
        }
        $by->name = html_writer::link($postuser->profilelink, $postuser->fullname);
    } else {
        $by->name = $postuser->fullname;
    }
    $by->date = userdate($post->modified);
    $output .= html_writer::tag('div', get_string('bynameondate', 'hsuforum', $by), array('class'=>'author',
                                                                                   'role' => 'heading',
                                                                                   'aria-level' => '2'));

    $output .= html_writer::end_tag('div'); //topic
    $output .= html_writer::end_tag('div'); //row

    $output .= html_writer::start_tag('div', array('class'=>'row maincontent clearfix'));
    $output .= html_writer::start_tag('div', array('class'=>'left'));

    $groupoutput = '';
    if ($groups) {
        $groupoutput = print_group_picture($groups, $course->id, false, true, true);
    }
    if (empty($groupoutput)) {
        $groupoutput = '&nbsp;';
    }
    $output .= html_writer::tag('div', $groupoutput, array('class'=>'grouppictures'));

    $output .= html_writer::end_tag('div'); //left side
    $output .= html_writer::start_tag('div', array('class'=>'no-overflow'));
    $output .= html_writer::start_tag('div', array('class'=>'content'));
    if (!empty($attachments)) {
        $output .= html_writer::tag('div', $attachments, array('class'=>'attachments'));
    }

    $options = new stdClass;
    $options->para    = false;
    $options->trusted = $post->messagetrust;
    $options->context = $modcontext;
    if ($shortenpost) {
        // Prepare shortened version
        $postclass    = 'shortenedpost';
        $postcontent  = format_text($post->message, $post->messageformat, $options);
        $postcontent  = shorten_text($postcontent, $CFG->hsuforum_shortpost);
        $postcontent .= html_writer::link($discussionlink, get_string('readtherest', 'hsuforum'));
        $postcontent .= html_writer::tag('div', '('.get_string('numwords', 'moodle', count_words($post->message)).')',
            array('class'=>'post-word-count'));
    } else {
        // Prepare whole post
        $postclass    = 'fullpost';
        $postcontent  = format_text($post->message, $post->messageformat, $options, $course->id);
        if (!empty($highlight)) {
            $postcontent = highlight($highlight, $postcontent);
        }
        if (!empty($forum->displaywordcount)) {
            $postcontent .= html_writer::tag('div', get_string('numwords', 'moodle', count_words($post->message)),
                array('class'=>'post-word-count'));
        }
        $postcontent .= html_writer::tag('div', $attachedimages, array('class'=>'attachedimages'));
    }

    // Output the post content
    $output .= html_writer::tag('div', $postcontent, array('class'=>'posting '.$postclass));
    $output .= html_writer::end_tag('div'); // Content
    $output .= html_writer::end_tag('div'); // Content mask
    $output .= html_writer::end_tag('div'); // Row

    $output .= html_writer::start_tag('div', array('class'=>'row side'));
    $output .= html_writer::tag('div','&nbsp;', array('class'=>'left'));
    $output .= html_writer::start_tag('div', array('class'=>'options clearfix'));

    // Output ratings
    if (!empty($post->rating)) {
        $output .= html_writer::tag('div', $OUTPUT->render($post->rating), array('class'=>'forum-post-rating'));
    }

    // Output the commands
    $commandhtml = array();
    foreach ($commands as $command) {
        if (is_array($command)) {
            $commandhtml[] = html_writer::link($command['url'], $command['text']);
        } else {
            $commandhtml[] = $command;
        }
    }
    if (!is_null($commandsoverride)) {
        if (!is_array($commandsoverride)) {
            $commandsoverride = array($commandsoverride);
        }
        $commandhtml = $commandsoverride;
    }
    $output .= html_writer::tag('div', implode(' | ', $commandhtml), array('class'=>'commands'));

    // Output link to post if required
    if ($link && hsuforum_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext)) {
        if ($post->replies == 1) {
            $replystring = get_string('repliesone', 'hsuforum', $post->replies);
        } else {
            $replystring = get_string('repliesmany', 'hsuforum', $post->replies);
        }

        $output .= html_writer::start_tag('div', array('class'=>'link'));
        $output .= html_writer::link($discussionlink, get_string('discussthistopic', 'hsuforum'));
        $output .= '&nbsp;('.$replystring.')';
        $output .= html_writer::end_tag('div'); // link
    }

    // Output footer if required
    if ($footer) {
        $output .= html_writer::tag('div', $footer, array('class'=>'footer'));
    }

    // Close remaining open divs
    $output .= html_writer::end_tag('div'); // content
    $output .= html_writer::end_tag('div'); // row
    $output .= html_writer::end_tag('div'); // forumpost

    // Mark the forum post as read if required
    if ($istracked && !$CFG->hsuforum_usermarksread && !$postisread) {
        hsuforum_tp_mark_post_read($USER->id, $post, $forum->id);
    }

    if ($return) {
        return $output;
    }
    echo $output;
    return;
}

/**
 * Return rating related permissions
 *
 * @param string $options the context id
 * @return array an associative array of the user's rating permissions
 */
function hsuforum_rating_permissions($contextid, $component, $ratingarea) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($component != 'mod_hsuforum' || $ratingarea != 'post') {
        // We don't know about this component/ratingarea so just return null to get the
        // default restrictive permissions.
        return null;
    }
    return array(
        'view'    => has_capability('mod/hsuforum:viewrating', $context),
        'viewany' => has_capability('mod/hsuforum:viewanyrating', $context),
        'viewall' => has_capability('mod/hsuforum:viewallratings', $context),
        'rate'    => has_capability('mod/hsuforum:rate', $context)
    );
}

/**
 * Validates a submitted rating
 * @param array $params submitted data
 *            context => object the context in which the rated items exists [required]
 *            component => The component for this module - should always be mod_hsuforum [required]
 *            ratingarea => object the context in which the rated items exists [required]
 *            itemid => int the ID of the object being rated [required]
 *            scaleid => int the scale from which the user can select a rating. Used for bounds checking. [required]
 *            rating => int the submitted rating [required]
 *            rateduserid => int the id of the user whose items have been rated. NOT the user who submitted the ratings. 0 to update all. [required]
 *            aggregation => int the aggregation method to apply when calculating grades ie RATING_AGGREGATE_AVERAGE [required]
 * @return boolean true if the rating is valid. Will throw rating_exception if not
 */
function hsuforum_rating_validate($params) {
    global $DB, $USER;

    // Check the component is mod_hsuforum
    if ($params['component'] != 'mod_hsuforum') {
        throw new rating_exception('invalidcomponent');
    }

    // Check the ratingarea is post (the only rating area in forum)
    if ($params['ratingarea'] != 'post') {
        throw new rating_exception('invalidratingarea');
    }

    // Check the rateduserid is not the current user .. you can't rate your own posts
    if ($params['rateduserid'] == $USER->id) {
        throw new rating_exception('nopermissiontorate');
    }

    // Fetch all the related records ... we need to do this anyway to call hsuforum_user_can_see_post
    $post = $DB->get_record('hsuforum_posts', array('id' => $params['itemid'], 'userid' => $params['rateduserid']), '*', MUST_EXIST);
    $discussion = $DB->get_record('hsuforum_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
    $forum = $DB->get_record('hsuforum', array('id' => $discussion->forum), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('hsuforum', $forum->id, $course->id , false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // Make sure the context provided is the context of the forum
    if ($context->id != $params['context']->id) {
        throw new rating_exception('invalidcontext');
    }

    if ($forum->scale != $params['scaleid']) {
        //the scale being submitted doesnt match the one in the database
        throw new rating_exception('invalidscaleid');
    }

    // check the item we're rating was created in the assessable time window
    if (!empty($forum->assesstimestart) && !empty($forum->assesstimefinish)) {
        if ($post->created < $forum->assesstimestart || $post->created > $forum->assesstimefinish) {
            throw new rating_exception('notavailable');
        }
    }

    //check that the submitted rating is valid for the scale

    // lower limit
    if ($params['rating'] < 0  && $params['rating'] != RATING_UNSET_RATING) {
        throw new rating_exception('invalidnum');
    }

    // upper limit
    if ($forum->scale < 0) {
        //its a custom scale
        $scalerecord = $DB->get_record('scale', array('id' => -$forum->scale));
        if ($scalerecord) {
            $scalearray = explode(',', $scalerecord->scale);
            if ($params['rating'] > count($scalearray)) {
                throw new rating_exception('invalidnum');
            }
        } else {
            throw new rating_exception('invalidscaleid');
        }
    } else if ($params['rating'] > $forum->scale) {
        //if its numeric and submitted rating is above maximum
        throw new rating_exception('invalidnum');
    }

    // Make sure groups allow this user to see the item they're rating
    if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
        if (!groups_group_exists($discussion->groupid)) { // Can't find group
            throw new rating_exception('cannotfindgroup');//something is wrong
        }

        if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
            // do not allow rating of posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
            throw new rating_exception('notmemberofgroup');
        }
    }

    // perform some final capability checks
    if (!hsuforum_user_can_see_post($forum, $discussion, $post, $USER, $cm)) {
        throw new rating_exception('nopermissiontorate');
    }

    return true;
}


/**
 * This function prints the overview of a discussion in the forum listing.
 * It needs some discussion information and some post information, these
 * happen to be combined for efficiency in the $post parameter by the function
 * that calls this one: hsuforum_print_latest_discussions()
 *
 * @global object
 * @global object
 * @param object $post The post object (passed by reference for speed).
 * @param object $forum The forum object.
 * @param int $group Current group.
 * @param string $datestring Format to use for the dates.
 * @param boolean $cantrack Is tracking enabled for this forum.
 * @param boolean $forumtracked Is the user tracking this forum.
 * @param boolean $canviewparticipants True if user has the viewparticipants permission for this course
 */
function hsuforum_print_discussion_header(&$post, $forum, $group=-1, $datestring="",
                                        $cantrack=true, $forumtracked=true, $canviewparticipants=true, $modcontext=NULL) {

    global $USER, $CFG, $OUTPUT, $PAGE;

    static $rowcount;
    static $strmarkalldread;

    if (empty($modcontext)) {
        if (!$cm = get_coursemodule_from_instance('hsuforum', $forum->id, $forum->course)) {
            print_error('invalidcoursemodule');
        }
        $modcontext = context_module::instance($cm->id);
    }

    /** @var $renderer mod_hsuforum_renderer */
    $renderer = $PAGE->get_renderer('mod_hsuforum');

    if (!isset($rowcount)) {
        $rowcount = 0;
        $strmarkalldread = get_string('markalldread', 'hsuforum');
    } else {
        $rowcount = ($rowcount + 1) % 2;
    }

    $post->subject = format_string($post->subject,true);

    echo "\n\n";
    echo '<tr class="discussion r'.$rowcount.'">';

    // Topic
    echo '<td class="topic starter">';
    echo '<a href="'.$CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.$post->discussion.'">'.$post->subject.'</a>';
    echo "</td>\n";

    // Picture
    $postuser = new stdClass();
    $postuserfields = explode(',', user_picture::fields());
    $postuser = username_load_fields_from_object($postuser, $post, null, $postuserfields);
    $postuser->id = $post->userid;

    $postuser = hsuforum_anonymize_user($postuser, $forum, $post);

    echo '<td class="picture">';
    echo $OUTPUT->user_picture($postuser, array('courseid'=>$forum->course, 'link' => (!hsuforum_is_anonymous_user($postuser))));
    echo "</td>\n";

    // User name
    $fullname = fullname($postuser, has_capability('moodle/site:viewfullnames', $modcontext));
    echo '<td class="author">';
    if (!hsuforum_is_anonymous_user($postuser)) {
        if (has_capability('moodle/course:manageactivities', $modcontext, $postuser->id)) {
            $fullname = html_writer::tag('span', $fullname, array('class' => 'hsuforum_highlightposter'));
        }
        echo '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$postuser->id.'&amp;course='.$forum->course.'">'.$fullname.'</a>';
    } else {
        echo $fullname;
    }
    echo $renderer->post_flags($post, $modcontext);
    echo "</td>\n";

    // Group picture
    if ($group !== -1) {  // Groups are active - group is a group data object or NULL
        echo '<td class="picture group">';
        if (!empty($group->picture) and empty($group->hidepicture)) {
            print_group_picture($group, $forum->course, false, false, true);
        } else if (isset($group->id)) {
            if($canviewparticipants) {
                echo '<a href="'.$CFG->wwwroot.'/user/index.php?id='.$forum->course.'&amp;group='.$group->id.'">'.$group->name.'</a>';
            } else {
                echo $group->name;
            }
        }
        echo "</td>\n";
    }

    if (has_capability('mod/hsuforum:viewdiscussion', $modcontext)) {   // Show the column with replies
        echo '<td class="replies">';
        echo '<a href="'.$CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.$post->discussion.'">';
        echo $post->replies.'</a>';
        echo "</td>\n";

        if ($cantrack) {
            echo '<td class="replies">';
            if ($forumtracked) {
                if ($post->unread > 0) {
                    echo '<span class="unread">';
                    echo '<a href="'.$CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.$post->discussion.'#unread">';
                    echo $post->unread;
                    echo '</a>';
                    echo '<a title="'.$strmarkalldread.'" href="'.$CFG->wwwroot.'/mod/hsuforum/markposts.php?f='.
                         $forum->id.'&amp;d='.$post->discussion.'&amp;mark=read&amp;returnpage=view.php">' .
                         '<img src="'.$OUTPUT->pix_url('t/markasread') . '" class="iconsmall" alt="'.$strmarkalldread.'" /></a>';
                    echo '</span>';
                } else {
                    echo '<span class="read">';
                    echo $post->unread;
                    echo '</span>';
                }
            } else {
                echo '<span class="read">';
                echo '-';
                echo '</span>';
            }
            echo "</td>\n";
        }
    }

    require_once(__DIR__.'/lib/discussion/subscribe.php');
    $subscribe = new hsuforum_lib_discussion_subscribe($forum, $modcontext);
    if ($link = $renderer->discussion_subscribe_link($post, $subscribe)) {
        echo html_writer::tag('td', $link, array('class' => 'subscribe'));
    }

    echo '<td class="lastpost">';
    $usedate = (empty($post->timemodified)) ? $post->modified : $post->timemodified;  // Just in case
    $parenturl = (empty($post->lastpostid)) ? '' : '&amp;parent='.$post->lastpostid;
    $usermodified = new stdClass();
    $usermodified->id = $post->usermodified;
    $usermodified = username_load_fields_from_object($usermodified, $post, 'um');

    $lastpost = new stdClass;
    $lastpost->id = $post->lastpostid;
    $lastpost->userid = $post->usermodified;
    $lastpost->reveal = is_null($post->umreveal) ? $post->reveal : $post->umreveal;

    $usermodified = hsuforum_anonymize_user($usermodified, $forum, $lastpost);

    if (!hsuforum_is_anonymous_user($usermodified)) {
        echo '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$post->usermodified.'&amp;course='.$forum->course.'">'.
            fullname($usermodified).'</a><br />';
    } else {
        echo fullname($usermodified).'<br />';
    }
    echo '<a href="'.$CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.$post->discussion.$parenturl.'">'.
          userdate($usedate, $datestring).'</a>';
    echo "</td>\n";

    echo "</tr>\n\n";

}

/**
 * This function is now deprecated. Use shorten_text($message, $CFG->hsuforum_shortpost) instead.
 *
 * Given a post object that we already know has a long message
 * this function truncates the message nicely to the first
 * sane place between $CFG->hsuforum_longpost and $CFG->hsuforum_shortpost
 *
 * @deprecated since Moodle 2.6
 * @see shorten_text()
 * @todo finalise deprecation in 2.8 in MDL-40851
 * @global object
 * @param string $message
 * @return string
 */
function hsuforum_shorten_post($message) {
   global $CFG;
   debugging('hsuforum_shorten_post() is deprecated since Moodle 2.6. Please use shorten_text($message, $CFG->hsuforum_shortpost) instead.', DEBUG_DEVELOPER);
   return shorten_text($message, $CFG->hsuforum_shortpost);
}

/**
 * @global object
 * @param object $course
 * @param string $search
 * @return string
 */
function hsuforum_search_form($course, $search='') {
    global $CFG, $OUTPUT;

    $output  = '<div class="forumsearch">';
    $output .= '<form action="'.$CFG->wwwroot.'/mod/hsuforum/search.php" style="display:inline">';
    $output .= '<fieldset class="invisiblefieldset">';
    $output .= '<legend class="accesshide">'.get_string('searchforums', 'hsuforum').'</legend>';
    $output .= $OUTPUT->help_icon('search');
    $output .= '<label class="accesshide" for="search" >'.get_string('search', 'hsuforum').'</label>';
    $output .= '<input id="search" name="search" type="text" size="18" value="'.s($search, true).'" alt="search" />';
    // $output .= '<label class="accesshide" for="searchforums" >'.get_string('searchforums', 'hsuforum').'</label>'; Mark - removed, redundant
    $output .= '<input id="searchforums" value="'.get_string('searchforums', 'hsuforum').'" type="submit" />';
    $output .= '<input name="id" type="hidden" value="'.$course->id.'" />';
    $output .= '</fieldset>';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
}


/**
 * @global object
 * @global object
 */
function hsuforum_set_return() {
    global $CFG, $SESSION;

    if (! isset($SESSION->fromdiscussion)) {
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
        } else {
            $referer = "";
        }
        // If the referer is NOT a login screen then save it.
        if (! strncasecmp("$CFG->wwwroot/login", $referer, 300)) {
            $SESSION->fromdiscussion = $_SERVER["HTTP_REFERER"];
        }
    }
}


/**
 * @global object
 * @param string $default
 * @return string
 */
function hsuforum_go_back_to($default) {
    global $SESSION;

    if (!empty($SESSION->fromdiscussion)) {
        $returnto = $SESSION->fromdiscussion;
        unset($SESSION->fromdiscussion);
        return $returnto;
    } else {
        return $default;
    }
}

/**
 * Given a discussion object that is being moved to $forumto,
 * this function checks all posts in that discussion
 * for attachments, and if any are found, these are
 * moved to the new forum directory.
 *
 * @global object
 * @param object $discussion
 * @param int $forumfrom source forum id
 * @param int $forumto target forum id
 * @return bool success
 */
function hsuforum_move_attachments($discussion, $forumfrom, $forumto) {
    global $DB;

    $fs = get_file_storage();

    $newcm = get_coursemodule_from_instance('hsuforum', $forumto);
    $oldcm = get_coursemodule_from_instance('hsuforum', $forumfrom);

    $newcontext = context_module::instance($newcm->id);
    $oldcontext = context_module::instance($oldcm->id);

    // loop through all posts, better not use attachment flag ;-)
    if ($posts = $DB->get_records('hsuforum_posts', array('discussion'=>$discussion->id), '', 'id, attachment')) {
        foreach ($posts as $post) {
            $fs->move_area_files_to_new_context($oldcontext->id,
                    $newcontext->id, 'mod_hsuforum', 'post', $post->id);
            $attachmentsmoved = $fs->move_area_files_to_new_context($oldcontext->id,
                    $newcontext->id, 'mod_hsuforum', 'attachment', $post->id);
            if ($attachmentsmoved > 0 && $post->attachment != '1') {
                // Weird - let's fix it
                $post->attachment = '1';
                $DB->update_record('hsuforum_posts', $post);
            } else if ($attachmentsmoved == 0 && $post->attachment != '') {
                // Weird - let's fix it
                $post->attachment = '';
                $DB->update_record('hsuforum_posts', $post);
            }
        }
    }

    return true;
}

/**
 * Returns attachments as formated text/html optionally with separate images
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param object $cm
 * @param string $type html/text/separateimages
 * @return mixed string or array of (html text withouth images and image HTML)
 */
function hsuforum_print_attachments($post, $cm, $type) {
    global $CFG, $DB, $USER, $OUTPUT;

    if (empty($post->attachment)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!in_array($type, array('separateimages', 'html', 'text'))) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!$context = context_module::instance($cm->id)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }
    $strattachment = get_string('attachment', 'hsuforum');

    $fs = get_file_storage();

    $imagereturn = '';
    $output = '';

    $canexport = !empty($CFG->enableportfolios) && empty($forum->anonymous) && (has_capability('mod/hsuforum:exportpost', $context) || ($post->userid == $USER->id && has_capability('mod/hsuforum:exportownpost', $context)));

    if ($canexport) {
        require_once($CFG->libdir.'/portfoliolib.php');
    }

    $files = $fs->get_area_files($context->id, 'mod_hsuforum', 'attachment', $post->id, "timemodified", false);
    if ($files) {
        if ($canexport) {
            $button = new portfolio_add_button();
        }
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $mimetype = $file->get_mimetype();
            $iconimage = $OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));
            $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context->id.'/mod_hsuforum/attachment/'.$post->id.'/'.$filename);

            if ($type == 'html') {
                $output .= "<a href=\"$path\">$iconimage</a> ";
                $output .= "<a href=\"$path\">".s($filename)."</a>";
                if ($canexport) {
                    $button->set_callback_options('hsuforum_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_hsuforum');
                    $button->set_format_by_file($file);
                    $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                }
                $output .= "<br />";

            } else if ($type == 'text') {
                $output .= "$strattachment ".s($filename).":\n$path\n";

            } else { //'returnimages'
                if (in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png'))) {
                    // Image attachments don't get printed as links
                    $imagereturn .= "<br /><img src=\"$path\" alt=\"\" />";
                    if ($canexport) {
                        $button->set_callback_options('hsuforum_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_hsuforum');
                        $button->set_format_by_file($file);
                        $imagereturn .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                } else {
                    $output .= "<a href=\"$path\">$iconimage</a> ";
                    $output .= format_text("<a href=\"$path\">".s($filename)."</a>", FORMAT_HTML, array('context'=>$context));
                    if ($canexport) {
                        $button->set_callback_options('hsuforum_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_hsuforum');
                        $button->set_format_by_file($file);
                        $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                    $output .= '<br />';
                }
            }

            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir.'/plagiarismlib.php');
                $output .= plagiarism_get_links(array('userid' => $post->userid,
                    'file' => $file,
                    'cmid' => $cm->id,
                    'course' => $cm->course,
                    'hsuforum' => $cm->instance));
                $output .= '<br />';
            }
        }
    }

    if ($type !== 'separateimages') {
        return $output;

    } else {
        return array($output, $imagereturn);
    }
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Lists all browsable file areas
 *
 * @package  mod_hsuforum
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array
 */
function hsuforum_get_file_areas($course, $cm, $context) {
    return array(
        'attachment' => get_string('areaattachment', 'mod_hsuforum'),
        'post' => get_string('areapost', 'mod_hsuforum'),
    );
}

/**
 * File browsing support for forum module.
 *
 * @package  mod_hsuforum
 * @category files
 * @param stdClass $browser file browser object
 * @param stdClass $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module
 * @param stdClass $context context module
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info instance or null if not found
 */
function hsuforum_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }

    // filearea must contain a real area
    if (!isset($areas[$filearea])) {
        return null;
    }

    // Note that hsuforum_user_can_see_post() additionally allows access for parent roles
    // and it explicitly checks qanda forum type, too. One day, when we stop requiring
    // course:managefiles, we will need to extend this.
    if (!has_capability('mod/hsuforum:viewdiscussion', $context)) {
        return null;
    }

    if (is_null($itemid)) {
        require_once($CFG->dirroot.'/mod/hsuforum/locallib.php');
        return new hsuforum_file_info_container($browser, $course, $cm, $context, $areas, $filearea);
    }

    static $cached = array();
    // $cached will store last retrieved post, discussion and forum. To make sure that the cache
    // is cleared between unit tests we check if this is the same session
    if (!isset($cached['sesskey']) || $cached['sesskey'] != sesskey()) {
        $cached = array('sesskey' => sesskey());
    }

    if (isset($cached['post']) && $cached['post']->id == $itemid) {
        $post = $cached['post'];
    } else if ($post = $DB->get_record('hsuforum_posts', array('id' => $itemid))) {
        $cached['post'] = $post;
    } else {
        return null;
    }

    if (isset($cached['discussion']) && $cached['discussion']->id == $post->discussion) {
        $discussion = $cached['discussion'];
    } else if ($discussion = $DB->get_record('hsuforum_discussions', array('id' => $post->discussion))) {
        $cached['discussion'] = $discussion;
    } else {
        return null;
    }

    if (isset($cached['forum']) && $cached['forum']->id == $cm->instance) {
        $forum = $cached['forum'];
    } else if ($forum = $DB->get_record('hsuforum', array('id' => $cm->instance))) {
        $cached['forum'] = $forum;
    } else {
        return null;
    }

    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;
    if (!($storedfile = $fs->get_file($context->id, 'mod_hsuforum', $filearea, $itemid, $filepath, $filename))) {
        return null;
    }

    // Checks to see if the user can manage files or is the owner.
    // TODO MDL-33805 - Do not use userid here and move the capability check above.
    if (!has_capability('moodle/course:managefiles', $context) && $storedfile->get_userid() != $USER->id) {
        return null;
    }
    // Make sure groups allow this user to see this file
    if ($discussion->groupid > 0 && !has_capability('moodle/site:accessallgroups', $context)) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS && !groups_is_member($discussion->groupid)) {
            return null;
        }
    }

    // Make sure we're allowed to see it...
    if (!hsuforum_user_can_see_post($forum, $discussion, $post, NULL, $cm)) {
        return null;
    }

    $urlbase = $CFG->wwwroot.'/pluginfile.php';
    return new file_info_stored($browser, $context, $storedfile, $urlbase, $itemid, true, true, false, false);
}

/**
 * Serves the forum attachments. Implements needed access control ;-)
 *
 * @package  mod_hsuforum
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function hsuforum_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $areas = hsuforum_get_file_areas($course, $cm, $context);

    // Try comment area first. SC INT-4387.
    hsuforum_forum_comments_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options);

    // filearea must contain a real area
    if (!isset($areas[$filearea])) {
        return false;
    }

    $postid = (int)array_shift($args);

    if (!$post = $DB->get_record('hsuforum_posts', array('id'=>$postid))) {
        return false;
    }

    if (!$discussion = $DB->get_record('hsuforum_discussions', array('id'=>$post->discussion))) {
        return false;
    }

    if (!$forum = $DB->get_record('hsuforum', array('id'=>$cm->instance))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_hsuforum/$filearea/$postid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // Make sure groups allow this user to see this file
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS) {
            if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
                return false;
            }
        }
    }

    // Make sure we're allowed to see it...
    if (!hsuforum_user_can_see_post($forum, $discussion, $post, NULL, $cm)) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, true, $options); // download MUST be forced - security!
}

/**
 * If successful, this function returns the name of the file
 *
 * @global object
 * @param object $post is a full post record, including course and forum
 * @param object $forum
 * @param object $cm
 * @param mixed $mform
 * @param string $unused
 * @param \mod_hsuforum\upload_file $uploader
 * @return bool
 */
function hsuforum_add_attachment($post, $forum, $cm, $mform=null, $unused=null, \mod_hsuforum\upload_file $uploader = null) {
    global $DB;

    if ($uploader instanceof \mod_hsuforum\upload_file) {
        $files = $uploader->process_file_upload($post->id);
        $DB->set_field('hsuforum_posts', 'attachment', empty($files) ? 0 : 1, array('id' => $post->id));
        return true;
    }

    if (empty($mform)) {
        return false;
    }

    if (empty($post->attachments)) {
        return true;   // Nothing to do
    }

    $context = context_module::instance($cm->id);

    $info = file_get_draft_area_info($post->attachments);
    $present = ($info['filecount']>0) ? '1' : '';
    file_save_draft_area_files($post->attachments, $context->id, 'mod_hsuforum', 'attachment', $post->id,
            mod_hsuforum_post_form::attachment_options($forum));

    $DB->set_field('hsuforum_posts', 'attachment', $present, array('id'=>$post->id));

    return true;
}

/**
 * Add a new post in an existing discussion.
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param mixed $mform
 * @param string $message
 * @param \mod_hsuforum\upload_file $uploader
 * @return int
 */
function hsuforum_add_new_post($post, $mform, &$message, \mod_hsuforum\upload_file $uploader = null) {
    global $USER, $CFG, $DB;

    $discussion = $DB->get_record('hsuforum_discussions', array('id' => $post->discussion));
    $forum      = $DB->get_record('hsuforum', array('id' => $discussion->forum));
    $cm         = get_coursemodule_from_instance('hsuforum', $forum->id);
    $context    = context_module::instance($cm->id);

    $post->created    = $post->modified = time();
    $post->mailed     = HSUFORUM_MAILED_PENDING;
    $post->userid     = $USER->id;
    $post->attachment = "";

    $post->id = $DB->insert_record("hsuforum_posts", $post);
    $post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_hsuforum', 'post', $post->id,
            mod_hsuforum_post_form::editor_options($context, null), $post->message);
    $DB->set_field('hsuforum_posts', 'message', $post->message, array('id'=>$post->id));
    hsuforum_add_attachment($post, $forum, $cm, $mform, $message, $uploader);

    // Update discussion modified date
    if (empty($post->privatereply)) {
        $DB->set_field("hsuforum_discussions", "timemodified", $post->modified, array("id" => $post->discussion));
        $DB->set_field("hsuforum_discussions", "usermodified", $post->userid, array("id" => $post->discussion));
    }

    if (hsuforum_tp_can_track_forums($forum) && hsuforum_tp_is_tracked($forum)) {
        hsuforum_tp_mark_post_read($post->userid, $post, $post->forum);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    hsuforum_trigger_content_uploaded_event($post, $cm, 'hsuforum_add_new_post');

    return $post->id;
}

/**
 * Update a post
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param mixed $mform
 * @param string $message
 * @param \mod_hsuforum\upload_file $uploader
 * @return bool
 */
function hsuforum_update_post($post, $mform, &$message, \mod_hsuforum\upload_file $uploader = null) {
    global $USER, $CFG, $DB;

    $discussion = $DB->get_record('hsuforum_discussions', array('id' => $post->discussion));
    $forum      = $DB->get_record('hsuforum', array('id' => $discussion->forum));
    $cm         = get_coursemodule_from_instance('hsuforum', $forum->id);
    $context    = context_module::instance($cm->id);

    $post->modified = time();

    $DB->update_record('hsuforum_posts', $post);

    if (empty($post->privatereply)) {
        $discussion->timemodified = $post->modified; // last modified tracking
        $discussion->usermodified = $post->userid; // last modified tracking
    }

    if (!$post->parent) {   // Post is a discussion starter - update discussion title and times too
        $discussion->name      = $post->subject;
        $discussion->timestart = $post->timestart;
        $discussion->timeend   = $post->timeend;
    }
    $post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_hsuforum', 'post', $post->id,
            mod_hsuforum_post_form::editor_options($context, $post->id), $post->message);
    $DB->set_field('hsuforum_posts', 'message', $post->message, array('id'=>$post->id));

    $DB->update_record('hsuforum_discussions', $discussion);

    hsuforum_add_attachment($post, $forum, $cm, $mform, $message, $uploader);

    if (hsuforum_tp_can_track_forums($forum) && hsuforum_tp_is_tracked($forum)) {
        hsuforum_tp_mark_post_read($post->userid, $post, $post->forum);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    hsuforum_trigger_content_uploaded_event($post, $cm, 'hsuforum_update_post');

    return true;
}

/**
 * Given an object containing all the necessary data,
 * create a new discussion and return the id
 *
 * @param object $post
 * @param mixed $mform
 * @param string $unused
 * @param int $userid
 * @param \mod_hsuforum\upload_file $uploader
 * @return object
 */
function hsuforum_add_discussion($discussion, $mform=null, $unused=null, $userid=null, \mod_hsuforum\upload_file $uploader = null) {
    global $USER, $CFG, $DB;

    $timenow = time();

    if (is_null($userid)) {
        $userid = $USER->id;
    }

    // The first post is stored as a real post, and linked
    // to from the discuss entry.

    $forum = $DB->get_record('hsuforum', array('id'=>$discussion->forum));
    $cm    = get_coursemodule_from_instance('hsuforum', $forum->id);

    $post = new stdClass();
    $post->discussion    = 0;
    $post->parent        = 0;
    $post->userid        = $userid;
    $post->created       = $timenow;
    $post->modified      = $timenow;
    $post->mailed        = HSUFORUM_MAILED_PENDING;
    $post->subject       = $discussion->name;
    $post->message       = $discussion->message;
    $post->messageformat = $discussion->messageformat;
    $post->messagetrust  = $discussion->messagetrust;
    $post->attachments   = isset($discussion->attachments) ? $discussion->attachments : null;
    $post->forum         = $forum->id;     // speedup
    $post->course        = $forum->course; // speedup
    $post->mailnow       = $discussion->mailnow;

    if (!is_null($mform)) {
        $data = $mform->get_data();
        if (!empty($data->reveal)) {
            $post->reveal = 1;
        }
    }
    $post->id = $DB->insert_record("hsuforum_posts", $post);

    // TODO: Fix the calling code so that there always is a $cm when this function is called
    if (!empty($cm->id) && !empty($discussion->itemid)) {   // In "single simple discussions" this may not exist yet
        $context = context_module::instance($cm->id);
        $text = file_save_draft_area_files($discussion->itemid, $context->id, 'mod_hsuforum', 'post', $post->id,
                mod_hsuforum_post_form::editor_options($context, null), $post->message);
        $DB->set_field('hsuforum_posts', 'message', $text, array('id'=>$post->id));
    }

    // Now do the main entry for the discussion, linking to this first post

    $discussion->firstpost    = $post->id;
    $discussion->timemodified = $timenow;
    $discussion->usermodified = $post->userid;
    $discussion->userid       = $userid;

    $post->discussion = $DB->insert_record("hsuforum_discussions", $discussion);

    // Finally, set the pointer on the post.
    $DB->set_field("hsuforum_posts", "discussion", $post->discussion, array("id"=>$post->id));

    if (!empty($cm->id)) {
        hsuforum_add_attachment($post, $forum, $cm, $mform, $unused, $uploader);
    }

    if (hsuforum_tp_can_track_forums($forum) && hsuforum_tp_is_tracked($forum)) {
        hsuforum_tp_mark_post_read($post->userid, $post, $post->forum);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    if (!empty($cm->id)) {
        hsuforum_trigger_content_uploaded_event($post, $cm, 'hsuforum_add_discussion');
    }

    return $post->discussion;
}

/**
 * Verify and delete the post.  The post can be a discussion post.
 *
 * @param object $course
 * @param object $cm
 * @param object $forum
 * @param context_module $modcontext
 * @param object $discussion
 * @param object $post
 * @return string The URL to redirect to
 */
function hsuforum_verify_and_delete_post($course, $cm, $forum, $modcontext, $discussion, $post) {
    global $CFG;

    // Check user capability to delete post.
    $timepassed = time() - $post->created;
    if (($timepassed > $CFG->maxeditingtime) && !has_capability('mod/hsuforum:deleteanypost', $modcontext)) {
        print_error("cannotdeletepost", "hsuforum",
            hsuforum_go_back_to("discuss.php?d=$post->discussion"));
    }
    if ($post->totalscore) {
        print_error('couldnotdeleteratings', 'rating',
            hsuforum_go_back_to("discuss.php?d=$post->discussion"));
    }
    if (hsuforum_count_replies($post) && !has_capability('mod/hsuforum:deleteanypost', $modcontext)) {
        print_error("couldnotdeletereplies", "hsuforum",
            hsuforum_go_back_to("discuss.php?d=$post->discussion"));
    }
    if (!$post->parent) { // post is a discussion topic as well, so delete discussion
        if ($forum->type == 'single') {
            print_error('cannnotdeletesinglediscussion', 'hsuforum',
                hsuforum_go_back_to("discuss.php?d=$post->discussion"));
        }
        hsuforum_delete_discussion($discussion, false, $course, $cm, $forum);

        add_to_log($discussion->course, "hsuforum", "delete discussion",
            "view.php?id=$cm->id", "$forum->id", $cm->id);

        return $CFG->wwwroot."/mod/hsuforum/view.php?id=$cm->id";

    }
    if (!hsuforum_delete_post($post, has_capability('mod/hsuforum:deleteanypost', $modcontext), $course, $cm, $forum)) {
        print_error('errorwhiledelete', 'hsuforum');
    }
    if ($forum->type == 'single') {
        // Single discussion forums are an exception. We show
        // the forum itself since it only has one discussion
        // thread.
        $discussionurl = "view.php?f=$forum->id";
    } else {
        $discussionurl = "discuss.php?d=$post->discussion";
    }

    add_to_log($discussion->course, "hsuforum", "delete post", $discussionurl, "$post->id", $cm->id);

    return hsuforum_go_back_to($discussionurl);
}

/**
 * Deletes a discussion and handles all associated cleanup.
 *
 * @global object
 * @param object $discussion Discussion to delete
 * @param bool $fulldelete True when deleting entire forum
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $forum Forum
 * @return bool
 */
function hsuforum_delete_discussion($discussion, $fulldelete, $course, $cm, $forum) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    $result = true;

    if ($posts = $DB->get_records("hsuforum_posts", array("discussion" => $discussion->id))) {
        foreach ($posts as $post) {
            $post->course = $discussion->course;
            $post->forum  = $discussion->forum;
            if (!hsuforum_delete_post($post, 'ignore', $course, $cm, $forum, $fulldelete)) {
                $result = false;
            }
        }
    }

    hsuforum_tp_delete_read_records(-1, -1, $discussion->id);

    if (!$DB->delete_records("hsuforum_discussions", array("id"=>$discussion->id))) {
        $result = false;
    }
    if (!$DB->delete_records('hsuforum_subscriptions_disc', array('discussion' => $discussion->id))) {
        $result = false;
    }

    // Update completion state if we are tracking completion based on number of posts
    // But don't bother when deleting whole thing
    if (!$fulldelete) {
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC &&
           ($forum->completiondiscussions || $forum->completionreplies || $forum->completionposts)) {
            $completion->update_state($cm, COMPLETION_INCOMPLETE, $discussion->userid);
        }
    }

    return $result;
}


/**
 * Deletes a single forum post.
 *
 * @global object
 * @param object $post Forum post object
 * @param mixed $children Whether to delete children. If false, returns false
 *   if there are any children (without deleting the post). If true,
 *   recursively deletes all children. If set to special value 'ignore', deletes
 *   post regardless of children (this is for use only when deleting all posts
 *   in a disussion).
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $forum Forum
 * @param bool $skipcompletion True to skip updating completion state if it
 *   would otherwise be updated, i.e. when deleting entire forum anyway.
 * @return bool
 */
function hsuforum_delete_post($post, $children, $course, $cm, $forum, $skipcompletion=false) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    $context = context_module::instance($cm->id);

    if ($children !== 'ignore' && ($childposts = $DB->get_records('hsuforum_posts', array('parent'=>$post->id)))) {
       if ($children) {
           foreach ($childposts as $childpost) {
               hsuforum_delete_post($childpost, true, $course, $cm, $forum, $skipcompletion);
           }
       } else {
           return false;
       }
    }

    //delete ratings
    require_once($CFG->dirroot.'/rating/lib.php');
    $delopt = new stdClass;
    $delopt->contextid = $context->id;
    $delopt->component = 'mod_hsuforum';
    $delopt->ratingarea = 'post';
    $delopt->itemid = $post->id;
    $rm = new rating_manager();
    $rm->delete_ratings($delopt);

    //delete attachments
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_hsuforum', 'attachment', $post->id);
    $fs->delete_area_files($context->id, 'mod_hsuforum', 'post', $post->id);

    if ($DB->delete_records("hsuforum_posts", array("id" => $post->id))) {

        hsuforum_tp_delete_read_records(-1, $post->id);

    // Just in case we are deleting the last post
        hsuforum_discussion_update_last_post($post->discussion);

        // Update completion state if we are tracking completion based on number of posts
        // But don't bother when deleting whole thing

        if (!$skipcompletion) {
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC &&
               ($forum->completiondiscussions || $forum->completionreplies || $forum->completionposts)) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE, $post->userid);
            }
        }

        return true;
    }
    return false;
}

/**
 * Sends post content to plagiarism plugin
 * @param object $post Forum post object
 * @param object $cm Course-module
 * @param string $name
 * @return bool
*/
function hsuforum_trigger_content_uploaded_event($post, $cm, $name) {
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_hsuforum', 'attachment', $post->id, "timemodified", false);
    $params = array(
        'context' => $context,
        'objectid' => $post->id,
        'other' => array(
            'content' => $post->message,
            'discussionid' => $post->discussion,
            'pathnamehashes' => array_keys($files),
            'triggeredfrom' => $name,
        )
    );
    $event = \mod_hsuforum\event\assessable_uploaded::create($params);
    $event->trigger();
    return true;
}

/**
 * @global object
 * @param object $post
 * @param bool $children
 * @return int
 */
function hsuforum_count_replies($post, $children=true) {
    global $DB, $USER;
    $count = 0;

    $select = 'parent = ? AND (privatereply = 0 OR privatereply = ? OR userid = ?)';
    $params = array($post->id, $USER->id, $USER->id);

    if ($children) {
        if ($childposts = $DB->get_records_select('hsuforum_posts', $select, $params)) {
           foreach ($childposts as $childpost) {
               $count ++;                   // For this child
               $count += hsuforum_count_replies($childpost, true);
           }
        }
    } else {
        $count += $DB->count_records_select('hsuforum_posts', $select, $params);
    }

    return $count;
}


/**
 * @global object
 * @param int $forumid
 * @param mixed $value
 * @return bool
 */
function hsuforum_forcesubscribe($forumid, $value=1) {
    global $DB;
    return $DB->set_field("hsuforum", "forcesubscribe", $value, array("id" => $forumid));
}

/**
 * @global object
 * @param object $forum
 * @return bool
 */
function hsuforum_is_forcesubscribed($forum) {
    global $DB;
    if (isset($forum->forcesubscribe)) {    // then we use that
        return ($forum->forcesubscribe == HSUFORUM_FORCESUBSCRIBE);
    } else {   // Check the database
       return ($DB->get_field('hsuforum', 'forcesubscribe', array('id' => $forum)) == HSUFORUM_FORCESUBSCRIBE);
    }
}

function hsuforum_get_forcesubscribed($forum) {
    global $DB;
    if (isset($forum->forcesubscribe)) {    // then we use that
        return $forum->forcesubscribe;
    } else {   // Check the database
        return $DB->get_field('hsuforum', 'forcesubscribe', array('id' => $forum));
    }
}

/**
 * @global object
 * @param int $userid
 * @param object $forum
 * @return bool
 */
function hsuforum_is_subscribed($userid, $forum) {
    global $DB;
    if (is_numeric($forum)) {
        $forum = $DB->get_record('hsuforum', array('id' => $forum));
    }
    // If forum is force subscribed and has allowforcesubscribe, then user is subscribed.
    $cm = get_coursemodule_from_instance('hsuforum', $forum->id);
    if (hsuforum_is_forcesubscribed($forum) && $cm &&
            has_capability('mod/hsuforum:allowforcesubscribe', context_module::instance($cm->id), $userid)) {
        return true;
    }
    return $DB->record_exists("hsuforum_subscriptions", array("userid" => $userid, "forum" => $forum->id));
}

function hsuforum_get_subscribed_forums($course) {
    global $USER, $CFG, $DB;
    $sql = "SELECT f.id
              FROM {hsuforum} f
                   LEFT JOIN {hsuforum_subscriptions} fs ON (fs.forum = f.id AND fs.userid = ?)
             WHERE f.course = ?
                   AND f.forcesubscribe <> ".HSUFORUM_DISALLOWSUBSCRIBE."
                   AND (f.forcesubscribe = ".HSUFORUM_FORCESUBSCRIBE." OR fs.id IS NOT NULL)";
    if ($subscribed = $DB->get_records_sql($sql, array($USER->id, $course->id))) {
        foreach ($subscribed as $s) {
            $subscribed[$s->id] = $s->id;
        }
        return $subscribed;
    } else {
        return array();
    }
}

/**
 * Returns an array of forums that the current user is subscribed to and is allowed to unsubscribe from
 *
 * @return array An array of unsubscribable forums
 */
function hsuforum_get_optional_subscribed_forums() {
    global $USER, $DB;

    // Get courses that $USER is enrolled in and can see
    $courses = enrol_get_my_courses();
    if (empty($courses)) {
        return array();
    }

    $courseids = array();
    foreach($courses as $course) {
        $courseids[] = $course->id;
    }
    list($coursesql, $courseparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');

    // get all forums from the user's courses that they are subscribed to and which are not set to forced
    $sql = "SELECT f.id, cm.id as cm, cm.visible
              FROM {hsuforum} f
                   JOIN {course_modules} cm ON cm.instance = f.id
                   JOIN {modules} m ON m.name = :modulename AND m.id = cm.module
                   LEFT JOIN {hsuforum_subscriptions} fs ON (fs.forum = f.id AND fs.userid = :userid)
             WHERE f.forcesubscribe <> :forcesubscribe AND fs.id IS NOT NULL
                   AND cm.course $coursesql";
    $params = array_merge($courseparams, array('modulename'=>'hsuforum', 'userid'=>$USER->id, 'forcesubscribe'=>HSUFORUM_FORCESUBSCRIBE));
    if (!$forums = $DB->get_records_sql($sql, $params)) {
        return array();
    }

    $unsubscribableforums = array(); // Array to return

    foreach($forums as $forum) {

        if (empty($forum->visible)) {
            // the forum is hidden
            $context = context_module::instance($forum->cm);
            if (!has_capability('moodle/course:viewhiddenactivities', $context)) {
                // the user can't see the hidden forum
                continue;
            }
        }

        // subscribe.php only requires 'mod/hsuforum:managesubscriptions' when
        // unsubscribing a user other than yourself so we don't require it here either

        // A check for whether the forum has subscription set to forced is built into the SQL above

        $unsubscribableforums[] = $forum;
    }

    return $unsubscribableforums;
}

/**
 * Adds user to the subscriber list
 *
 * @global object
 * @param int $userid
 * @param int $forumid
 */
function hsuforum_subscribe($userid, $forumid) {
    global $DB;

    require_once(__DIR__.'/repository/discussion.php');

    $repo = new hsuforum_repository_discussion();
    $repo->unsubscribe_all($forumid, $userid);

    if ($DB->record_exists("hsuforum_subscriptions", array("userid"=>$userid, "forum"=>$forumid))) {
        return true;
    }

    $sub = new stdClass();
    $sub->userid  = $userid;
    $sub->forum = $forumid;

    return $DB->insert_record("hsuforum_subscriptions", $sub);
}

/**
 * Removes user from the subscriber list
 *
 * @global object
 * @param int $userid
 * @param int $forumid
 */
function hsuforum_unsubscribe($userid, $forumid) {
    global $DB;

    require_once(__DIR__.'/repository/discussion.php');

    $repo = new hsuforum_repository_discussion();
    $repo->unsubscribe_all($forumid, $userid);

    return ($DB->delete_records('hsuforum_digests', array('userid' => $userid, 'forum' => $forumid))
        && $DB->delete_records('hsuforum_subscriptions', array('userid' => $userid, 'forum' => $forumid)));
}

/**
 * Given a new post, subscribes or unsubscribes as appropriate.
 * Returns some text which describes what happened.
 *
 * @global objec
 * @param object $post
 * @param object $forum
 */
function hsuforum_post_subscription($post, $forum) {

    global $USER;

    $action = '';
    $subscribed = hsuforum_is_subscribed($USER->id, $forum);

    if ($forum->forcesubscribe == HSUFORUM_FORCESUBSCRIBE) { // database ignored
        return "";

    } elseif (($forum->forcesubscribe == HSUFORUM_DISALLOWSUBSCRIBE)
        && !has_capability('moodle/course:manageactivities', context_course::instance($forum->course), $USER->id)) {
        if ($subscribed) {
            $action = 'unsubscribe'; // sanity check, following MDL-14558
        } else {
            return "";
        }

    } else { // go with the user's choice
        if (isset($post->subscribe)) {
            // no change
            if ((!empty($post->subscribe) && $subscribed)
                || (empty($post->subscribe) && !$subscribed)) {
                return "";

            } elseif (!empty($post->subscribe) && !$subscribed) {
                $action = 'subscribe';

            } elseif (empty($post->subscribe) && $subscribed) {
                $action = 'unsubscribe';
            }
        }
    }

    $info = new stdClass();
    $info->name  = fullname($USER);
    $info->forum = format_string($forum->name);

    switch ($action) {
        case 'subscribe':
            hsuforum_subscribe($USER->id, $post->forum);
            return "<p>".get_string("nowsubscribed", "hsuforum", $info)."</p>";
        case 'unsubscribe':
            hsuforum_unsubscribe($USER->id, $post->forum);
            return "<p>".get_string("nownotsubscribed", "hsuforum", $info)."</p>";
    }
}

/**
 * Generate and return the subscribe or unsubscribe link for a forum.
 *
 * @param object $forum the forum. Fields used are $forum->id and $forum->forcesubscribe.
 * @param object $context the context object for this forum.
 * @param array $messages text used for the link in its various states
 *      (subscribed, unsubscribed, forcesubscribed or cantsubscribe).
 *      Any strings not passed in are taken from the $defaultmessages array
 *      at the top of the function.
 * @param bool $cantaccessagroup
 * @param bool $fakelink
 * @param bool $backtoindex
 * @param array $subscribed_forums
 * @return string
 */
function hsuforum_get_subscribe_link($forum, $context, $messages = array(), $cantaccessagroup = false, $fakelink=true, $backtoindex=false, $subscribed_forums=null) {
    global $CFG, $USER, $PAGE, $OUTPUT;
    $defaultmessages = array(
        'subscribed' => get_string('unsubscribe', 'hsuforum'),
        'unsubscribed' => get_string('subscribe', 'hsuforum'),
        'cantaccessgroup' => get_string('no'),
        'forcesubscribed' => get_string('everyoneissubscribed', 'hsuforum'),
        'cantsubscribe' => get_string('disallowsubscribe','hsuforum')
    );
    $messages = $messages + $defaultmessages;

    if (hsuforum_is_forcesubscribed($forum)) {
        return $messages['forcesubscribed'];
    } else if ($forum->forcesubscribe == HSUFORUM_DISALLOWSUBSCRIBE && !has_capability('mod/hsuforum:managesubscriptions', $context)) {
        return $messages['cantsubscribe'];
    } else if ($cantaccessagroup) {
        return $messages['cantaccessgroup'];
    } else {
        if (!is_enrolled($context, $USER, '', true)) {
            return '';
        }
        if (is_null($subscribed_forums)) {
            $subscribed = hsuforum_is_subscribed($USER->id, $forum);
        } else {
            $subscribed = !empty($subscribed_forums[$forum->id]);
        }
        if ($subscribed) {
            $linktext = $messages['subscribed'];
            $linktitle = get_string('subscribestop', 'hsuforum');
        } else {
            $linktext = $messages['unsubscribed'];
            $linktitle = get_string('subscribestart', 'hsuforum');
        }

        $options = array();
        if ($backtoindex) {
            $backtoindexlink = '&amp;backtoindex=1';
            $options['backtoindex'] = 1;
        } else {
            $backtoindexlink = '';
        }
        $link = '';

        if ($fakelink) {
            $PAGE->requires->js('/mod/hsuforum/forum.js');
            $PAGE->requires->js_function_call('hsuforum_produce_subscribe_link', array($forum->id, $backtoindexlink, $linktext, $linktitle));
            $link = "<noscript>";
        }
        $options['id'] = $forum->id;
        $options['sesskey'] = sesskey();
        $url = new moodle_url('/mod/hsuforum/subscribe.php', $options);
        $link .= $OUTPUT->single_button($url, $linktext, 'get', array('title'=>$linktitle));
        if ($fakelink) {
            $link .= '</noscript>';
        }

        return $link;
    }
}


/**
 * Generate and return the track or no track link for a forum.
 *
 * @global object
 * @global object
 * @global object
 * @param object $forum the forum. Fields used are $forum->id and $forum->forcesubscribe.
 * @param array $messages
 * @param bool $fakelink
 * @return string
 */
function hsuforum_get_tracking_link($forum, $messages=array(), $fakelink=true) {
    global $CFG, $USER, $PAGE, $OUTPUT;

    static $strnotrackforum, $strtrackforum;

    if (isset($messages['trackforum'])) {
         $strtrackforum = $messages['trackforum'];
    }
    if (isset($messages['notrackforum'])) {
         $strnotrackforum = $messages['notrackforum'];
    }
    if (empty($strtrackforum)) {
        $strtrackforum = get_string('trackforum', 'hsuforum');
    }
    if (empty($strnotrackforum)) {
        $strnotrackforum = get_string('notrackforum', 'hsuforum');
    }

    if (hsuforum_tp_is_tracked($forum)) {
        $linktitle = $strnotrackforum;
        $linktext = $strnotrackforum;
    } else {
        $linktitle = $strtrackforum;
        $linktext = $strtrackforum;
    }

    $link = '';
    if ($fakelink) {
        $PAGE->requires->js('/mod/hsuforum/forum.js');
        $PAGE->requires->js_function_call('hsuforum_produce_tracking_link', Array($forum->id, $linktext, $linktitle));
        // use <noscript> to print button in case javascript is not enabled
        $link .= '<noscript>';
    }
    $url = new moodle_url('/mod/hsuforum/settracking.php', array('id'=>$forum->id));
    $link .= $OUTPUT->single_button($url, $linktext, 'get', array('title'=>$linktitle));

    if ($fakelink) {
        $link .= '</noscript>';
    }

    return $link;
}



/**
 * Returns true if user created new discussion already
 *
 * @global object
 * @global object
 * @param int $forumid
 * @param int $userid
 * @return bool
 */
function hsuforum_user_has_posted_discussion($forumid, $userid) {
    global $CFG, $DB;

    $sql = "SELECT 'x'
              FROM {hsuforum_discussions} d, {hsuforum_posts} p
             WHERE d.forum = ? AND p.discussion = d.id AND p.parent = 0 and p.userid = ?";

    return $DB->record_exists_sql($sql, array($forumid, $userid));
}

/**
 * @global object
 * @global object
 * @param int $forumid
 * @param int $userid
 * @return array
 */
function hsuforum_discussions_user_has_posted_in($forumid, $userid) {
    global $CFG, $DB;

    $haspostedsql = "SELECT DISTINCT d.id AS id,
                            d.*
                       FROM {hsuforum_posts} p,
                            {hsuforum_discussions} d
                      WHERE p.discussion = d.id
                        AND d.forum = ?
                        AND p.userid = ?";

    return $DB->get_records_sql($haspostedsql, array($forumid, $userid));
}

/**
 * @global object
 * @global object
 * @param int $forumid
 * @param int $did
 * @param int $userid
 * @return bool
 */
function hsuforum_user_has_posted($forumid, $did, $userid) {
    global $DB;

    if (empty($did)) {
        // posted in any forum discussion?
        $sql = "SELECT 'x'
                  FROM {hsuforum_posts} p
                  JOIN {hsuforum_discussions} d ON d.id = p.discussion
                 WHERE p.userid = :userid AND d.forum = :forumid";
        return $DB->record_exists_sql($sql, array('forumid'=>$forumid,'userid'=>$userid));
    } else {
        return $DB->record_exists('hsuforum_posts', array('discussion'=>$did,'userid'=>$userid));
    }
}

/**
 * Returns creation time of the first user's post in given discussion
 * @global object $DB
 * @param int $did Discussion id
 * @param int $userid User id
 * @return int|bool post creation time stamp or return false
 */
function hsuforum_get_user_posted_time($did, $userid) {
    global $DB;

    $posttime = $DB->get_field('hsuforum_posts', 'MIN(created)', array('userid'=>$userid, 'discussion'=>$did));
    if (empty($posttime)) {
        return false;
    }
    return $posttime;
}

/**
 * @global object
 * @param object $forum
 * @param object $currentgroup
 * @param int $unused
 * @param object $cm
 * @param object $context
 * @return bool
 */
function hsuforum_user_can_post_discussion($forum, $currentgroup=null, $unused=-1, $cm=NULL, $context=NULL) {
// $forum is an object
    global $USER;

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser() or !isloggedin()) {
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('hsuforum', $forum->id, $forum->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm->id);
    }

    if ($currentgroup === null) {
        $currentgroup = groups_get_activity_group($cm);
    }

    $groupmode = groups_get_activity_groupmode($cm);

    if ($forum->type == 'news') {
        $capname = 'mod/hsuforum:addnews';
    } else if ($forum->type == 'qanda') {
        $capname = 'mod/hsuforum:addquestion';
    } else {
        $capname = 'mod/hsuforum:startdiscussion';
    }

    if (!has_capability($capname, $context)) {
        return false;
    }

    if ($forum->type == 'single') {
        return false;
    }

    if ($forum->type == 'eachuser') {
        if (hsuforum_user_has_posted_discussion($forum->id, $USER->id)) {
            return false;
        }
    }

    if (!$groupmode or has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($currentgroup) {
        return groups_is_member($currentgroup);
    } else {
        // no group membership and no accessallgroups means no new discussions
        // reverted to 1.7 behaviour in 1.9+,  buggy in 1.8.0-1.9.0
        return false;
    }
}

/**
 * This function checks whether the user can reply to posts in a forum
 * discussion. Use hsuforum_user_can_post_discussion() to check whether the user
 * can start discussions.
 *
 * @global object
 * @global object
 * @uses DEBUG_DEVELOPER
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $forum forum object
 * @param object $discussion
 * @param object $user
 * @param object $cm
 * @param object $course
 * @param object $context
 * @return bool
 */
function hsuforum_user_can_post($forum, $discussion, $user=NULL, $cm=NULL, $course=NULL, $context=NULL) {
    global $USER, $DB;
    if (empty($user)) {
        $user = $USER;
    }

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    if (!isset($discussion->groupid)) {
        debugging('incorrect discussion parameter', DEBUG_DEVELOPER);
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('hsuforum', $forum->id, $forum->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$course) {
        debugging('missing course', DEBUG_DEVELOPER);
        if (!$course = $DB->get_record('course', array('id' => $forum->course))) {
            print_error('invalidcourseid');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm->id);
    }

    // normal users with temporary guest access can not post, suspended users can not post either
    if (!is_viewing($context, $user->id) and !is_enrolled($context, $user->id, '', true)) {
        return false;
    }

    if ($forum->type == 'news') {
        $capname = 'mod/hsuforum:replynews';
    } else {
        $capname = 'mod/hsuforum:replypost';
    }

    if (!has_capability($capname, $context, $user->id)) {
        return false;
    }

    if (!$groupmode = groups_get_activity_groupmode($cm, $course)) {
        return true;
    }

    if (has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($groupmode == VISIBLEGROUPS) {
        if ($discussion->groupid == -1) {
            // allow students to reply to all participants discussions - this was not possible in Moodle <1.8
            return true;
        }
        return groups_is_member($discussion->groupid);

    } else {
        //separate groups
        if ($discussion->groupid == -1) {
            return false;
        }
        return groups_is_member($discussion->groupid);
    }
}

/**
 * Checks to see if a user can view a particular post.
 *
 * @deprecated since Moodle 2.4 use hsuforum_user_can_see_post() instead
 *
 * @param object $post
 * @param object $course
 * @param object $cm
 * @param object $forum
 * @param object $discussion
 * @param object $user
 * @return boolean
 */
function hsuforum_user_can_view_post($post, $course, $cm, $forum, $discussion, $user=null){
    debugging('hsuforum_user_can_view_post() is deprecated. Please use hsuforum_user_can_see_post() instead.', DEBUG_DEVELOPER);
    return hsuforum_user_can_see_post($forum, $discussion, $post, $user, $cm);
}

/**
* Check to ensure a user can view a timed discussion.
*
* @param object $discussion
* @param object $user
* @param object $context
* @return boolean returns true if they can view post, false otherwise
*/
function hsuforum_user_can_see_timed_discussion($discussion, $user, $context) {
    global $CFG;

    // Check that the user can view a discussion that is normally hidden due to access times.
    if (!empty($CFG->hsuforum_enabletimedposts)) {
        $time = time();
        if (($discussion->timestart != 0 && $discussion->timestart > $time)
            || ($discussion->timeend != 0 && $discussion->timeend < $time)) {
            if (!has_capability('mod/hsuforum:viewhiddentimedposts', $context, $user->id)) {
                return false;
            }
        }
    }

    return true;
}

/**
* Check to ensure a user can view a group discussion.
*
* @param object $discussion
* @param object $cm
* @param object $context
* @return boolean returns true if they can view post, false otherwise
*/
function hsuforum_user_can_see_group_discussion($discussion, $cm, $context) {

    // If it's a grouped discussion, make sure the user is a member.
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == SEPARATEGROUPS) {
            return groups_is_member($discussion->groupid) || has_capability('moodle/site:accessallgroups', $context);
        }
    }

    return true;
}

/**
 * @global object
 * @global object
 * @uses DEBUG_DEVELOPER
 * @param object $forum
 * @param object $discussion
 * @param object $context
 * @param object $user
 * @return bool
 */
function hsuforum_user_can_see_discussion($forum, $discussion, $context, $user=NULL) {
    global $USER, $DB;

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    // retrieve objects (yuk)
    if (is_numeric($forum)) {
        debugging('missing full forum', DEBUG_DEVELOPER);
        if (!$forum = $DB->get_record('hsuforum',array('id'=>$forum))) {
            return false;
        }
    }
    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('hsuforum_discussions',array('id'=>$discussion))) {
            return false;
        }
    }
    if (!$cm = get_coursemodule_from_instance('hsuforum', $forum->id, $forum->course)) {
        print_error('invalidcoursemodule');
    }

    if (!has_capability('mod/hsuforum:viewdiscussion', $context)) {
        return false;
    }

    if (!hsuforum_user_can_see_timed_discussion($discussion, $user, $context)) {
        return false;
    }

    if (!hsuforum_user_can_see_group_discussion($discussion, $cm, $context)) {
        return false;
    }

    if ($forum->type == 'qanda' &&
            !hsuforum_user_has_posted($forum->id, $discussion->id, $user->id) &&
            !has_capability('mod/hsuforum:viewqandawithoutposting', $context)) {
        return false;
    }
    return true;
}

/**
 * @global object
 * @global object
 * @param object $forum
 * @param object $discussion
 * @param object $post
 * @param object $user
 * @param object $cm
 * @return bool
 */
function hsuforum_user_can_see_post($forum, $discussion, $post, $user=NULL, $cm=NULL) {
    global $CFG, $USER, $DB;

    // Context used throughout function.
    $modcontext = context_module::instance($cm->id);

    // retrieve objects (yuk)
    if (is_numeric($forum)) {
        debugging('missing full forum', DEBUG_DEVELOPER);
        if (!$forum = $DB->get_record('hsuforum',array('id'=>$forum))) {
            return false;
        }
    }

    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('hsuforum_discussions',array('id'=>$discussion))) {
            return false;
        }
    }
    if (is_numeric($post)) {
        debugging('missing full post', DEBUG_DEVELOPER);
        if (!$post = $DB->get_record('hsuforum_posts',array('id'=>$post))) {
            return false;
        }
    }

    if (!isset($post->id) && isset($post->parent)) {
        $post->id = $post->parent;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('hsuforum', $forum->id, $forum->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    $canviewdiscussion = (isset($cm->cache) && !empty($cm->cache->caps['mod/hsuforum:viewdiscussion'])) || has_capability('mod/hsuforum:viewdiscussion', $modcontext, $user->id);
    if (!$canviewdiscussion && !has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), context_user::instance($post->userid))) {
        return false;
    }

    if (isset($cm->uservisible)) {
        if (!$cm->uservisible) {
            return false;
        }
    } else {
        if (!coursemodule_visible_for_user($cm, $user->id)) {
            return false;
        }
    }

    if (!hsuforum_user_can_see_timed_discussion($discussion, $user, $modcontext)) {
        return false;
    }

    if (!hsuforum_user_can_see_group_discussion($discussion, $cm, $modcontext)) {
        return false;
    }

    if (!property_exists($post, 'privatereply')) {
        throw new coding_exception('Must set post\'s privatereply property!');
    }
    if (!empty($post->privatereply)) {
        if ($post->userid != $user->id and $post->privatereply != $user->id) {
            return false;
        }
    }

    if ($forum->type == 'qanda') {
        $firstpost = hsuforum_get_firstpost_from_discussion($discussion->id);
        $userfirstpost = hsuforum_get_user_posted_time($discussion->id, $user->id);

        return (($userfirstpost !== false && (time() - $userfirstpost >= $CFG->maxeditingtime)) ||
                $firstpost->id == $post->id || $post->userid == $user->id || $firstpost->userid == $user->id ||
                has_capability('mod/hsuforum:viewqandawithoutposting', $modcontext, $user->id));
    }
    return true;
}


/**
 * Prints the discussion view screen for a forum.
 *
 * @global object
 * @global object
 * @param object $course The current course object.
 * @param object $forum Forum to be printed.
 * @param int $maxdiscussions .
 * @param string $displayformat The display format to use (optional).
 * @param string $sort Sort arguments for database query (optional).
 * @param int $groupmode Group mode of the forum (optional).
 * @param void $unused (originally current group)
 * @param int $page Page mode, page to display (optional).
 * @param int $perpage The maximum number of discussions per page(optional)
 *
 */
function hsuforum_print_latest_discussions($course, $forum, $maxdiscussions=-1, $displayformat='plain', $sort='',
                                        $currentgroup=-1, $groupmode=-1, $page=-1, $perpage=100, $cm=NULL) {
    global $CFG, $USER, $OUTPUT, $PAGE;

    require_once($CFG->dirroot.'/rating/lib.php');

    if (!$cm) {
        if (!$cm = get_coursemodule_from_instance('hsuforum', $forum->id, $forum->course)) {
            print_error('invalidcoursemodule');
        }
    }
    $context = context_module::instance($cm->id);

    $showdisplayformat = false;
    if (ajaxenabled() and $displayformat == 'header') {
        $displayformat = optional_param('displayformat', '', PARAM_ALPHA);
        if (!empty($displayformat)) {
            set_user_preference('hsuforum_displayformat', $displayformat);
        } else {
            $displayformat = get_user_preferences('hsuforum_displayformat', 'header');
        }
        $showdisplayformat = true;
    }

    if (empty($sort)) {
        $sort = "d.timemodified DESC";
    }

    $olddiscussionlink = false;

 // Sort out some defaults
    if ($perpage <= 0) {
        $perpage = 0;
        $page    = -1;
    }

    if ($maxdiscussions == 0) {
        // all discussions - backwards compatibility
        $page    = -1;
        $perpage = 0;
        if ($displayformat == 'plain') {
            $displayformat = 'header';  // Abbreviate display by default
        }

    } else if ($maxdiscussions > 0) {
        $page    = -1;
        $perpage = $maxdiscussions;
    }

    /** @var $renderer mod_hsuforum_renderer|\mod_hsuforum\render_interface */
    if ($displayformat == 'article') {
        $renderer = $PAGE->get_renderer('mod_hsuforum', 'article');
    } else {
        $renderer = $PAGE->get_renderer('mod_hsuforum');
    }

    $fullpost = false;
    if (in_array($displayformat, array('plain', 'nested', 'article'))) {
        $fullpost = true;
    }


// Decide if current user is allowed to see ALL the current discussions or not

// First check the group stuff
    if ($currentgroup == -1 or $groupmode == -1) {
        $groupmode    = groups_get_activity_groupmode($cm, $course);
        $currentgroup = groups_get_activity_group($cm);
    }

    $groups = array(); //cache

// If the user can post discussions, then this is a good place to put the
// button for it. We do not show the button if we are showing site news
// and the current user is a guest.

    $canstart = hsuforum_user_can_post_discussion($forum, $currentgroup, $groupmode, $cm, $context);
    if (!$canstart and $forum->type !== 'news') {
        if (isguestuser() or !isloggedin()) {
            $canstart = true;
        }
        if (!is_enrolled($context) and !is_viewing($context)) {
            // allow guests and not-logged-in to see the button - they are prompted to log in after clicking the link
            // normal users with temporary guest access see this button too, they are asked to enrol instead
            // do not show the button to users with suspended enrolments here
            $canstart = enrol_selfenrol_available($course->id);
        }
    }

    // Get all the recent discussions we're allowed to see
    $getuserlastmodified = in_array($displayformat, array('header', 'nested', 'tree', 'article'));
    $discussions = hsuforum_get_discussions($cm, $sort, $fullpost, $maxdiscussions, $getuserlastmodified, $page, $perpage, false);

    // If we want paging
    $numdiscussions = null;
    if ($page != -1) {
        // Get the number of discussions found.
        $numdiscussions = hsuforum_get_discussions_count($cm);
    } else {
        if ($maxdiscussions > 0 and $maxdiscussions <= count($discussions)) {
            $olddiscussionlink = true;
        }
    }

    if ($showdisplayformat) {
        if ($displayformat != 'article') {
            $url = clone($PAGE->url);
            $url->param('displayformat', 'article');
            echo html_writer::link($url, get_string('switchtoaccessible', 'hsuforum'), array('class' => 'accesshide'));
        }
        $display = new single_select($PAGE->url, 'displayformat', array(
            'header'  => get_string('default', 'hsuforum'),
            'tree'    => get_string('tree', 'hsuforum'),
            'nested'  => get_string('nested', 'hsuforum'),
            'article' => get_string('accessible', 'hsuforum'),
        ), $displayformat, array(), 'displayformatid');

        $display->set_label(get_string('discussiondisplay', 'hsuforum'));
        $display->class .= ' hsuforum-display-format clearfix';
        echo $OUTPUT->render($display);
    }

    if (!$canstart && (isguestuser() or !isloggedin() or $forum->type == 'news')) {
        // no button and no info
    } else if (!$canstart && $groupmode && has_capability('mod/hsuforum:startdiscussion', $context)) {
        // inform users why they can not post new discussion
        $message = ($currentgroup) ? 'cannotadddiscussion' : 'cannotadddiscussionall';
        echo $OUTPUT->notification(get_string($message, 'hsuforum'), 'notifyproblem hsuforum-cannot-post');
    }

    echo $OUTPUT->container_start('clearfix');

    if (!is_null($numdiscussions)) {
        echo html_writer::tag('h3', get_string('xdiscussions', 'hsuforum', $numdiscussions),
            array('class' => 'hsuforum-discussion-count', 'data-count' => $numdiscussions, 'tabindex' => '-1'));
    }

    if ($canstart) {
        echo '<div class="singlebutton forumaddnew hsuforum-add-discussion">';
        echo "<form id=\"newdiscussionform\" method=\"get\" action=\"$CFG->wwwroot/mod/hsuforum/post.php\">";
        echo '<div>';
        echo "<input type=\"hidden\" name=\"forum\" value=\"$forum->id\" />";
        switch ($forum->type) {
            case 'news':
            case 'blog':
                $buttonadd = get_string('addanewtopic', 'hsuforum');
                break;
            case 'qanda':
                $buttonadd = get_string('addanewquestion', 'hsuforum');
                break;
            default:
                $buttonadd = get_string('addanewdiscussion', 'hsuforum');
                break;
        }
        echo '<input type="submit" value="'.$buttonadd.'" />';
        echo '</div>';
        echo '</form>';
        echo "</div>\n";
    }

    echo $OUTPUT->container_end();
    echo $OUTPUT->container_start('hsuforum-control-options clearfix');

    groups_print_activity_menu($cm, $PAGE->url);

    if ($forum->type != 'single') {
        require_once(__DIR__.'/lib/discussion/sort.php');
        $dsort = hsuforum_lib_discussion_sort::get_from_session($forum, $context);
        $dsort->set_key(optional_param('dsortkey', $dsort->get_key(), PARAM_ALPHA));
        $dsort->set_direction(optional_param('dsortdirection', $dsort->get_direction(), PARAM_ALPHA));
        hsuforum_lib_discussion_sort::set_to_session($dsort);
        echo $renderer->discussion_sorting($dsort);
    }

    echo $OUTPUT->container_end();

    if (!$discussions) {
        echo '<div class="forumnodiscuss">';
        if ($forum->type == 'news') {
            echo '('.get_string('nonews', 'hsuforum').')';
        } else if ($forum->type == 'qanda') {
            echo '('.get_string('noquestions','hsuforum').')';
        } else {
            echo '('.get_string('nodiscussions', 'hsuforum').')';
        }
        echo "</div>\n";

        if ($displayformat == 'article') {
            // Load these incase the user adds a new discussion.
            $PAGE->requires->js_init_call('M.mod_hsuforum.init_flags', null, false, $renderer->get_js_module());
            $PAGE->requires->js_init_call('M.mod_hsuforum.init_subscribe', null, false, $renderer->get_js_module());

            echo $OUTPUT->box_start('mod_hsuforum_posts_container article');
            echo $renderer->discussions($cm, array(), array(
                'total'   => 0,
                'page'    => $page,
                'perpage' => $perpage,
            ));
            echo $OUTPUT->box_end();
        }
        return;
    }

    if (in_array($displayformat, array('nested', 'article')) and $forum->assessed != RATING_AGGREGATE_NONE) {
        $ratingoptions = new stdClass;
        $ratingoptions->context = $context;
        $ratingoptions->component = 'mod_hsuforum';
        $ratingoptions->ratingarea = 'post';
        $ratingoptions->items = $discussions;
        $ratingoptions->aggregate = $forum->assessed;
        $ratingoptions->scaleid = $forum->scale;
        $ratingoptions->userid = $USER->id;
        $ratingoptions->returnurl = "$CFG->wwwroot/mod/hsuforum/view.php?id=$cm->id";
        $ratingoptions->assesstimestart = $forum->assesstimestart;
        $ratingoptions->assesstimefinish = $forum->assesstimefinish;

        $rm = new rating_manager();
        $discussions = $rm->get_ratings($ratingoptions);
    }

    // Show the paging bar.
    if (!is_null($numdiscussions) && $displayformat != 'article') {
        echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$forum->id");
    }

    $canviewparticipants = has_capability('moodle/course:viewparticipants',$context);

    $strdatestring = get_string('strftimerecentfull');

    // Check if the forum is tracked.
    if ($cantrack = hsuforum_tp_can_track_forums($forum)) {
        $forumtracked = hsuforum_tp_is_tracked($forum);
    } else {
        $forumtracked = false;
    }

    echo $OUTPUT->box_start("mod_hsuforum_posts_container $displayformat");

    if ($displayformat == 'header') {
        echo html_writer::start_tag('table', array(
            'cellspacing' => '0',
            'class' => 'forumheaderlist',
            'summary' => get_string('discussionsummary', 'hsuforum', format_string($forum->name)),
        ));
        echo '<thead>';
        echo '<tr>';
        echo '<th class="header topic" scope="col">'.get_string('discussion', 'hsuforum').'</th>';
        echo '<th class="header author" colspan="2" scope="col">'.get_string('startedby', 'hsuforum').'</th>';
        if ($groupmode > 0) {
            echo '<th class="header group" scope="col">'.get_string('group').'</th>';
        }
        if (has_capability('mod/hsuforum:viewdiscussion', $context)) {
            echo '<th class="header replies" scope="col">'.get_string('replies', 'hsuforum').'</th>';
            // If the forum can be tracked, display the unread column.
            if ($cantrack) {
                echo '<th class="header replies" scope="col">'.get_string('unread', 'hsuforum');
                if ($forumtracked) {
                    echo '<a title="'.get_string('markallread', 'hsuforum').
                         '" href="'.$CFG->wwwroot.'/mod/hsuforum/markposts.php?f='.
                         $forum->id.'&amp;mark=read&amp;returnpage=view.php">'.
                         '<img src="'.$OUTPUT->pix_url('t/markasread') . '" class="iconsmall" alt="'.get_string('markallread', 'hsuforum').'" /></a>';
                }
                echo '</th>';
            }
        }
        require_once(__DIR__.'/lib/discussion/subscribe.php');
        $subscribe = new hsuforum_lib_discussion_subscribe($forum, $context);
        if ($subscribe->can_subscribe()) {
            echo '<th class="header subscribed" scope="col">'.get_string('subscribed', 'hsuforum').'</th>';
        }
        echo '<th class="header lastpost" scope="col">'.get_string('lastpost', 'hsuforum').'</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
    }

    // Can be used by some output formats.
    $discussionlist = array();

    foreach ($discussions as $discussion) {
        if (empty($discussion->replies)) {
            $discussion->replies = 0;
        }
        if (empty($discussion->lastpostid)) {
            $discussion->lastpostid = 0;
        }

        // SPECIAL CASE: The front page can display a news item post to non-logged in users.
        // All posts are read in this case.
        if (!$forumtracked) {
            $discussion->unread = '-';
        } else if (empty($USER)) {
            $discussion->unread = 0;
        } else if (empty($discussion->unread)) {
            $discussion->unread = 0;
        }

        if (isloggedin()) {
            $ownpost = ($discussion->userid == $USER->id);
        } else {
            $ownpost=false;
        }
        // Use discussion name instead of subject of first post
        $discussion->subject = $discussion->name;

        switch ($displayformat) {
            case 'tree':
                if (empty($nodes)) {
                    $nodes = array();
                }
                if ($node = $renderer->post_to_node($cm, $discussion, $discussion, true)) {
                    $nodes[] = $node;
                }
                break;

            case 'nested':
                echo $renderer->nested_discussion($cm, $discussion);
                break;

            case 'article':
                // Seems odd right?  But $discussion is actually more like the post than the discussion record.
                $disc = hsuforum_extract_discussion($discussion, $forum);
                $discussionlist[$disc->id] = array($disc, $discussion);
                break;

            case 'header':
                if ($groupmode > 0) {
                    if (isset($groups[$discussion->groupid])) {
                        $group = $groups[$discussion->groupid];
                    } else {
                        $group = $groups[$discussion->groupid] = groups_get_group($discussion->groupid);
                    }
                } else {
                    $group = -1;
                }
                hsuforum_print_discussion_header($discussion, $forum, $group, $strdatestring, $cantrack, $forumtracked,
                    $canviewparticipants, $context);
            break;
            default:
                $link = false;

                if ($discussion->replies) {
                    $link = true;
                } else {
                    $modcontext = context_module::instance($cm->id);
                    $link = hsuforum_user_can_see_discussion($forum, $discussion, $modcontext, $USER);
                }

                $discussion->forum = $forum->id;

                hsuforum_print_post($discussion, $discussion, $forum, $cm, $course, $ownpost, 0, $link, false,
                        '', null, true, $forumtracked);
            break;
        }
    }

    if ($displayformat == "header") {
        echo '</tbody>';
        echo '</table>';
    } else if ($displayformat == 'tree') {
        echo $renderer->discussion_nodes($context, $nodes);
    } else if ($displayformat == 'article') {
        echo $renderer->discussions($cm, $discussionlist, array(
            'total'   => $numdiscussions,
            'page'    => $page,
            'perpage' => $perpage,
        ));
    } else if ($displayformat == 'nested') {
        echo html_writer::tag('noscript', $OUTPUT->notification(get_string('javascriptdisableddisplayformat', 'hsuforum')));
    }

    if ($olddiscussionlink) {
        if ($forum->type == 'news') {
            $strolder = get_string('oldertopics', 'hsuforum');
        } else {
            $strolder = get_string('olderdiscussions', 'hsuforum');
        }
        echo '<div class="forumolddiscuss">';
        echo '<a href="'.$CFG->wwwroot.'/mod/hsuforum/view.php?f='.$forum->id.'&amp;showall=1">';
        echo $strolder.'</a> ...</div>';
    }

    echo $OUTPUT->box_end(); // End mod_hsuforum_posts_container

    if ($page != -1 && $displayformat != 'article') { ///Show the paging bar
        echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$forum->id");
    }
}


/**
 * Prints a forum discussion
 *
 * @uses CONTEXT_MODULE
 * @uses HSUFORUM_MODE_FLATNEWEST
 * @uses HSUFORUM_MODE_FLATOLDEST
 * @uses HSUFORUM_MODE_THREADED
 * @uses HSUFORUM_MODE_NESTED
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $forum
 * @param stdClass $discussion
 * @param stdClass $post
 * @param int $mode
 * @param mixed $canreply
 * @param bool $canrate
 */
function hsuforum_print_discussion($course, $cm, $forum, $discussion, $post, $mode, $canreply=NULL, $canrate=false) {
    global $USER, $CFG, $OUTPUT, $PAGE;

    require_once($CFG->dirroot.'/rating/lib.php');

    $displayformat = get_user_preferences('hsuforum_displayformat', 'header');
    $ownpost = (isloggedin() && $USER->id == $post->userid);

    $modcontext = context_module::instance($cm->id);
    if ($canreply === NULL) {
        $reply = hsuforum_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext);
    } else {
        $reply = $canreply;
    }

    // $cm holds general cache for forum functions
    $cm->cache = new stdClass;
    $cm->cache->groups      = groups_get_all_groups($course->id, 0, $cm->groupingid);
    $cm->cache->usersgroups = array();

    $posters = array();

    $forumtracked = hsuforum_tp_is_tracked($forum);
    $posts = hsuforum_get_all_discussion_posts($discussion->id, hsuforum_get_layout_mode_sort($mode), $forumtracked);
    $post = $posts[$post->id];

    foreach ($posts as $pid=>$p) {
        $posters[$p->userid] = $p->userid;
    }

    // preload all groups of ppl that posted in this discussion
    if ($postersgroups = groups_get_all_groups($course->id, $posters, $cm->groupingid, 'gm.id, gm.groupid, gm.userid')) {
        foreach($postersgroups as $pg) {
            if (!isset($cm->cache->usersgroups[$pg->userid])) {
                $cm->cache->usersgroups[$pg->userid] = array();
            }
            $cm->cache->usersgroups[$pg->userid][$pg->groupid] = $pg->groupid;
        }
        unset($postersgroups);
    }

    //load ratings
    if ($forum->assessed != RATING_AGGREGATE_NONE) {
        $ratingoptions = new stdClass;
        $ratingoptions->context = $modcontext;
        $ratingoptions->component = 'mod_hsuforum';
        $ratingoptions->ratingarea = 'post';
        $ratingoptions->items = $posts;
        $ratingoptions->aggregate = $forum->assessed;//the aggregation method
        $ratingoptions->scaleid = $forum->scale;
        $ratingoptions->userid = $USER->id;
        if ($forum->type == 'single' or !$discussion->id) {
            $ratingoptions->returnurl = "$CFG->wwwroot/mod/hsuforum/view.php?id=$cm->id";
        } else {
            $ratingoptions->returnurl = "$CFG->wwwroot/mod/hsuforum/discuss.php?d=$discussion->id";
        }
        $ratingoptions->assesstimestart = $forum->assesstimestart;
        $ratingoptions->assesstimefinish = $forum->assesstimefinish;

        $rm = new rating_manager();
        $posts = $rm->get_ratings($ratingoptions);
    }


    $post->forum = $forum->id;   // Add the forum id to the post object, later used by hsuforum_print_post
    $post->forumtype = $forum->type;

    $post->subject = format_string($post->subject);

    $postread = !empty($post->postread);

    echo $OUTPUT->box_start("mod_hsuforum_posts_container $displayformat");

    if ($displayformat == 'article') {
        /** @var \mod_hsuforum\render_interface $renderer */
        $renderer = $PAGE->get_renderer('mod_hsuforum', 'article');
        echo $renderer->discussion_thread($cm, $discussion, $post, $posts, $reply);
        echo $OUTPUT->box_end(); // End mod_hsuforum_posts_container
        return;
    }

    hsuforum_print_post($post, $discussion, $forum, $cm, $course, $ownpost, $reply, false,
                         '', '', $postread, true, $forumtracked);

    switch ($mode) {
        case HSUFORUM_MODE_FLATOLDEST :
        case HSUFORUM_MODE_FLATNEWEST :
        case HSUFORUM_MODE_FLATFIRSTNAME :
        case HSUFORUM_MODE_FLATLASTNAME :
        default:
            hsuforum_print_posts_flat($course, $cm, $forum, $discussion, $post, $mode, $reply, $forumtracked, $posts);
            break;

        case HSUFORUM_MODE_THREADED :
            hsuforum_print_posts_threaded($course, $cm, $forum, $discussion, $post, 0, $reply, $forumtracked, $posts);
            break;

        case HSUFORUM_MODE_NESTED :
            hsuforum_print_posts_nested($course, $cm, $forum, $discussion, $post, $reply, $forumtracked, $posts);
            break;
    }

    echo $OUTPUT->box_end(); // End mod_hsuforum_posts_container
}


/**
 * @global object
 * @global object
 * @uses HSUFORUM_MODE_FLATNEWEST
 * @param object $course
 * @param object $cm
 * @param object $forum
 * @param object $discussion
 * @param object $post
 * @param object $mode
 * @param bool $reply
 * @param bool $forumtracked
 * @param array $posts
 * @return void
 */
function hsuforum_print_posts_flat($course, &$cm, $forum, $discussion, $post, $mode, $reply, $forumtracked, $posts) {
    global $USER, $CFG;

    $link  = false;

    foreach ($posts as $post) {
        if (!$post->parent) {
            continue;
        }
        $post->subject = format_string($post->subject);
        $ownpost = ($USER->id == $post->userid);

        $postread = !empty($post->postread);

        hsuforum_print_post($post, $discussion, $forum, $cm, $course, $ownpost, $reply, $link,
                             '', '', $postread, true, $forumtracked);
    }
}

/**
 * @todo Document this function
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @return void
 */
function hsuforum_print_posts_threaded($course, &$cm, $forum, $discussion, $parent, $depth, $reply, $forumtracked, $posts) {
    global $USER, $CFG, $PAGE;

    $link  = false;

    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;

        $modcontext       = context_module::instance($cm->id);
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $modcontext);

        foreach ($posts as $post) {

            echo '<div class="indent">';
            if ($depth > 0) {
                $ownpost = ($USER->id == $post->userid);
                $post->subject = format_string($post->subject);

                $postread = !empty($post->postread);

                hsuforum_print_post($post, $discussion, $forum, $cm, $course, $ownpost, $reply, $link,
                                     '', '', $postread, true, $forumtracked);
            } else {
                if (!hsuforum_user_can_see_post($forum, $discussion, $post, NULL, $cm)) {
                    echo "</div>\n";
                    continue;
                }
                $postuser     = new stdClass;
                $postuser->id = $post->userid;

                username_load_fields_from_object($postuser, $post);

                $postuser = hsuforum_anonymize_user($postuser, $forum, $post);

                $by = new stdClass();
                $by->name = fullname($postuser, $canviewfullnames);
                $by->date = userdate($post->modified);

                if (!hsuforum_is_anonymous_user($postuser) and has_capability('moodle/course:manageactivities', $modcontext, $postuser->id)) {
                    $by->name = html_writer::tag('span', $by->name, array('class' => 'hsuforum_highlightposter'));
                }
                if ($forumtracked) {
                    if (!empty($post->postread)) {
                        $style = '<span class="forumthread read">';
                    } else {
                        $style = '<span class="forumthread unread">';
                    }
                } else {
                    $style = '<span class="forumthread">';
                }
                echo $style."<a name=\"$post->id\"></a>".
                     "<a href=\"discuss.php?d=$post->discussion&amp;parent=$post->id\">".format_string($post->subject,true)."</a> ";
                print_string("bynameondate", "hsuforum", $by);
                echo $PAGE->get_renderer('mod_hsuforum')->post_flags($post, $modcontext);
                echo "</span>";
            }

            hsuforum_print_posts_threaded($course, $cm, $forum, $discussion, $post, $depth-1, $reply, $forumtracked, $posts);
            echo "</div>\n";
        }
    }
}

/**
 * @todo Document this function
 * @global object
 * @global object
 * @return void
 */
function hsuforum_print_posts_nested($course, &$cm, $forum, $discussion, $parent, $reply, $forumtracked, $posts) {
    global $USER, $CFG;

    $link  = false;

    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;

        foreach ($posts as $post) {

            echo '<div class="indent">';
            if (!isloggedin()) {
                $ownpost = false;
            } else {
                $ownpost = ($USER->id == $post->userid);
            }

            $post->subject = format_string($post->subject);
            $postread = !empty($post->postread);

            hsuforum_print_post($post, $discussion, $forum, $cm, $course, $ownpost, $reply, $link,
                                 '', '', $postread, true, $forumtracked);
            hsuforum_print_posts_nested($course, $cm, $forum, $discussion, $post, $reply, $forumtracked, $posts);
            echo "</div>\n";
        }
    }
}

/**
 * Returns all forum posts since a given time in specified forum.
 *
 * @todo Document this functions args
 * @global object
 * @global object
 * @global object
 * @global object
 */
function hsuforum_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0)  {
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id' => $courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];

    // Cannot report on recent activity on anonymous forums as we could reveal user's identity.
    $anonymous = $DB->get_field('hsuforum', 'anonymous', array('id' => $cm->instance), MUST_EXIST);
    if (!empty($anonymous)) {
        $tmpactivity             = new stdClass();
        $tmpactivity->type       = 'hsuforum';
        $tmpactivity->cmid       = $cm->id;
        $tmpactivity->name       = format_string($cm->name, true);;
        $tmpactivity->sectionnum = $cm->sectionnum;
        $tmpactivity->timestamp  = time();
        $tmpactivity->content    = get_string('anonymousrecentactivity', 'hsuforum');

        $activities[$index++] = $tmpactivity;
        return;
    }

    $params = array($timestart, $cm->instance, $USER->id, $USER->id);

    if ($userid) {
        $userselect = "AND u.id = ?";
        $params[] = $userid;
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND d.groupid = ?";
        $params[] = $groupid;
    } else {
        $groupselect = "";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    if (!$posts = $DB->get_records_sql("SELECT p.*, f.anonymous AS forumanonymous, f.type AS forumtype, d.forum, d.groupid,
                                              d.timestart, d.timeend, d.userid AS duserid,
                                              $allnames, u.email, u.picture, u.imagealt, u.email
                                         FROM {hsuforum_posts} p
                                              JOIN {hsuforum_discussions} d ON d.id = p.discussion
                                              JOIN {hsuforum} f             ON f.id = d.forum
                                              JOIN {user} u              ON u.id = p.userid
                                        WHERE p.created > ? AND f.id = ? AND (p.privatereply = 0 OR p.privatereply = ? OR p.userid = ?)
                                              $userselect $groupselect
                                     ORDER BY p.id ASC", $params)) { // order by initial posting date
         return;
    }

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $cm_context      = context_module::instance($cm->id);
    $viewhiddentimed = has_capability('mod/hsuforum:viewhiddentimedposts', $cm_context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cm_context);

    $printposts = array();
    foreach ($posts as $post) {

        if (!empty($CFG->hsuforum_enabletimedposts) and $USER->id != $post->duserid
          and (($post->timestart > 0 and $post->timestart > time()) or ($post->timeend > 0 and $post->timeend < time()))) {
            if (!$viewhiddentimed) {
                continue;
            }
        }

        if ($groupmode) {
            if ($post->groupid == -1 or $groupmode == VISIBLEGROUPS or $accessallgroups) {
                // oki (Open discussions have groupid -1)
            } else {
                // separate mode
                if (isguestuser()) {
                    // shortcut
                    continue;
                }

                if (!in_array($post->groupid, $modinfo->get_groups($cm->groupingid))) {
                    continue;
                }
            }
        }

        $printposts[] = $post;
    }

    if (!$printposts) {
        return;
    }

    $aname = format_string($cm->name,true);

    foreach ($printposts as $post) {
        $tmpactivity = new stdClass();

        $tmpactivity->type         = 'hsuforum';
        $tmpactivity->cmid         = $cm->id;
        $tmpactivity->name         = $aname;
        $tmpactivity->sectionnum   = $cm->sectionnum;
        $tmpactivity->timestamp    = $post->modified;

        $tmpactivity->content = new stdClass();
        $tmpactivity->content->id         = $post->id;
        $tmpactivity->content->discussion = $post->discussion;
        $tmpactivity->content->subject    = format_string($post->subject);
        $tmpactivity->content->parent     = $post->parent;

        $tmpactivity->user = new stdClass();
        $additionalfields = array('id' => 'userid', 'picture', 'imagealt', 'email');
        $additionalfields = explode(',', user_picture::fields());
        $tmpactivity->user = username_load_fields_from_object($tmpactivity->user, $post, null, $additionalfields);
        $tmpactivity->user->id = $post->userid;

        $tmpactivity->user = hsuforum_anonymize_user($tmpactivity->user, (object) array(
            'id'        => $post->forum,
            'course'    => $courseid,
            'anonymous' => $post->forumanonymous
        ), $post);

        $activities[$index++] = $tmpactivity;
    }

    return;
}

/**
 * @todo Document this function
 * @global object
 */
function hsuforum_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $CFG, $OUTPUT;

    // This handles anonymous forums.
    if (is_string($activity->content)) {
        echo $OUTPUT->box($activity->content, 'forum-recent anonymous');
        return;
    }
    if ($activity->content->parent) {
        $class = 'reply';
    } else {
        $class = 'discussion';
    }

    echo '<table border="0" cellpadding="3" cellspacing="0" class="forum-recent">';

    echo "<tr><td class=\"userpicture\" valign=\"top\">";
    echo $OUTPUT->user_picture($activity->user, array('courseid'=>$courseid, 'link' => (!hsuforum_is_anonymous_user($activity->user))));
    echo "</td><td class=\"$class\">";

    echo '<div class="title">';
    if ($detail) {
        $aname = s($activity->name);
        echo "<img src=\"" . $OUTPUT->pix_url('icon', $activity->type) . "\" ".
             "class=\"icon\" alt=\"{$aname}\" />";
    }
    echo "<a href=\"$CFG->wwwroot/mod/hsuforum/discuss.php?d={$activity->content->discussion}"
         ."#p{$activity->content->id}\">{$activity->content->subject}</a>";
    echo '</div>';

    echo '<div class="user">';
    $fullname = fullname($activity->user, $viewfullnames);
    if (hsuforum_is_anonymous_user($activity->user)) {
        echo "{$fullname} - ".userdate($activity->timestamp);
    } else {
        echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">"
                 ."{$fullname}</a> - ".userdate($activity->timestamp);
    }
    echo '</div>';
      echo "</td></tr></table>";

    return;
}

/**
 * recursively sets the discussion field to $discussionid on $postid and all its children
 * used when pruning a post
 *
 * @global object
 * @param int $postid
 * @param int $discussionid
 * @return bool
 */
function hsuforum_change_discussionid($postid, $discussionid) {
    global $DB;
    $DB->set_field('hsuforum_posts', 'discussion', $discussionid, array('id' => $postid));
    if ($posts = $DB->get_records('hsuforum_posts', array('parent' => $postid))) {
        foreach ($posts as $post) {
            hsuforum_change_discussionid($post->id, $discussionid);
        }
    }
    return true;
}

/**
 * Prints the editing button on subscribers page
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param int $forumid
 * @return string
 */
function hsuforum_update_subscriptions_button($courseid, $forumid) {
    global $CFG, $USER;

    if (!empty($USER->subscriptionsediting)) {
        $string = get_string('turneditingoff');
        $edit = "off";
    } else {
        $string = get_string('turneditingon');
        $edit = "on";
    }

    return "<form method=\"get\" action=\"$CFG->wwwroot/mod/hsuforum/subscribers.php\">".
           "<input type=\"hidden\" name=\"id\" value=\"$forumid\" />".
           "<input type=\"hidden\" name=\"edit\" value=\"$edit\" />".
           "<input type=\"submit\" value=\"$string\" /></form>";
}

/**
 * This function gets run whenever user is enrolled into course
 *
 * @deprecated deprecating this function as we will be using \mod_hsuforum\observer::role_assigned()
 * @param stdClass $cp
 * @return void
 */
function hsuforum_user_enrolled($cp) {
    global $DB;

    // NOTE: this has to be as fast as possible - we do not want to slow down enrolments!
    //       Originally there used to be 'mod/hsuforum:initialsubscriptions' which was
    //       introduced because we did not have enrolment information in earlier versions...

    $sql = "SELECT f.id
              FROM {hsuforum} f
         LEFT JOIN {hsuforum_subscriptions} fs ON (fs.forum = f.id AND fs.userid = :userid)
             WHERE f.course = :courseid AND f.forcesubscribe = :initial AND fs.id IS NULL";
    $params = array('courseid'=>$cp->courseid, 'userid'=>$cp->userid, 'initial'=>HSUFORUM_INITIALSUBSCRIBE);

    $forums = $DB->get_records_sql($sql, $params);
    foreach ($forums as $forum) {
        hsuforum_subscribe($cp->userid, $forum->id);
    }
}

// Functions to do with read tracking.

/**
 * Mark posts as read.
 *
 * @global object
 * @global object
 * @param object $user object
 * @param array $postids array of post ids
 * @return boolean success
 */
function hsuforum_tp_mark_posts_read($user, $postids) {
    global $CFG, $DB;

    if (!hsuforum_tp_can_track_forums(false, $user)) {
        return true;
    }

    $status = true;

    $now = time();
    $cutoffdate = $now - ($CFG->hsuforum_oldpostdays * 24 * 3600);

    if (empty($postids)) {
        return true;

    } else if (count($postids) > 200) {
        while ($part = array_splice($postids, 0, 200)) {
            $status = hsuforum_tp_mark_posts_read($user, $part) && $status;
        }
        return $status;
    }

    list($usql, $params) = $DB->get_in_or_equal($postids);
    $params[] = $user->id;

    $sql = "SELECT id
              FROM {hsuforum_read}
             WHERE postid $usql AND userid = ?";
    if ($existing = $DB->get_records_sql($sql, $params)) {
        $existing = array_keys($existing);
    } else {
        $existing = array();
    }

    $new = array_diff($postids, $existing);

    if ($new) {
        list($usql, $new_params) = $DB->get_in_or_equal($new);
        $params = array($user->id, $now, $now, $user->id);
        $params = array_merge($params, $new_params);
        $params[] = $cutoffdate;

        if ($CFG->hsuforum_allowforcedreadtracking) {
            $trackingsql = "AND (f.trackingtype = ".HSUFORUM_TRACKING_FORCED."
                            OR (f.trackingtype = ".HSUFORUM_TRACKING_OPTIONAL." AND tf.id IS NULL))";
        } else {
            $trackingsql = "AND ((f.trackingtype = ".HSUFORUM_TRACKING_OPTIONAL."  OR f.trackingtype = ".HSUFORUM_TRACKING_FORCED.")
                                AND tf.id IS NULL)";
        }

        $sql = "INSERT INTO {hsuforum_read} (userid, postid, discussionid, forumid, firstread, lastread)

                SELECT ?, p.id, p.discussion, d.forum, ?, ?
                  FROM {hsuforum_posts} p
                       JOIN {hsuforum_discussions} d       ON d.id = p.discussion
                       JOIN {hsuforum} f                   ON f.id = d.forum
                       LEFT JOIN {hsuforum_track_prefs} tf ON (tf.userid = ? AND tf.forumid = f.id)
                 WHERE p.id $usql
                       AND p.modified >= ?
                       $trackingsql";
        $status = $DB->execute($sql, $params) && $status;
    }

    if ($existing) {
        list($usql, $new_params) = $DB->get_in_or_equal($existing);
        $params = array($now, $user->id);
        $params = array_merge($params, $new_params);

        $sql = "UPDATE {hsuforum_read}
                   SET lastread = ?
                 WHERE userid = ? AND postid $usql";
        $status = $DB->execute($sql, $params) && $status;
    }

    return $status;
}

/**
 * Mark post as read.
 * @global object
 * @global object
 * @param int $userid
 * @param int $postid
 */
function hsuforum_tp_add_read_record($userid, $postid) {
    global $CFG, $DB;

    $now = time();
    $cutoffdate = $now - ($CFG->hsuforum_oldpostdays * 24 * 3600);

    if (!$DB->record_exists('hsuforum_read', array('userid' => $userid, 'postid' => $postid))) {
        $sql = "INSERT INTO {hsuforum_read} (userid, postid, discussionid, forumid, firstread, lastread)

                SELECT ?, p.id, p.discussion, d.forum, ?, ?
                  FROM {hsuforum_posts} p
                       JOIN {hsuforum_discussions} d ON d.id = p.discussion
                 WHERE p.id = ? AND p.modified >= ?";
        return $DB->execute($sql, array($userid, $now, $now, $postid, $cutoffdate));

    } else {
        $sql = "UPDATE {hsuforum_read}
                   SET lastread = ?
                 WHERE userid = ? AND postid = ?";
        return $DB->execute($sql, array($now, $userid, $userid));
    }
}

/**
 * Returns all records in the 'hsuforum_read' table matching the passed keys, indexed
 * by userid.
 *
 * @global object
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $forumid
 * @return array
 */
function hsuforum_tp_get_read_records($userid=-1, $postid=-1, $discussionid=-1, $forumid=-1) {
    global $DB;
    $select = '';
    $params = array();

    if ($userid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'userid = ?';
        $params[] = $userid;
    }
    if ($postid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'postid = ?';
        $params[] = $postid;
    }
    if ($discussionid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'discussionid = ?';
        $params[] = $discussionid;
    }
    if ($forumid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'forumid = ?';
        $params[] = $forumid;
    }

    return $DB->get_records_select('hsuforum_read', $select, $params);
}

/**
 * Returns all read records for the provided user and discussion, indexed by postid.
 *
 * @global object
 * @param inti $userid
 * @param int $discussionid
 */
function hsuforum_tp_get_discussion_read_records($userid, $discussionid) {
    global $DB;
    $select = 'userid = ? AND discussionid = ?';
    $fields = 'postid, firstread, lastread';
    return $DB->get_records_select('hsuforum_read', $select, array($userid, $discussionid), '', $fields);
}

/**
 * If its an old post, do nothing. If the record exists, the maintenance will clear it up later.
 *
 * @return bool
 */
function hsuforum_tp_mark_post_read($userid, $post, $forumid) {
    if (!hsuforum_tp_is_post_old($post)) {
        return hsuforum_tp_add_read_record($userid, $post->id);
    } else {
        return true;
    }
}

/**
 * Marks a whole forum as read, for a given user
 *
 * @global object
 * @global object
 * @param object $user
 * @param int $forumid
 * @param int|bool $groupid
 * @return bool
 */
function hsuforum_tp_mark_hsuforum_read($user, $forumid, $groupid=false) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->hsuforum_oldpostdays*24*60*60);

    $groupsel = "";
    $params = array($user->id, $forumid, $cutoffdate);

    if ($groupid !== false) {
        $groupsel = " AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT p.id
              FROM {hsuforum_posts} p
                   LEFT JOIN {hsuforum_discussions} d ON d.id = p.discussion
                   LEFT JOIN {hsuforum_read} r        ON (r.postid = p.id AND r.userid = ?)
             WHERE d.forum = ?
                   AND p.modified >= ? AND r.id is NULL
                   $groupsel";

    if ($posts = $DB->get_records_sql($sql, $params)) {
        $postids = array_keys($posts);
        return hsuforum_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 * Marks a whole discussion as read, for a given user
 *
 * @global object
 * @global object
 * @param object $user
 * @param int $discussionid
 * @return bool
 */
function hsuforum_tp_mark_discussion_read($user, $discussionid) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->hsuforum_oldpostdays*24*60*60);

    $sql = "SELECT p.id
              FROM {hsuforum_posts} p
                   LEFT JOIN {hsuforum_read} r ON (r.postid = p.id AND r.userid = ?)
             WHERE p.discussion = ?
                   AND p.modified >= ? AND r.id is NULL";

    if ($posts = $DB->get_records_sql($sql, array($user->id, $discussionid, $cutoffdate))) {
        $postids = array_keys($posts);
        return hsuforum_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 * @global object
 * @param int $userid
 * @param object $post
 */
function hsuforum_tp_is_post_read($userid, $post) {
    global $DB;
    return (hsuforum_tp_is_post_old($post) ||
            $DB->record_exists('hsuforum_read', array('userid' => $userid, 'postid' => $post->id)));
}

/**
 * @global object
 * @param object $post
 * @param int $time Defautls to time()
 */
function hsuforum_tp_is_post_old($post, $time=null) {
    global $CFG;

    if (is_null($time)) {
        $time = time();
    }
    return ($post->modified < ($time - ($CFG->hsuforum_oldpostdays * 24 * 3600)));
}

/**
 * Returns the count of records for the provided user and discussion.
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $discussionid
 * @return bool
 */
function hsuforum_tp_count_discussion_read_records($userid, $discussionid) {
    global $CFG, $DB;

    $cutoffdate = isset($CFG->hsuforum_oldpostdays) ? (time() - ($CFG->hsuforum_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(DISTINCT p.id) '.
           'FROM {hsuforum_discussions} d '.
           'LEFT JOIN {hsuforum_read} r ON d.id = r.discussionid AND r.userid = ? '.
           'LEFT JOIN {hsuforum_posts} p ON p.discussion = d.id '.
                'AND (p.modified < ? OR p.id = r.postid) '.
           'WHERE d.id = ? ';

    return ($DB->count_records_sql($sql, array($userid, $cutoffdate, $discussionid)));
}

/**
 * Returns the count of records for the provided user and discussion.
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $discussionid
 * @return int
 */
function hsuforum_tp_count_discussion_unread_posts($userid, $discussionid) {
    global $CFG, $DB;

    $cutoffdate = isset($CFG->hsuforum_oldpostdays) ? (time() - ($CFG->hsuforum_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(p.id) '.
           'FROM {hsuforum_posts} p '.
           'LEFT JOIN {hsuforum_read} r ON r.postid = p.id AND r.userid = ? '.
           'WHERE p.discussion = ? '.
                'AND p.modified >= ? AND r.id is NULL';

    return $DB->count_records_sql($sql, array($userid, $discussionid, $cutoffdate));
}

/**
 * Returns the count of posts for the provided forum and [optionally] group.
 * @global object
 * @global object
 * @param int $forumid
 * @param int|bool $groupid
 * @return int
 */
function hsuforum_tp_count_hsuforum_posts($forumid, $groupid=false) {
    global $CFG, $DB;
    $params = array($forumid);
    $sql = 'SELECT COUNT(*) '.
           'FROM {hsuforum_posts} fp,{hsuforum_discussions} fd '.
           'WHERE fd.forum = ? AND fp.discussion = fd.id';
    if ($groupid !== false) {
        $sql .= ' AND (fd.groupid = ? OR fd.groupid = -1)';
        $params[] = $groupid;
    }
    $count = $DB->count_records_sql($sql, $params);


    return $count;
}

/**
 * Returns the count of records for the provided user and forum and [optionally] group.
 * @global object
 * @global object
 * @param int $userid
 * @param int $forumid
 * @param int|bool $groupid
 * @return int
 */
function hsuforum_tp_count_hsuforum_read_records($userid, $forumid, $groupid=false) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->hsuforum_oldpostdays*24*60*60);

    $groupsel = '';
    $params = array($userid, $forumid, $cutoffdate);
    if ($groupid !== false) {
        $groupsel = "AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT COUNT(p.id)
              FROM  {hsuforum_posts} p
                    JOIN {hsuforum_discussions} d ON d.id = p.discussion
                    LEFT JOIN {hsuforum_read} r   ON (r.postid = p.id AND r.userid= ?)
              WHERE d.forum = ?
                    AND (p.modified < $cutoffdate OR (p.modified >= ? AND r.id IS NOT NULL))
                    $groupsel";

    return $DB->get_field_sql($sql, $params);
}

/**
 * Returns the count of records for the provided user and course.
 * Please note that group access is ignored!
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $courseid
 * @return array
 */
function hsuforum_tp_get_course_unread_posts($userid, $courseid) {
    global $CFG, $DB;

    $now = round(time(), -2); // DB cache friendliness.
    $cutoffdate = $now - ($CFG->hsuforum_oldpostdays * 24 * 60 * 60);
    $params = array($userid, $userid, $courseid, $cutoffdate, $userid, $userid, $userid);

    if (!empty($CFG->hsuforum_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    if ($CFG->hsuforum_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = ".HSUFORUM_TRACKING_FORCED."
                            OR (f.trackingtype = ".HSUFORUM_TRACKING_OPTIONAL." AND tf.id IS NULL
                                AND (SELECT trackforums FROM {user} WHERE id = ?) = 1))";
    } else {
        $trackingsql = "AND ((f.trackingtype = ".HSUFORUM_TRACKING_OPTIONAL." OR f.trackingtype = ".HSUFORUM_TRACKING_FORCED.")
                            AND tf.id IS NULL
                            AND (SELECT trackforums FROM {user} WHERE id = ?) = 1)";
    }

    $sql = "SELECT f.id, COUNT(p.id) AS unread
              FROM {hsuforum_posts} p
                   JOIN {hsuforum_discussions} d       ON d.id = p.discussion
                   JOIN {hsuforum} f                   ON f.id = d.forum
                   JOIN {course} c                  ON c.id = f.course
                   LEFT JOIN {hsuforum_read} r         ON (r.postid = p.id AND r.userid = ?)
                   LEFT JOIN {hsuforum_track_prefs} tf ON (tf.userid = ? AND tf.forumid = f.id)
             WHERE f.course = ?
                   AND p.modified >= ? AND r.id is NULL
                   AND (p.privatereply = 0 OR p.privatereply = ? OR p.userid = ?)
                   $trackingsql
                   $timedsql
          GROUP BY f.id";

    if ($return = $DB->get_records_sql($sql, $params)) {
        return $return;
    }

    return array();
}

/**
 * Returns the count of records for the provided user and forum and [optionally] group.
 *
 * @global object
 * @global object
 * @global object
 * @param object $cm
 * @param object $course
 * @return int
 */
function hsuforum_tp_count_hsuforum_unread_posts($cm, $course) {
    global $CFG, $USER, $DB;

    static $readcache = array();

    $forumid = $cm->instance;

    if (!isset($readcache[$course->id])) {
        $readcache[$course->id] = array();
        if ($counts = hsuforum_tp_get_course_unread_posts($USER->id, $course->id)) {
            foreach ($counts as $count) {
                $readcache[$course->id][$count->id] = $count->unread;
            }
        }
    }

    if (empty($readcache[$course->id][$forumid])) {
        // no need to check group mode ;-)
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $readcache[$course->id][$forumid];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
        return $readcache[$course->id][$forumid];
    }

    require_once($CFG->dirroot.'/course/lib.php');

    $modinfo = get_fast_modinfo($course);

    $mygroups = $modinfo->get_groups($cm->groupingid);

    // add all groups posts
    $mygroups[-1] = -1;

    list ($groups_sql, $groups_params) = $DB->get_in_or_equal($mygroups);

    $now = round(time(), -2); // db cache friendliness
    $cutoffdate = $now - ($CFG->hsuforum_oldpostdays*24*60*60);
    $params = array($USER->id, $forumid, $cutoffdate, $USER->id, $USER->id);

    if (!empty($CFG->hsuforum_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    $params = array_merge($params, $groups_params);

    $sql = "SELECT COUNT(p.id)
              FROM {hsuforum_posts} p
                   JOIN {hsuforum_discussions} d ON p.discussion = d.id
                   LEFT JOIN {hsuforum_read} r   ON (r.postid = p.id AND r.userid = ?)
             WHERE d.forum = ?
                   AND p.modified >= ? AND r.id is NULL
                   AND (p.privatereply = 0 OR p.privatereply = ? OR p.userid = ?)
                   $timedsql
                   AND d.groupid $groups_sql";

    return $DB->get_field_sql($sql, $params);
}

/**
 * Deletes read records for the specified index. At least one parameter must be specified.
 *
 * @global object
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $forumid
 * @return bool
 */
function hsuforum_tp_delete_read_records($userid=-1, $postid=-1, $discussionid=-1, $forumid=-1) {
    global $DB;
    $params = array();

    $select = '';
    if ($userid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'userid = ?';
        $params[] = $userid;
    }
    if ($postid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'postid = ?';
        $params[] = $postid;
    }
    if ($discussionid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'discussionid = ?';
        $params[] = $discussionid;
    }
    if ($forumid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'forumid = ?';
        $params[] = $forumid;
    }
    if ($select == '') {
        return false;
    }
    else {
        return $DB->delete_records_select('hsuforum_read', $select, $params);
    }
}
/**
 * Get a list of forums not tracked by the user.
 *
 * @global object
 * @global object
 * @param int $userid The id of the user to use.
 * @param int $courseid The id of the course being checked.
 * @return mixed An array indexed by forum id, or false.
 */
function hsuforum_tp_get_untracked_forums($userid, $courseid) {
    global $CFG, $DB;

    if ($CFG->hsuforum_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = ".HSUFORUM_TRACKING_OFF."
                            OR (f.trackingtype = ".HSUFORUM_TRACKING_OPTIONAL." AND (ft.id IS NOT NULL
                                OR (SELECT trackforums FROM {user} WHERE id = ?) = 0)))";
    } else {
        $trackingsql = "AND (f.trackingtype = ".HSUFORUM_TRACKING_OFF."
                            OR ((f.trackingtype = ".HSUFORUM_TRACKING_OPTIONAL." OR f.trackingtype = ".HSUFORUM_TRACKING_FORCED.")
                                AND (ft.id IS NOT NULL
                                    OR (SELECT trackforums FROM {user} WHERE id = ?) = 0)))";
    }

    $sql = "SELECT f.id
              FROM {hsuforum} f
                   LEFT JOIN {hsuforum_track_prefs} ft ON (ft.forumid = f.id AND ft.userid = ?)
             WHERE f.course = ?
                   $trackingsql";

    if ($forums = $DB->get_records_sql($sql, array($userid, $courseid, $userid))) {
        foreach ($forums as $forum) {
            $forums[$forum->id] = $forum;
        }
        return $forums;

    } else {
        return array();
    }
}

/**
 * Determine if a user can track forums and optionally a particular forum.
 * Checks the site settings, the user settings and the forum settings (if
 * requested).
 *
 * @global object
 * @global object
 * @global object
 * @param mixed $forum The forum object to test, or the int id (optional).
 * @param mixed $userid The user object to check for (optional).
 * @return boolean
 */
function hsuforum_tp_can_track_forums($forum=false, $user=false) {
    global $USER, $CFG, $DB;

    // if possible, avoid expensive
    // queries
    if (empty($CFG->hsuforum_trackreadposts)) {
        return false;
    }

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    if ($forum === false) {
        if ($CFG->hsuforum_allowforcedreadtracking) {
            // Since we can force tracking, assume yes without a specific forum.
            return true;
        } else {
            return (bool)$user->trackforums;
        }
    }

    // Work toward always passing an object...
    if (is_numeric($forum)) {
        debugging('Better use proper forum object.', DEBUG_DEVELOPER);
        $forum = $DB->get_record('hsuforum', array('id' => $forum), '', 'id,trackingtype');
    }

    $forumallows = ($forum->trackingtype == HSUFORUM_TRACKING_OPTIONAL);
    $forumforced = ($forum->trackingtype == HSUFORUM_TRACKING_FORCED);

    if ($CFG->hsuforum_allowforcedreadtracking) {
        // If we allow forcing, then forced forums takes procidence over user setting.
        return ($forumforced || ($forumallows  && (!empty($user->trackforums) && (bool)$user->trackforums)));
    } else {
        // If we don't allow forcing, user setting trumps.
        return ($forumforced || $forumallows)  && !empty($user->trackforums);
    }
}

/**
 * Tells whether a specific forum is tracked by the user. A user can optionally
 * be specified. If not specified, the current user is assumed.
 *
 * @global object
 * @global object
 * @global object
 * @param mixed $forum If int, the id of the forum being checked; if object, the forum object
 * @param int $userid The id of the user being checked (optional).
 * @return boolean
 */
function hsuforum_tp_is_tracked($forum, $user=false) {
    global $USER, $CFG, $DB;

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    // Work toward always passing an object...
    if (is_numeric($forum)) {
        debugging('Better use proper forum object.', DEBUG_DEVELOPER);
        $forum = $DB->get_record('hsuforum', array('id' => $forum));
    }

    if (!hsuforum_tp_can_track_forums($forum, $user)) {
        return false;
    }

    $forumallows = ($forum->trackingtype == HSUFORUM_TRACKING_OPTIONAL);
    $forumforced = ($forum->trackingtype == HSUFORUM_TRACKING_FORCED);
    $userpref = $DB->get_record('hsuforum_track_prefs', array('userid' => $user->id, 'forumid' => $forum->id));

    if ($CFG->hsuforum_allowforcedreadtracking) {
        return $forumforced || ($forumallows && $userpref === false);
    } else {
        return  ($forumallows || $forumforced) && $userpref === false;
    }
}

/**
 * @global object
 * @global object
 * @param int $forumid
 * @param int $userid
 */
function hsuforum_tp_start_tracking($forumid, $userid=false) {
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER->id;
    }

    return $DB->delete_records('hsuforum_track_prefs', array('userid' => $userid, 'forumid' => $forumid));
}

/**
 * @global object
 * @global object
 * @param int $forumid
 * @param int $userid
 */
function hsuforum_tp_stop_tracking($forumid, $userid=false) {
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER->id;
    }

    if (!$DB->record_exists('hsuforum_track_prefs', array('userid' => $userid, 'forumid' => $forumid))) {
        $track_prefs = new stdClass();
        $track_prefs->userid = $userid;
        $track_prefs->forumid = $forumid;
        $DB->insert_record('hsuforum_track_prefs', $track_prefs);
    }

    return hsuforum_tp_delete_read_records($userid, -1, -1, $forumid);
}


/**
 * Clean old records from the hsuforum_read table.
 * @global object
 * @global object
 * @return void
 */
function hsuforum_tp_clean_read_records() {
    global $CFG, $DB;

    if (!isset($CFG->hsuforum_oldpostdays)) {
        return;
    }
// Look for records older than the cutoffdate that are still in the hsuforum_read table.
    $cutoffdate = time() - ($CFG->hsuforum_oldpostdays*24*60*60);

    //first get the oldest tracking present - we need tis to speedup the next delete query
    $sql = "SELECT MIN(fp.modified) AS first
              FROM {hsuforum_posts} fp
                   JOIN {hsuforum_read} fr ON fr.postid=fp.id";
    if (!$first = $DB->get_field_sql($sql)) {
        // nothing to delete;
        return;
    }

    // now delete old tracking info
    $sql = "DELETE
              FROM {hsuforum_read}
             WHERE postid IN (SELECT fp.id
                                FROM {hsuforum_posts} fp
                               WHERE fp.modified >= ? AND fp.modified < ?)";
    $DB->execute($sql, array($first, $cutoffdate));
}

/**
 * Sets the last post for a given discussion
 *
 * @global object
 * @global object
 * @param into $discussionid
 * @return bool|int
 **/
function hsuforum_discussion_update_last_post($discussionid) {
    global $CFG, $DB;

// Check the given discussion exists
    if (!$DB->record_exists('hsuforum_discussions', array('id' => $discussionid))) {
        return false;
    }

// Use SQL to find the last post for this discussion
    $sql = "SELECT id, userid, modified
              FROM {hsuforum_posts}
             WHERE discussion=?
             ORDER BY modified DESC";

// Lets go find the last post
    if (($lastposts = $DB->get_records_sql($sql, array($discussionid), 0, 1))) {
        $lastpost = reset($lastposts);
        $discussionobject = new stdClass();
        $discussionobject->id           = $discussionid;
        $discussionobject->usermodified = $lastpost->userid;
        $discussionobject->timemodified = $lastpost->modified;
        $DB->update_record('hsuforum_discussions', $discussionobject);
        return $lastpost->id;
    }

// To get here either we couldn't find a post for the discussion (weird)
// or we couldn't update the discussion record (weird x2)
    return false;
}


/**
 * @return array
 */
function hsuforum_get_view_actions() {
    return array('view discussion', 'search', 'forum', 'forums', 'subscribers', 'view forum');
}

/**
 * @return array
 */
function hsuforum_get_post_actions() {
    return array('add discussion','add post','delete discussion','delete post','move discussion','prune post','update post');
}

/**
 * Returns a warning object if a user has reached the number of posts equal to
 * the warning/blocking setting, or false if there is no warning to show.
 *
 * @param int|stdClass $forum the forum id or the forum object
 * @param stdClass $cm the course module
 * @return stdClass|bool returns an object with the warning information, else
 *         returns false if no warning is required.
 */
function hsuforum_check_throttling($forum, $cm = null) {
    global $CFG, $DB, $USER;

    if (is_numeric($forum)) {
        $forum = $DB->get_record('hsuforum', array('id' => $forum), '*', MUST_EXIST);
    }

    if (!is_object($forum)) {
        return false; // This is broken.
    }

    if (!$cm) {
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id, $forum->course, false, MUST_EXIST);
    }

    if (empty($forum->blockafter)) {
        return false;
    }

    if (empty($forum->blockperiod)) {
        return false;
    }

    $modcontext = context_module::instance($cm->id);
    if (has_capability('mod/hsuforum:postwithoutthrottling', $modcontext)) {
        return false;
    }

    // Get the number of posts in the last period we care about.
    $timenow = time();
    $timeafter = $timenow - $forum->blockperiod;
    $numposts = $DB->count_records_sql('SELECT COUNT(p.id) FROM {hsuforum_posts} p
                                        JOIN {hsuforum_discussions} d
                                        ON p.discussion = d.id WHERE d.forum = ?
                                        AND p.userid = ? AND p.created > ?', array($forum->id, $USER->id, $timeafter));

    $a = new stdClass();
    $a->blockafter = $forum->blockafter;
    $a->numposts = $numposts;
    $a->blockperiod = get_string('secondstotime'.$forum->blockperiod);

    if ($forum->blockafter <= $numposts) {
        $warning = new stdClass();
        $warning->canpost = false;
        $warning->errorcode = 'forumblockingtoomanyposts';
        $warning->module = 'error';
        $warning->additional = $a;
        $warning->link = $CFG->wwwroot . '/mod/hsuforum/view.php?f=' . $forum->id;

        return $warning;
    }

    if ($forum->warnafter <= $numposts) {
        $warning = new stdClass();
        $warning->canpost = true;
        $warning->errorcode = 'forumblockingalmosttoomanyposts';
        $warning->module = 'hsuforum';
        $warning->additional = $a;
        $warning->link = null;

        return $warning;
    }
}

/**
 * Throws an error if the user is no longer allowed to post due to having reached
 * or exceeded the number of posts specified in 'Post threshold for blocking'
 * setting.
 *
 * @since Moodle 2.5
 * @param stdClass $thresholdwarning the warning information returned
 *        from the function hsuforum_check_throttling.
 */
function hsuforum_check_blocking_threshold($thresholdwarning) {
    if (!empty($thresholdwarning) && !$thresholdwarning->canpost) {
        print_error($thresholdwarning->errorcode,
                    $thresholdwarning->module,
                    $thresholdwarning->link,
                    $thresholdwarning->additional);
    }
}


/**
 * Removes all grades from gradebook
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param string $type optional
 */
function hsuforum_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $wheresql = '';
    $params = array($courseid);
    if ($type) {
        $wheresql = "AND f.type=?";
        $params[] = $type;
    }

    $sql = "SELECT f.*, cm.idnumber as cmidnumber, f.course as courseid
              FROM {hsuforum} f, {course_modules} cm, {modules} m
             WHERE m.name='hsuforum' AND m.id=cm.module AND cm.instance=f.id AND f.course=? $wheresql";

    if ($forums = $DB->get_records_sql($sql, $params)) {
        foreach ($forums as $forum) {
            hsuforum_grade_item_update($forum, 'reset');
        }
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified forum
 * and clean up any related data.
 *
 * @global object
 * @global object
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function hsuforum_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/rating/lib.php');

    $componentstr = get_string('modulenameplural', 'hsuforum');
    $status = array();

    $params = array($data->courseid);

    $removeposts = false;
    $typesql     = "";
    if (!empty($data->reset_hsuforum_all)) {
        $removeposts = true;
        $typesstr    = get_string('resetforumsall', 'hsuforum');
        $types       = array();
    } else if (!empty($data->reset_hsuforum_types)){
        $removeposts = true;
        $typesql     = "";
        $types       = array();
        $hsuforum_types_all = hsuforum_get_hsuforum_types_all();
        foreach ($data->reset_hsuforum_types as $type) {
            if (!array_key_exists($type, $hsuforum_types_all)) {
                continue;
            }
            $typesql .= " AND f.type=?";
            $types[] = $hsuforum_types_all[$type];
            $params[] = $type;
        }
        $typesstr = get_string('resetforums', 'hsuforum').': '.implode(', ', $types);
    }
    $alldiscussionssql = "SELECT fd.id
                            FROM {hsuforum_discussions} fd, {hsuforum} f
                           WHERE f.course=? AND f.id=fd.forum";

    $allforumssql      = "SELECT f.id
                            FROM {hsuforum} f
                           WHERE f.course=?";

    $allpostssql       = "SELECT fp.id
                            FROM {hsuforum_posts} fp, {hsuforum_discussions} fd, {hsuforum} f
                           WHERE f.course=? AND f.id=fd.forum AND fd.id=fp.discussion";

    $forumssql = $forums = $rm = null;

    if( $removeposts || !empty($data->reset_hsuforum_ratings) ) {
        $forumssql      = "$allforumssql $typesql";
        $forums = $forums = $DB->get_records_sql($forumssql, $params);
        $rm = new rating_manager();
        $ratingdeloptions = new stdClass;
        $ratingdeloptions->component = 'mod_hsuforum';
        $ratingdeloptions->ratingarea = 'post';
    }

    if ($removeposts) {
        $discussionssql = "$alldiscussionssql $typesql";
        $postssql       = "$allpostssql $typesql";

        // now get rid of all attachments
        $fs = get_file_storage();
        if ($forums) {
            foreach ($forums as $forumid=>$unused) {
                if (!$cm = get_coursemodule_from_instance('hsuforum', $forumid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);
                $fs->delete_area_files($context->id, 'mod_hsuforum', 'attachment');
                $fs->delete_area_files($context->id, 'mod_hsuforum', 'post');

                //remove ratings
                $ratingdeloptions->contextid = $context->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        // first delete all read flags
        $DB->delete_records_select('hsuforum_read', "forumid IN ($forumssql)", $params);

        // remove tracking prefs
        $DB->delete_records_select('hsuforum_track_prefs', "forumid IN ($forumssql)", $params);

        // remove posts from queue
        $DB->delete_records_select('hsuforum_queue', "discussionid IN ($discussionssql)", $params);

        // all posts - initial posts must be kept in single simple discussion forums
        $DB->delete_records_select('hsuforum_posts', "discussion IN ($discussionssql) AND parent <> 0", $params); // first all children
        $DB->delete_records_select('hsuforum_posts', "discussion IN ($discussionssql AND f.type <> 'single') AND parent = 0", $params); // now the initial posts for non single simple

        // finally all discussions except single simple forums
        $DB->delete_records_select('hsuforum_discussions', "forum IN ($forumssql AND f.type <> 'single')", $params);

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            if (empty($types)) {
                hsuforum_reset_gradebook($data->courseid);
            } else {
                foreach ($types as $type) {
                    hsuforum_reset_gradebook($data->courseid, $type);
                }
            }
        }

        $status[] = array('component'=>$componentstr, 'item'=>$typesstr, 'error'=>false);
    }

    // remove all ratings in this course's forums
    if (!empty($data->reset_hsuforum_ratings)) {
        if ($forums) {
            foreach ($forums as $forumid=>$unused) {
                if (!$cm = get_coursemodule_from_instance('hsuforum', $forumid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);

                //remove ratings
                $ratingdeloptions->contextid = $context->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            hsuforum_reset_gradebook($data->courseid);
        }
    }

    // remove all digest settings unconditionally - even for users still enrolled in course.
    if (!empty($data->reset_forum_digests)) {
        $DB->delete_records_select('hsuforum_digests', "forum IN ($allforumssql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resetdigests', 'hsuforum'), 'error' => false);
    }

    // remove all subscriptions unconditionally - even for users still enrolled in course
    if (!empty($data->reset_hsuforum_subscriptions)) {
        $DB->delete_records_select('hsuforum_subscriptions', "forum IN ($allforumssql)", $params);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('resetsubscriptions','hsuforum'), 'error'=>false);
    }

    // remove all tracking prefs unconditionally - even for users still enrolled in course
    if (!empty($data->reset_hsuforum_track_prefs)) {
        $DB->delete_records_select('hsuforum_track_prefs', "forumid IN ($allforumssql)", $params);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('resettrackprefs','hsuforum'), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('hsuforum', array('assesstimestart', 'assesstimefinish'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * Called by course/reset.php
 *
 * @param $mform form passed by reference
 */
function hsuforum_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'forumheader', get_string('modulenameplural', 'hsuforum'));

    $mform->addElement('checkbox', 'reset_hsuforum_all', get_string('resetforumsall','hsuforum'));

    $mform->addElement('select', 'reset_hsuforum_types', get_string('resetforums', 'hsuforum'), hsuforum_get_hsuforum_types_all(), array('multiple' => 'multiple'));
    $mform->setAdvanced('reset_hsuforum_types');
    $mform->disabledIf('reset_hsuforum_types', 'reset_hsuforum_all', 'checked');

    $mform->addElement('checkbox', 'reset_hsuforum_digests', get_string('resetdigests', 'hsuforum'));
    $mform->setAdvanced('reset_hsuforum_digests');

    $mform->addElement('checkbox', 'reset_hsuforum_subscriptions', get_string('resetsubscriptions','hsuforum'));
    $mform->setAdvanced('reset_hsuforum_subscriptions');

    $mform->addElement('checkbox', 'reset_hsuforum_track_prefs', get_string('resettrackprefs','hsuforum'));
    $mform->setAdvanced('reset_hsuforum_track_prefs');
    $mform->disabledIf('reset_hsuforum_track_prefs', 'reset_hsuforum_all', 'checked');

    $mform->addElement('checkbox', 'reset_hsuforum_ratings', get_string('deleteallratings'));
    $mform->disabledIf('reset_hsuforum_ratings', 'reset_hsuforum_all', 'checked');
}

/**
 * Course reset form defaults.
 * @return array
 */
function hsuforum_reset_course_form_defaults($course) {
    return array('reset_hsuforum_all'=>1, 'reset_hsuforum_digests' => 0, 'reset_hsuforum_subscriptions'=>0, 'reset_hsuforum_track_prefs'=>0, 'reset_hsuforum_ratings'=>1);
}

/**
 * Converts a forum to use the Roles System
 *
 * @global object
 * @global object
 * @param object $forum        a forum object with the same attributes as a record
 *                        from the forum database table
 * @param int $forummodid   the id of the forum module, from the modules table
 * @param array $teacherroles array of roles that have archetype teacher
 * @param array $studentroles array of roles that have archetype student
 * @param array $guestroles   array of roles that have archetype guest
 * @param int $cmid         the course_module id for this forum instance
 * @return boolean      forum was converted or not
 */
function hsuforum_convert_to_roles($forum, $forummodid, $teacherroles=array(),
                                $studentroles=array(), $guestroles=array(), $cmid=NULL) {

    global $CFG, $DB, $OUTPUT;

    if (!isset($forum->open) && !isset($forum->assesspublic)) {
        // We assume that this forum has already been converted to use the
        // Roles System. Columns forum.open and forum.assesspublic get dropped
        // once the forum module has been upgraded to use Roles.
        return false;
    }

    if ($forum->type == 'teacher') {

        // Teacher forums should be converted to normal forums that
        // use the Roles System to implement the old behavior.
        // Note:
        //   Seems that teacher forums were never backed up in 1.6 since they
        //   didn't have an entry in the course_modules table.
        require_once($CFG->dirroot.'/course/lib.php');

        if ($DB->count_records('hsuforum_discussions', array('forum' => $forum->id)) == 0) {
            // Delete empty teacher forums.
            $DB->delete_records('hsuforum', array('id' => $forum->id));
        } else {
            // Create a course module for the forum and assign it to
            // section 0 in the course.
            $mod = new stdClass();
            $mod->course = $forum->course;
            $mod->module = $forummodid;
            $mod->instance = $forum->id;
            $mod->section = 0;
            $mod->visible = 0;     // Hide the forum
            $mod->visibleold = 0;  // Hide the forum
            $mod->groupmode = 0;

            if (!$cmid = add_course_module($mod)) {
                print_error('cannotcreateinstanceforteacher', 'hsuforum');
            } else {
                $sectionid = course_add_cm_to_section($forum->course, $mod->coursemodule, 0);
            }

            // Change the forum type to general.
            $forum->type = 'general';
            $DB->update_record('hsuforum', $forum);

            $context = context_module::instance($cmid);

            // Create overrides for default student and guest roles (prevent).
            foreach ($studentroles as $studentrole) {
                assign_capability('mod/hsuforum:viewdiscussion', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:viewhiddentimedposts', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:startdiscussion', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:replypost', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:viewrating', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:viewanyrating', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:rate', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:createattachment', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:deleteownpost', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:deleteanypost', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:splitdiscussions', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:movediscussions', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:editanypost', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:viewqandawithoutposting', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:viewsubscribers', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:managesubscriptions', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/hsuforum:postwithoutthrottling', CAP_PREVENT, $studentrole->id, $context->id);
            }
            foreach ($guestroles as $guestrole) {
                assign_capability('mod/hsuforum:viewdiscussion', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:viewhiddentimedposts', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:startdiscussion', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:replypost', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:viewrating', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:viewanyrating', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:rate', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:createattachment', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:deleteownpost', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:deleteanypost', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:splitdiscussions', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:movediscussions', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:editanypost', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:viewqandawithoutposting', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:viewsubscribers', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:managesubscriptions', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/hsuforum:postwithoutthrottling', CAP_PREVENT, $guestrole->id, $context->id);
            }
        }
    } else {
        // Non-teacher forum.

        if (empty($cmid)) {
            // We were not given the course_module id. Try to find it.
            if (!$cm = get_coursemodule_from_instance('hsuforum', $forum->id)) {
                echo $OUTPUT->notification('Could not get the course module for the forum');
                return false;
            } else {
                $cmid = $cm->id;
            }
        }
        $context = context_module::instance($cmid);

        // $forum->open defines what students can do:
        //   0 = No discussions, no replies
        //   1 = No discussions, but replies are allowed
        //   2 = Discussions and replies are allowed
        switch ($forum->open) {
            case 0:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/hsuforum:startdiscussion', CAP_PREVENT, $studentrole->id, $context->id);
                    assign_capability('mod/hsuforum:replypost', CAP_PREVENT, $studentrole->id, $context->id);
                }
                break;
            case 1:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/hsuforum:startdiscussion', CAP_PREVENT, $studentrole->id, $context->id);
                    assign_capability('mod/hsuforum:replypost', CAP_ALLOW, $studentrole->id, $context->id);
                }
                break;
            case 2:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/hsuforum:startdiscussion', CAP_ALLOW, $studentrole->id, $context->id);
                    assign_capability('mod/hsuforum:replypost', CAP_ALLOW, $studentrole->id, $context->id);
                }
                break;
        }

        // $forum->assessed defines whether forum rating is turned
        // on (1 or 2) and who can rate posts:
        //   1 = Everyone can rate posts
        //   2 = Only teachers can rate posts
        switch ($forum->assessed) {
            case 1:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/hsuforum:rate', CAP_ALLOW, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/hsuforum:rate', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
            case 2:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/hsuforum:rate', CAP_PREVENT, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/hsuforum:rate', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
        }

        // $forum->assesspublic defines whether students can see
        // everybody's ratings:
        //   0 = Students can only see their own ratings
        //   1 = Students can see everyone's ratings
        switch ($forum->assesspublic) {
            case 0:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/hsuforum:viewanyrating', CAP_PREVENT, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/hsuforum:viewanyrating', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
            case 1:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/hsuforum:viewanyrating', CAP_ALLOW, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/hsuforum:viewanyrating', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
        }

        if (empty($cm)) {
            $cm = $DB->get_record('course_modules', array('id' => $cmid));
        }

        // $cm->groupmode:
        // 0 - No groups
        // 1 - Separate groups
        // 2 - Visible groups
        switch ($cm->groupmode) {
            case 0:
                break;
            case 1:
                foreach ($studentroles as $studentrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_PREVENT, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
            case 2:
                foreach ($studentroles as $studentrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
        }
    }
    return true;
}

/**
 * Returns array of forum layout modes
 *
 * @return array
 */
function hsuforum_get_layout_modes($forum = null) {
    $modes = array();
    if (!is_null($forum) and empty($forum->anonymous)) {
        $modes[HSUFORUM_MODE_FLATFIRSTNAME] = get_string('modeflatfirstname', 'hsuforum');
        $modes[HSUFORUM_MODE_FLATLASTNAME] = get_string('modeflatlastname', 'hsuforum');
    }
    return $modes + array(
        HSUFORUM_MODE_FLATOLDEST => get_string('modeflatoldestfirst', 'hsuforum'),
        HSUFORUM_MODE_FLATNEWEST => get_string('modeflatnewestfirst', 'hsuforum'),
        HSUFORUM_MODE_THREADED   => get_string('modethreaded', 'hsuforum'),
        HSUFORUM_MODE_NESTED     => get_string('modenested', 'hsuforum')
    );
}

/**
 * Returns array of forum types chooseable on the forum editing form
 *
 * @return array
 */
function hsuforum_get_hsuforum_types() {
    return array ('general'  => get_string('generalforum', 'hsuforum'),
                  'eachuser' => get_string('eachuserforum', 'hsuforum'),
                  'single'   => get_string('singleforum', 'hsuforum'),
                  'qanda'    => get_string('qandaforum', 'hsuforum'),
                  'blog'     => get_string('blogforum', 'hsuforum'));
}

/**
 * Returns array of all forum layout modes
 *
 * @return array
 */
function hsuforum_get_hsuforum_types_all() {
    return array ('news'     => get_string('namenews','hsuforum'),
                  'social'   => get_string('namesocial','hsuforum'),
                  'general'  => get_string('generalforum', 'hsuforum'),
                  'eachuser' => get_string('eachuserforum', 'hsuforum'),
                  'single'   => get_string('singleforum', 'hsuforum'),
                  'qanda'    => get_string('qandaforum', 'hsuforum'),
                  'blog'     => get_string('blogforum', 'hsuforum'));
}

/**
 * Returns array of hsuforum grade types
 */
function hsuforum_get_grading_types(){
    return array(
        HSUFORUM_GRADETYPE_NONE   => get_string('gradetypenone', 'hsuforum'),
        HSUFORUM_GRADETYPE_MANUAL => get_string('gradetypemanual', 'hsuforum'),
        HSUFORUM_GRADETYPE_RATING => get_string('gradetyperating', 'hsuforum')
    );
}

/**
 * Returns array of forum open modes
 *
 * @return array
 */
function hsuforum_get_open_modes() {
    return array ('2' => get_string('openmode2', 'hsuforum'),
                  '1' => get_string('openmode1', 'hsuforum'),
                  '0' => get_string('openmode0', 'hsuforum') );
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function hsuforum_get_extra_capabilities() {
    return array('moodle/site:accessallgroups', 'moodle/site:viewfullnames', 'moodle/site:trustcontent', 'moodle/rating:view', 'moodle/rating:viewany', 'moodle/rating:viewall', 'moodle/rating:rate');
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $forumnode The node to add module settings to
 */
function hsuforum_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $forumnode) {
    global $USER, $PAGE, $CFG, $DB, $OUTPUT;

    $forumobject = $DB->get_record("hsuforum", array("id" => $PAGE->cm->instance));
    if (empty($PAGE->cm->context)) {
        $PAGE->cm->context = context_module::instance($PAGE->cm->instance);
    }

    // for some actions you need to be enrolled, beiing admin is not enough sometimes here
    $enrolled = is_enrolled($PAGE->cm->context, $USER, '', false);
    $activeenrolled = is_enrolled($PAGE->cm->context, $USER, '', true);

    $canmanage  = has_capability('mod/hsuforum:managesubscriptions', $PAGE->cm->context);
    $subscriptionmode = hsuforum_get_forcesubscribed($forumobject);
    $cansubscribe = ($activeenrolled && $subscriptionmode != HSUFORUM_FORCESUBSCRIBE && ($subscriptionmode != HSUFORUM_DISALLOWSUBSCRIBE || $canmanage));

    $discussionid = optional_param('d', 0, PARAM_INT);
    $viewingdiscussion = ($PAGE->url->compare(new moodle_url('/mod/hsuforum/discuss.php'), URL_MATCH_BASE) and $discussionid);

    if (!is_guest($PAGE->cm->context)) {
        $forumnode->add(get_string('export', 'hsuforum'), new moodle_url('/mod/hsuforum/route.php', array('contextid' => $PAGE->cm->context->id, 'action' => 'export')), navigation_node::TYPE_SETTING, null, null, new pix_icon('i/export', get_string('export', 'hsuforum')));
    }
    $forumnode->add(get_string('viewposters', 'hsuforum'), new moodle_url('/mod/hsuforum/route.php', array('contextid' => $PAGE->cm->context->id, 'action' => 'viewposters')), navigation_node::TYPE_SETTING, null, null, new pix_icon('t/preview', get_string('viewposters', 'hsuforum')));

    if ($canmanage) {
        $mode = $forumnode->add(get_string('subscriptionmode', 'hsuforum'), null, navigation_node::TYPE_CONTAINER);

        $allowchoice = $mode->add(get_string('subscriptionoptional', 'hsuforum'), new moodle_url('/mod/hsuforum/subscribe.php', array('id'=>$forumobject->id, 'mode'=>HSUFORUM_CHOOSESUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $forceforever = $mode->add(get_string("subscriptionforced", "hsuforum"), new moodle_url('/mod/hsuforum/subscribe.php', array('id'=>$forumobject->id, 'mode'=>HSUFORUM_FORCESUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $forceinitially = $mode->add(get_string("subscriptionauto", "hsuforum"), new moodle_url('/mod/hsuforum/subscribe.php', array('id'=>$forumobject->id, 'mode'=>HSUFORUM_INITIALSUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $disallowchoice = $mode->add(get_string('subscriptiondisabled', 'hsuforum'), new moodle_url('/mod/hsuforum/subscribe.php', array('id'=>$forumobject->id, 'mode'=>HSUFORUM_DISALLOWSUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);

        switch ($subscriptionmode) {
            case HSUFORUM_CHOOSESUBSCRIBE : // 0
                $allowchoice->action = null;
                $allowchoice->add_class('activesetting');
                break;
            case HSUFORUM_FORCESUBSCRIBE : // 1
                $forceforever->action = null;
                $forceforever->add_class('activesetting');
                break;
            case HSUFORUM_INITIALSUBSCRIBE : // 2
                $forceinitially->action = null;
                $forceinitially->add_class('activesetting');
                break;
            case HSUFORUM_DISALLOWSUBSCRIBE : // 3
                $disallowchoice->action = null;
                $disallowchoice->add_class('activesetting');
                break;
        }

    } else if ($activeenrolled) {

        switch ($subscriptionmode) {
            case HSUFORUM_CHOOSESUBSCRIBE : // 0
                $notenode = $forumnode->add(get_string('subscriptionoptional', 'hsuforum'));
                break;
            case HSUFORUM_FORCESUBSCRIBE : // 1
                $notenode = $forumnode->add(get_string('subscriptionforced', 'hsuforum'));
                break;
            case HSUFORUM_INITIALSUBSCRIBE : // 2
                $notenode = $forumnode->add(get_string('subscriptionauto', 'hsuforum'));
                break;
            case HSUFORUM_DISALLOWSUBSCRIBE : // 3
                $notenode = $forumnode->add(get_string('subscriptiondisabled', 'hsuforum'));
                break;
        }
    }

    if ($cansubscribe) {
        if (hsuforum_is_subscribed($USER->id, $forumobject)) {
            $linktext = get_string('unsubscribe', 'hsuforum');
        } else {
            $linktext = get_string('subscribe', 'hsuforum');
        }
        $url = new moodle_url('/mod/hsuforum/subscribe.php', array('id'=>$forumobject->id, 'sesskey'=>sesskey()));
        $forumnode->add($linktext, $url, navigation_node::TYPE_SETTING);
    }

    if ($viewingdiscussion) {
        require_once(__DIR__.'/lib/discussion/subscribe.php');
        $subscribe = new hsuforum_lib_discussion_subscribe($forumobject, $PAGE->cm->context);

        if ($subscribe->can_subscribe()) {
            $subscribeurl = new moodle_url('/mod/hsuforum/route.php', array(
                'contextid'    => $PAGE->cm->context->id,
                'action'       => 'subscribedisc',
                'discussionid' => $discussionid,
                'sesskey'      => sesskey(),
                'returnurl'    => $PAGE->url,
            ));

            if ($subscribe->is_subscribed($discussionid)) {
                $linktext = get_string('unsubscribedisc', 'hsuforum');
            } else {
                $linktext = get_string('subscribedisc', 'hsuforum');
            }
            $forumnode->add($linktext, $subscribeurl, navigation_node::TYPE_SETTING);
        }
    }


    if (has_capability('mod/hsuforum:viewsubscribers', $PAGE->cm->context)){
        $url = new moodle_url('/mod/hsuforum/subscribers.php', array('id'=>$forumobject->id));
        $forumnode->add(get_string('showsubscribers', 'hsuforum'), $url, navigation_node::TYPE_SETTING);

        $discsubscribers = ($viewingdiscussion or (optional_param('action', '', PARAM_ALPHA) == 'discsubscribers'));
        if ($discsubscribers and !hsuforum_is_forcesubscribed($forumobject)) {
            $url = new moodle_url('/mod/hsuforum/route.php', array(
                'contextid'    => $PAGE->cm->context->id,
                'action'       => 'discsubscribers',
                'discussionid' => $discussionid,
            ));
            $forumnode->add(get_string('showdiscussionsubscribers', 'hsuforum'), $url, navigation_node::TYPE_SETTING, null, 'discsubscribers');
        }
    }

    if ($enrolled && hsuforum_tp_can_track_forums($forumobject)) { // keep tracking info for users with suspended enrolments
        if ($forumobject->trackingtype == HSUFORUM_TRACKING_OPTIONAL
                || ((!$CFG->hsuforum_allowforcedreadtracking) && $forumobject->trackingtype == HSUFORUM_TRACKING_FORCED)) {
            if (hsuforum_tp_is_tracked($forumobject)) {
                $linktext = get_string('notrackforum', 'hsuforum');
            } else {
                $linktext = get_string('trackforum', 'hsuforum');
            }
            $url = new moodle_url('/mod/hsuforum/settracking.php', array('id'=>$forumobject->id));
            $forumnode->add($linktext, $url, navigation_node::TYPE_SETTING);
        }
    }

    if (!isloggedin() && $PAGE->course->id == SITEID) {
        $userid = guest_user()->id;
    } else {
        $userid = $USER->id;
    }

    $hascourseaccess = ($PAGE->course->id == SITEID) || can_access_course($PAGE->course, $userid);
    $enablerssfeeds = !empty($CFG->enablerssfeeds) && !empty($CFG->hsuforum_enablerssfeeds);

    if ($enablerssfeeds && $forumobject->rsstype && $forumobject->rssarticles && $hascourseaccess) {

        if (!function_exists('rss_get_url')) {
            require_once("$CFG->libdir/rsslib.php");
        }

        if ($forumobject->rsstype == 1) {
            $string = get_string('rsssubscriberssdiscussions','hsuforum');
        } else {
            $string = get_string('rsssubscriberssposts','hsuforum');
        }

        $url = new moodle_url(rss_get_url($PAGE->cm->context->id, $userid, "mod_hsuforum", $forumobject->id));
        $forumnode->add($string, $url, settings_navigation::TYPE_SETTING, null, null, new pix_icon('i/rss', ''));
    }
}

/**
 * Abstract class used by forum subscriber selection controls
 * @package mod-hsuforum
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class hsuforum_subscriber_selector_base extends user_selector_base {

    /**
     * The id of the forum this selector is being used for
     * @var int
     */
    protected $forumid = null;
    /**
     * The context of the forum this selector is being used for
     * @var object
     */
    protected $context = null;
    /**
     * The id of the current group
     * @var int
     */
    protected $currentgroup = null;

    /**
     * Constructor method
     * @param string $name
     * @param array $options
     */
    public function __construct($name, $options) {
        $options['accesscontext'] = $options['context'];
        parent::__construct($name, $options);
        if (isset($options['context'])) {
            $this->context = $options['context'];
        }
        if (isset($options['currentgroup'])) {
            $this->currentgroup = $options['currentgroup'];
        }
        if (isset($options['forumid'])) {
            $this->forumid = $options['forumid'];
        }
    }

    /**
     * Returns an array of options to seralise and store for searches
     *
     * @return array
     */
    protected function get_options() {
        global $CFG;
        $options = parent::get_options();
        $options['file'] =  substr(__FILE__, strlen($CFG->dirroot.'/'));
        $options['context'] = $this->context;
        $options['currentgroup'] = $this->currentgroup;
        $options['forumid'] = $this->forumid;
        return $options;
    }

}

/**
 * A user selector control for potential subscribers to the selected forum
 * @package mod-hsuforum
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hsuforum_potential_subscriber_selector extends hsuforum_subscriber_selector_base {
    /**
     * If set to true EVERYONE in this course is force subscribed to this forum
     * @var bool
     */
    protected $forcesubscribed = false;
    /**
     * Can be used to store existing subscribers so that they can be removed from
     * the potential subscribers list
     */
    protected $existingsubscribers = array();

    /**
     * Constructor method
     * @param string $name
     * @param array $options
     */
    public function __construct($name, $options) {
        parent::__construct($name, $options);
        if (isset($options['forcesubscribed'])) {
            $this->forcesubscribed=true;
        }
    }

    /**
     * Returns an arary of options for this control
     * @return array
     */
    protected function get_options() {
        $options = parent::get_options();
        if ($this->forcesubscribed===true) {
            $options['forcesubscribed']=1;
        }
        return $options;
    }

    /**
     * Finds all potential users
     *
     * Potential subscribers are all enroled users who are not already subscribed.
     *
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;

        $whereconditions = array();
        list($wherecondition, $params) = $this->search_sql($search, 'u');
        if ($wherecondition) {
            $whereconditions[] = $wherecondition;
        }

        if (!$this->forcesubscribed) {
            $existingids = array();
            foreach ($this->existingsubscribers as $group) {
                foreach ($group as $user) {
                    $existingids[$user->id] = 1;
                }
            }
            if ($existingids) {
                list($usertest, $userparams) = $DB->get_in_or_equal(
                        array_keys($existingids), SQL_PARAMS_NAMED, 'existing', false);
                $whereconditions[] = 'u.id ' . $usertest;
                $params = array_merge($params, $userparams);
            }
        }

        if ($whereconditions) {
            $wherecondition = 'WHERE ' . implode(' AND ', $whereconditions);
        }

        list($esql, $eparams) = get_enrolled_sql($this->context, '', $this->currentgroup, true);
        $params = array_merge($params, $eparams);

        $fields      = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(u.id)';

        $sql = " FROM {user} u
                 JOIN ($esql) je ON je.id = u.id
                      $wherecondition";

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $order = ' ORDER BY ' . $sort;

        // Check to see if there are too many to show sensibly.
        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        // If not, show them.
        $availableusers = $DB->get_records_sql($fields . $sql . $order, array_merge($params, $sortparams));

        if (empty($availableusers)) {
            return array();
        }

        if ($this->forcesubscribed) {
            return array(get_string("existingsubscribers", 'hsuforum') => $availableusers);
        } else {
            return array(get_string("potentialsubscribers", 'hsuforum') => $availableusers);
        }
    }

    /**
     * Sets the existing subscribers
     * @param array $users
     */
    public function set_existing_subscribers(array $users) {
        $this->existingsubscribers = $users;
    }

    /**
     * Sets this forum as force subscribed or not
     */
    public function set_force_subscribed($setting=true) {
        $this->forcesubscribed = true;
    }
}

/**
 * User selector control for removing subscribed users
 * @package mod-hsuforum
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hsuforum_existing_subscriber_selector extends hsuforum_subscriber_selector_base {

    /**
     * Finds all subscribed users
     *
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;
        list($wherecondition, $params) = $this->search_sql($search, 'u');
        $params['forumid'] = $this->forumid;

        // only active enrolled or everybody on the frontpage
        list($esql, $eparams) = get_enrolled_sql($this->context, '', $this->currentgroup, true);
        $fields = $this->required_fields_sql('u');
        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $params = array_merge($params, $eparams, $sortparams);

        $subscribers = $DB->get_records_sql("SELECT $fields
                                               FROM {user} u
                                               JOIN ($esql) je ON je.id = u.id
                                               JOIN {hsuforum_subscriptions} s ON s.userid = u.id
                                              WHERE $wherecondition AND s.forum = :forumid
                                           ORDER BY $sort", $params);

        return array(get_string("existingsubscribers", 'hsuforum') => $subscribers);
    }

}

/**
 * Adds information about unread messages, that is only required for the course view page (and
 * similar), to the course-module object.
 * @param cm_info $cm Course-module object
 */
function hsuforum_cm_info_view(cm_info $cm) {
    global $CFG;

    if (hsuforum_tp_can_track_forums()) {
        if ($unread = hsuforum_tp_count_hsuforum_unread_posts($cm, $cm->get_course())) {
            $out = '<span class="unread"> <a href="' . $cm->get_url() . '">';
            if ($unread == 1) {
                $out .= get_string('unreadpostsone', 'hsuforum');
            } else {
                $out .= get_string('unreadpostsnumber', 'hsuforum', $unread);
            }
            $out .= '</a></span>';
            $cm->set_after_link($out);
        }
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function hsuforum_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $hsuforum_pagetype = array(
        'mod-hsuforum-*'=>get_string('page-mod-hsuforum-x', 'hsuforum'),
        'mod-hsuforum-view'=>get_string('page-mod-hsuforum-view', 'hsuforum'),
        'mod-hsuforum-discuss'=>get_string('page-mod-hsuforum-discuss', 'hsuforum')
    );
    return $hsuforum_pagetype;
}

/**
 * Gets all of the courses where the provided user has posted in a forum.
 *
 * @global moodle_database $DB The database connection
 * @param stdClass $user The user who's posts we are looking for
 * @param bool $discussionsonly If true only look for discussions started by the user
 * @param bool $includecontexts If set to trye contexts for the courses will be preloaded
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return array An array of courses
 */
function hsuforum_get_courses_user_posted_in($user, $discussionsonly = false, $includecontexts = true, $limitfrom = null, $limitnum = null) {
    global $DB;

    // If we are only after discussions we need only look at the hsuforum_discussions
    // table and join to the userid there. If we are looking for posts then we need
    // to join to the hsuforum_posts table.
    if (!$discussionsonly) {
        $joinsql = 'JOIN {hsuforum_discussions} fd ON fd.course = c.id
                    JOIN {hsuforum_posts} fp ON fp.discussion = fd.id';
        $wheresql = 'fp.userid = :userid';
        $params = array('userid' => $user->id);
    } else {
        $joinsql = 'JOIN {hsuforum_discussions} fd ON fd.course = c.id';
        $wheresql = 'fd.userid = :userid';
        $params = array('userid' => $user->id);
    }

    // Join to the context table so that we can preload contexts if required.
    if ($includecontexts) {
        $ctxselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ctxjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        $params['contextlevel'] = CONTEXT_COURSE;
    } else {
        $ctxselect = '';
        $ctxjoin = '';
    }

    // Now we need to get all of the courses to search.
    // All courses where the user has posted within a forum will be returned.
    $sql = "SELECT DISTINCT c.* $ctxselect
            FROM {course} c
            $joinsql
            $ctxjoin
            WHERE $wheresql";
    $courses = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    if ($includecontexts) {
        array_map('context_helper::preload_from_record', $courses);
    }
    return $courses;
}

/**
 * Gets all of the forums a user has posted in for one or more courses.
 *
 * @global moodle_database $DB
 * @param stdClass $user
 * @param array $courseids An array of courseids to search or if not provided
 *                       all courses the user has posted within
 * @param bool $discussionsonly If true then only forums where the user has started
 *                       a discussion will be returned.
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return array An array of forums the user has posted within in the provided courses
 */
function hsuforum_get_forums_user_posted_in($user, array $courseids = null, $discussionsonly = false, $limitfrom = null, $limitnum = null) {
    global $DB;

    if (!is_null($courseids)) {
        list($coursewhere, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
        $coursewhere = ' AND f.course '.$coursewhere;
    } else {
        $coursewhere = '';
        $params = array();
    }
    $params['userid'] = $user->id;
    $params['forum'] = 'hsuforum';

    if ($discussionsonly) {
        $join = 'JOIN {hsuforum_discussions} ff ON ff.forum = f.id';
    } else {
        $join = 'JOIN {hsuforum_discussions} fd ON fd.forum = f.id
                 JOIN {hsuforum_posts} ff ON ff.discussion = fd.id';
    }

    $sql = "SELECT f.*, cm.id AS cmid
              FROM {hsuforum} f
              JOIN {course_modules} cm ON cm.instance = f.id
              JOIN {modules} m ON m.id = cm.module
              JOIN (
                  SELECT f.id
                    FROM {hsuforum} f
                    {$join}
                   WHERE ff.userid = :userid
                GROUP BY f.id
                   ) j ON j.id = f.id
             WHERE m.name = :forum
                 {$coursewhere}";

    $courseforums = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    return $courseforums;
}

/**
 * Returns posts made by the selected user in the requested courses.
 *
 * This method can be used to return all of the posts made by the requested user
 * within the given courses.
 * For each course the access of the current user and requested user is checked
 * and then for each post access to the post and forum is checked as well.
 *
 * This function is safe to use with usercapabilities.
 *
 * @global moodle_database $DB
 * @param stdClass $user The user whose posts we want to get
 * @param array $courses The courses to search
 * @param bool $musthaveaccess If set to true errors will be thrown if the user
 *                             cannot access one or more of the courses to search
 * @param bool $discussionsonly If set to true only discussion starting posts
 *                              will be returned.
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return stdClass An object the following properties
 *               ->totalcount: the total number of posts made by the requested user
 *                             that the current user can see.
 *               ->courses: An array of courses the current user can see that the
 *                          requested user has posted in.
 *               ->forums: An array of forums relating to the posts returned in the
 *                         property below.
 *               ->posts: An array containing the posts to show for this request.
 */
function hsuforum_get_posts_by_user($user, array $courses, $musthaveaccess = false, $discussionsonly = false, $limitfrom = 0, $limitnum = 50) {
    global $DB, $USER, $CFG;

    $return = new stdClass;
    $return->totalcount = 0;    // The total number of posts that the current user is able to view
    $return->courses = array(); // The courses the current user can access
    $return->forums = array();  // The forums that the current user can access that contain posts
    $return->posts = array();   // The posts to display

    // First up a small sanity check. If there are no courses to check we can
    // return immediately, there is obviously nothing to search.
    if (empty($courses)) {
        return $return;
    }

    // A couple of quick setups
    $isloggedin = isloggedin();
    $isguestuser = $isloggedin && isguestuser();
    $iscurrentuser = $isloggedin && $USER->id == $user->id;

    // Checkout whether or not the current user has capabilities over the requested
    // user and if so they have the capabilities required to view the requested
    // users content.
    $usercontext = context_user::instance($user->id, MUST_EXIST);
    $hascapsonuser = !$iscurrentuser && $DB->record_exists('role_assignments', array('userid' => $USER->id, 'contextid' => $usercontext->id));
    $hascapsonuser = $hascapsonuser && has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), $usercontext);

    // Before we actually search each course we need to check the user's access to the
    // course. If the user doesn't have the appropraite access then we either throw an
    // error if a particular course was requested or we just skip over the course.
    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course->id, MUST_EXIST);
        if ($iscurrentuser || $hascapsonuser) {
            // If it is the current user, or the current user has capabilities to the
            // requested user then all we need to do is check the requested users
            // current access to the course.
            // Note: There is no need to check group access or anything of the like
            // as either the current user is the requested user, or has granted
            // capabilities on the requested user. Either way they can see what the
            // requested user posted, although its VERY unlikely in the `parent` situation
            // that the current user will be able to view the posts in context.
            if (!is_viewing($coursecontext, $user) && !is_enrolled($coursecontext, $user)) {
                // Need to have full access to a course to see the rest of own info
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'hsuforum');
                }
                continue;
            }
        } else {
            // Check whether the current user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!can_access_course($course)) {
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'hsuforum');
                }
                continue;
            }

            // Check whether the requested user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!can_access_course($course, $user)) {
                if ($musthaveaccess) {
                    print_error('notenrolled', 'hsuforum');
                }
                continue;
            }

            // If groups are in use and enforced throughout the course then make sure
            // we can meet in at least one course level group.
            // Note that we check if either the current user or the requested user have
            // the capability to access all groups. This is because with that capability
            // a user in group A could post in the group B forum. Grrrr.
            if (groups_get_course_groupmode($course) == SEPARATEGROUPS && $course->groupmodeforce
              && !has_capability('moodle/site:accessallgroups', $coursecontext) && !has_capability('moodle/site:accessallgroups', $coursecontext, $user->id)) {
                // If its the guest user to bad... the guest user cannot access groups
                if (!$isloggedin or $isguestuser) {
                    // do not use require_login() here because we might have already used require_login($course)
                    if ($musthaveaccess) {
                        redirect(get_login_url());
                    }
                    continue;
                }
                // Get the groups of the current user
                $mygroups = array_keys(groups_get_all_groups($course->id, $USER->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Get the groups the requested user is a member of
                $usergroups = array_keys(groups_get_all_groups($course->id, $user->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Check whether they are members of the same group. If they are great.
                $intersect = array_intersect($mygroups, $usergroups);
                if (empty($intersect)) {
                    // But they're not... if it was a specific course throw an error otherwise
                    // just skip this course so that it is not searched.
                    if ($musthaveaccess) {
                        print_error("groupnotamember", '', $CFG->wwwroot."/course/view.php?id=$course->id");
                    }
                    continue;
                }
            }
        }
        // Woo hoo we got this far which means the current user can search this
        // this course for the requested user. Although this is only the course accessibility
        // handling that is complete, the forum accessibility tests are yet to come.
        $return->courses[$course->id] = $course;
    }
    // No longer beed $courses array - lose it not it may be big
    unset($courses);

    // Make sure that we have some courses to search
    if (empty($return->courses)) {
        // If we don't have any courses to search then the reality is that the current
        // user doesn't have access to any courses is which the requested user has posted.
        // Although we do know at this point that the requested user has posts.
        if ($musthaveaccess) {
            print_error('permissiondenied');
        } else {
            return $return;
        }
    }

    // Next step: Collect all of the forums that we will want to search.
    // It is important to note that this step isn't actually about searching, it is
    // about determining which forums we can search by testing accessibility.
    $forums = hsuforum_get_forums_user_posted_in($user, array_keys($return->courses), $discussionsonly);

    // Will be used to build the where conditions for the search
    $forumsearchwhere = array();
    // Will be used to store the where condition params for the search
    $forumsearchparams = array();
    // Will record forums where the user can freely access everything
    $forumsearchfullaccess = array();
    // DB caching friendly
    $now = round(time(), -2);
    // For each course to search we want to find the forums the user has posted in
    // and providing the current user can access the forum create a search condition
    // for the forum to get the requested users posts.
    foreach ($return->courses as $course) {
        // Now we need to get the forums
        $modinfo = get_fast_modinfo($course);
        if (empty($modinfo->instances['hsuforum'])) {
            // hmmm, no forums? well at least its easy... skip!
            continue;
        }
        // Iterate
        foreach ($modinfo->get_instances_of('hsuforum') as $forumid => $cm) {
            if (!$cm->uservisible or !isset($forums[$forumid])) {
                continue;
            }
            // Get the forum in question
            $forum = $forums[$forumid];

            // This is needed for functionality later on in the forum code. It is converted to an object
            // because the cm_info is readonly from 2.6. This is a dirty hack because some other parts of the
            // code were expecting an writeable object. See {@link hsuforum_print_post()}.
            $forum->cm = new stdClass();
            foreach ($cm as $key => $value) {
                $forum->cm->$key = $value;
            }

            // Check that either the current user can view the forum, or that the
            // current user has capabilities over the requested user and the requested
            // user can view the discussion
            if (!has_capability('mod/hsuforum:viewdiscussion', $cm->context) && !($hascapsonuser && has_capability('mod/hsuforum:viewdiscussion', $cm->context, $user->id))) {
                continue;
            }

            // This will contain forum specific where clauses
            $forumsearchselect = array();
            if (!$iscurrentuser && !$hascapsonuser) {
                // Make sure we check group access
                if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $cm->context)) {
                    $groups = $modinfo->get_groups($cm->groupingid);
                    $groups[] = -1;
                    list($groupid_sql, $groupid_params) = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'grps'.$forumid.'_');
                    $forumsearchparams = array_merge($forumsearchparams, $groupid_params);
                    $forumsearchselect[] = "d.groupid $groupid_sql";
                }

                // hidden timed discussions
                if (!empty($CFG->hsuforum_enabletimedposts) && !has_capability('mod/hsuforum:viewhiddentimedposts', $cm->context)) {
                    $forumsearchselect[] = "(d.userid = :userid{$forumid} OR (d.timestart < :timestart{$forumid} AND (d.timeend = 0 OR d.timeend > :timeend{$forumid})))";
                    $forumsearchparams['userid'.$forumid] = $user->id;
                    $forumsearchparams['timestart'.$forumid] = $now;
                    $forumsearchparams['timeend'.$forumid] = $now;
                }

                // qanda access
                if ($forum->type == 'qanda' && !has_capability('mod/hsuforum:viewqandawithoutposting', $cm->context)) {
                    // We need to check whether the user has posted in the qanda forum.
                    $discussionspostedin = hsuforum_discussions_user_has_posted_in($forum->id, $user->id);
                    if (!empty($discussionspostedin)) {
                        $forumonlydiscussions = array();  // Holds discussion ids for the discussions the user is allowed to see in this forum.
                        foreach ($discussionspostedin as $d) {
                            $forumonlydiscussions[] = $d->id;
                        }
                        list($discussionid_sql, $discussionid_params) = $DB->get_in_or_equal($forumonlydiscussions, SQL_PARAMS_NAMED, 'qanda'.$forumid.'_');
                        $forumsearchparams = array_merge($forumsearchparams, $discussionid_params);
                        $forumsearchselect[] = "(d.id $discussionid_sql OR p.parent = 0)";
                    } else {
                        $forumsearchselect[] = "p.parent = 0";
                    }

                }

                if (count($forumsearchselect) > 0) {
                    $forumsearchwhere[] = "(d.forum = :forum{$forumid} AND ".implode(" AND ", $forumsearchselect).")";
                    $forumsearchparams['forum'.$forumid] = $forumid;
                } else {
                    $forumsearchfullaccess[] = $forumid;
                }
            } else {
                // The current user/parent can see all of their own posts
                $forumsearchfullaccess[] = $forumid;
            }
        }
    }

    // If we dont have any search conditions, and we don't have any forums where
    // the user has full access then we just return the default.
    if (empty($forumsearchwhere) && empty($forumsearchfullaccess)) {
        return $return;
    }

    // Prepare a where condition for the full access forums.
    if (count($forumsearchfullaccess) > 0) {
        list($fullidsql, $fullidparams) = $DB->get_in_or_equal($forumsearchfullaccess, SQL_PARAMS_NAMED, 'fula');
        $forumsearchparams = array_merge($forumsearchparams, $fullidparams);
        $forumsearchwhere[] = "(d.forum $fullidsql)";
    }

    // Prepare SQL to both count and search.
    // We alias user.id to useridx because we hsuforum_posts already has a userid field and not aliasing this would break
    // oracle and mssql.
    $userfields = user_picture::fields('u', null, 'useridx');
    $countsql = 'SELECT COUNT(*) ';
    $selectsql = 'SELECT p.*, d.forum, d.name AS discussionname, '.$userfields.' ';
    $wheresql = implode(" OR ", $forumsearchwhere);

    if ($discussionsonly) {
        if ($wheresql == '') {
            $wheresql = 'p.parent = 0';
        } else {
            $wheresql = 'p.parent = 0 AND ('.$wheresql.')';
        }
    }

    $sql = "FROM {hsuforum_posts} p
            JOIN {hsuforum_discussions} d ON d.id = p.discussion
            JOIN {hsuforum} f ON f.id = d.forum
            JOIN {user} u ON u.id = p.userid
           WHERE ($wheresql)
             AND p.userid = :userid
             AND f.anonymous = 0 ";
    $orderby = "ORDER BY p.modified DESC";
    $forumsearchparams['userid'] = $user->id;

    // Set the total number posts made by the requested user that the current user can see
    $return->totalcount = $DB->count_records_sql($countsql.$sql, $forumsearchparams);
    // Set the collection of posts that has been requested
    $return->posts = $DB->get_records_sql($selectsql.$sql.$orderby, $forumsearchparams, $limitfrom, $limitnum);

    // We need to build an array of forums for which posts will be displayed.
    // We do this here to save the caller needing to retrieve them themselves before
    // printing these forums posts. Given we have the forums already there is
    // practically no overhead here.
    foreach ($return->posts as $post) {
        if (!array_key_exists($post->forum, $return->forums)) {
            $return->forums[$post->forum] = $forums[$post->forum];
        }
    }

    return $return;
}

/**
 * Extract the user object from the post object
 *
 * @param $post
 * @param $forum
 * @param context_module $context
 * @return stdClass
 */
function hsuforum_extract_postuser($post, $forum, context_module $context) {
    $postuser     = new stdClass();
    $postuser->id = $post->userid;

    $fields = array_merge(
        get_all_user_name_fields(),
        array('imagealt', 'picture', 'email')
    );
    foreach ($fields as $field) {
        if (property_exists($post, $field)) {
            $postuser->$field = $post->$field;
        }
    }
    return hsuforum_get_postuser($postuser, $post, $forum, $context);
}

/**
 * Given a user, return post user that is ready for display (EG:
 * anonymous is enforced as well as highlighting)
 *
 * @param object $user
 * @param object $post
 * @param object $forum
 * @param context_module $context
 * @return stdClass
 */
function hsuforum_get_postuser($user, $post, $forum, context_module $context) {
    $postuser = hsuforum_anonymize_user($user, $forum, $post);

    if (property_exists($user, 'picture')) {
        $postuser->user_picture           = new user_picture($postuser);
        $postuser->user_picture->courseid = $forum->course;
        $postuser->user_picture->link     = (!hsuforum_is_anonymous_user($postuser));
    }
    $postuser->fullname = fullname($postuser, has_capability('moodle/site:viewfullnames', $context));

    if (!hsuforum_is_anonymous_user($postuser) and has_capability('moodle/course:manageactivities', $context, $postuser->id)) {
        $postuser->fullname = html_writer::tag('span', $postuser->fullname, array('class' => 'hsuforum_highlightposter'));
    }
    return $postuser;
}

/**
 * @param object $user
 * @param object $forum
 * @param object $post
 * @throws coding_exception
 * @return stdClass
 * @author Mark Nielsen
 */
function hsuforum_anonymize_user($user, $forum, $post) {
    static $anonymous = null;

    if (!isset($forum->anonymous) or !isset($forum->course)) {
        throw new coding_exception('Must pass the forum\'s anonymous and course fields');
    }
    if (!isset($post->reveal)) {
        throw new coding_exception('Must pass the post\'s reveal field');
    }
    if (empty($forum->anonymous) or !empty($post->reveal)) {
        return $user;
    }
    if (is_null($anonymous)) {
        $guest = guest_user();
        $anonymous = (object) array(
            'id' => $guest->id,
            'firstname' => get_string('anonymousfirstname', 'hsuforum'),
            'lastname' => get_string('anonymouslastname', 'hsuforum'),
            'firstnamephonetic' => get_string('anonymousfirstnamephonetic', 'hsuforum'),
            'lastnamephonetic' => get_string('anonymouslastnamephonetic', 'hsuforum'),
            'middlename' => get_string('anonymousmiddlename', 'hsuforum'),
            'alternatename' => get_string('anonymousalternatename', 'hsuforum'),
            'picture' => 0,
            'email' => $guest->email,
            'imagealt' => '',
            'profilelink' => new moodle_url('/user/view.php', array('id'=>$guest->id, 'course'=>$forum->course)),
        );
        $anonymous->fullname = fullname($anonymous, true);
        $anonymous->imagealt = $anonymous->fullname;

        // Prevent accidental reveal of user.
        foreach(get_all_user_name_fields() as $field) {
            if (!property_exists($anonymous, $field)) {
                $anonymous->$field = '';
            }
        }
    }
    $return = clone($user);
    foreach ($anonymous as $name => $value) {
        if (property_exists($user, $name)) {
            $return->$name = $value;
        }
    }
    return $return;
}

/**
 * @param $user
 * @return bool
 * @author Mark Nielsen
 */
function hsuforum_is_anonymous_user($user) {
    static $guest = null;

    if (is_null($guest)) {
        $guest = guest_user();
    }
    return ($user->id == $guest->id);
}

/**
 * @param stdClass $forum
 * @return int
 * @author Mark Nielsen
 */
function hsuforum_get_layout_mode($forum) {
    global $CFG;

    $displaymode = get_user_preferences('hsuforum_displaymode', $CFG->hsuforum_displaymode);

    if (!array_key_exists($displaymode, hsuforum_get_layout_modes($forum))) {
        return HSUFORUM_MODE_NESTED;
    }
    return $displaymode;
}

/**
 * @param int $mode The HSUFORUM_MODE_* constant
 * @return string
 * @author Mark Nielsen
 */
function hsuforum_get_layout_mode_sort($mode) {
    if ($mode == HSUFORUM_MODE_FLATFIRSTNAME) {
        $sort = 'u.firstname ASC, p.created ASC';
    } else if ($mode == HSUFORUM_MODE_FLATLASTNAME) {
        $sort = 'u.lastname ASC, p.created ASC';
    } else if ($mode == HSUFORUM_MODE_FLATNEWEST) {
        $sort = "p.created DESC";
    } else {
        $sort = "p.created ASC";
    }
    return $sort;
}

/**
 * @param $cm
 * @author Mark Nielsen
 */
function hsuforum_cm_add_cache(&$cm) {
    global $DB, $COURSE;

    if (!isset($cm->cache)) {
        $cm->cache = new stdClass;
    }
    if (!isset($cm->cache->context)) {
        $cm->cache->context = context_module::instance($cm->id);
    }
    if (!isset($cm->cache->forum)) {
        $cm->cache->forum = $DB->get_record('hsuforum', array('id' => $cm->instance), '*', MUST_EXIST);
    }
    if (!isset($cm->cache->course)) {
        if ($COURSE->id == $cm->course) {
            $cm->cache->course = $COURSE;
        } else {
            $cm->cache->course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        }
    }
    if (!isset($cm->cache->caps)) {
        $cm->cache->caps = array();
        $cm->cache->caps['mod/hsuforum:viewdiscussion']   = has_capability('mod/hsuforum:viewdiscussion', $cm->cache->context);
        $cm->cache->caps['moodle/site:viewfullnames']     = has_capability('moodle/site:viewfullnames', $cm->cache->context);
        $cm->cache->caps['mod/hsuforum:editanypost']      = has_capability('mod/hsuforum:editanypost', $cm->cache->context);
        $cm->cache->caps['mod/hsuforum:splitdiscussions'] = has_capability('mod/hsuforum:splitdiscussions', $cm->cache->context);
        $cm->cache->caps['mod/hsuforum:deleteownpost']    = has_capability('mod/hsuforum:deleteownpost', $cm->cache->context);
        $cm->cache->caps['mod/hsuforum:deleteanypost']    = has_capability('mod/hsuforum:deleteanypost', $cm->cache->context);
        $cm->cache->caps['mod/hsuforum:viewanyrating']    = has_capability('mod/hsuforum:viewanyrating', $cm->cache->context);
        $cm->cache->caps['mod/hsuforum:exportpost']       = has_capability('mod/hsuforum:exportpost', $cm->cache->context);
        $cm->cache->caps['mod/hsuforum:exportownpost']    = has_capability('mod/hsuforum:exportownpost', $cm->cache->context);
    }
    if (!isset($cm->cache->str)) {
        $cm->cache->str                     = new stdClass;
        $cm->cache->str->edit               = get_string('edit', 'hsuforum');
        $cm->cache->str->delete             = get_string('delete', 'hsuforum');
        $cm->cache->str->reply              = get_string('reply', 'hsuforum');
        $cm->cache->str->parent             = get_string('parent', 'hsuforum');
        $cm->cache->str->pruneheading       = get_string('pruneheading', 'hsuforum');
        $cm->cache->str->prune              = get_string('prune', 'hsuforum');
        $cm->cache->str->markread           = get_string('markread', 'hsuforum');
        $cm->cache->str->markunread         = get_string('markunread', 'hsuforum');
        $cm->cache->str->strftimerecentfull = get_string('strftimerecentfull');
    }
    if (!isset($cm->cache->displaymode)) {
        $cm->cache->displaymode = hsuforum_get_layout_mode($cm->cache->forum);
    }
    if (!isset($cm->cache->istracked)) {
        $cm->cache->istracked = hsuforum_tp_is_tracked($cm->cache->forum);
    }
    if (!isset($cm->cache->cantrack)) {
        $cm->cache->cantrack = hsuforum_tp_can_track_forums($cm->cache->forum);
    }
    if (!isset($cm->cache->groupmode)) {
        $cm->cache->groupmode = groups_get_activity_groupmode($cm, $cm->cache->course);
    }
    if (!isset($cm->cache->groups)) {
        $cm->cache->groups = groups_get_all_groups($cm->course, 0, $cm->groupingid);
    }
}

/**
 * Highly specialized function to extract a discussion record
 * from the hybrid object returned from hsuforum_get_discussions()
 *
 * @author Mark Nielsen
 * @param stdClass $post Our post with discussion data embedded into it
 * @param stdClass $forum The discussion's forum
 * @return object
 */
function hsuforum_extract_discussion($post, $forum) {
    $discussion = (object) array(
        'id'           => $post->discussion,
        'course'       => $forum->course,
        'forum'        => $forum->id,
        'name'         => $post->name,
        'firstpost'    => $post->firstpost,
        'userid'       => $post->userid,
        'groupid'      => $post->groupid,
        'timemodified' => $post->timemodified,
        'usermodified' => $post->usermodified,
        'timestart'    => $post->timestart,
        'timeend'      => $post->timeend,
    );

    // Rest of these are "meta" items that might not always be there.
    if (property_exists($post, 'subscriptionid')) {
        $discussion->subscriptionid = $post->subscriptionid;
    }
    if (property_exists($post, 'replies')) {
        $discussion->replies = $post->replies;
    }
    if (property_exists($post, 'unread')) {
        $discussion->unread = $post->unread;
    }
    if (property_exists($post, 'lastpostid')) {
        $discussion->lastpostid = $post->lastpostid;
    }
    return $discussion;
}

/**
 * @param stdClass $options
 * @return bool
 * @throws comment_exception
 */
function mod_hsuforum_comment_validate(stdClass $options) {
    global $USER, $DB;

    if ($options->commentarea != 'userposts_comments') {
        throw new comment_exception('invalidcommentarea');
    }
    if (!$user = $DB->get_record('user', array('id'=>$options->itemid))) {
        throw new comment_exception('invalidcommentitemid');
    }
    $context = $options->context;

    if (!$cm = get_coursemodule_from_id('hsuforum', $context->instanceid)) {
        throw new comment_exception('invalidcontext');
    }

    if (!has_capability('mod/hsuforum:rate', $context)) {
        if (!has_capability('mod/hsuforum:replypost', $context) or ($user->id != $USER->id)) {
            throw new comment_exception('nopermissiontocomment');
        }
    }

    return true;
}

function mod_hsuforum_comment_permissions(stdClass $options) {
    global $USER, $DB;

    if ($options->commentarea != 'userposts_comments') {
        throw new comment_exception('invalidcommentarea');
    }
    if (!$user = $DB->get_record('user', array('id'=>$options->itemid))) {
        throw new comment_exception('invalidcommentitemid');
    }
    $context = $options->context;

    if (!$cm = get_coursemodule_from_id('hsuforum', $context->instanceid)) {
        throw new comment_exception('invalidcontext');
    }

    if (!has_capability('mod/hsuforum:rate', $context)) {
        if (!has_capability('mod/hsuforum:replypost', $context) or ($user->id != $USER->id)) {
            return array('view' => false, 'post' => false);
        }
    }

    return array('view' => true, 'post' => true);
}

/**
 * @param array $comments
 * @param stdClass $options
 * @return mixed
 */
function mod_hsuforum_comment_display($comments, $options) {
    foreach ($comments as $comment) {
        $comment->content = file_rewrite_pluginfile_urls($comment->content, 'pluginfile.php', $options->context->id,
                'mod_hsuforum', 'comments', $comment->id);
    }

    return $comments;
}

/**
 * @param $course
 * @param $cm
 * @param $context
 * @param $filearea
 * @param $args
 * @param $forcedownload
 * @param $options
 * @return bool
 */
function hsuforum_forum_comments_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options) {
    global $DB, $USER;

    // Make sure this is the comments area.
    if ($filearea !== 'comments') {
        return false;
    }

    // Get the comment record.
    $commentid = (int)array_shift($args);
    if (!$comment = $DB->get_record('comments', array('id'=>$commentid))) {
        return false;
    }

    // Try to get the file.
    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_hsuforum/$filearea/$commentid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // Check permissions.
    if (!has_capability('mod/hsuforum:rate', $context)) {
        if (!has_capability('mod/hsuforum:replypost', $context) or ($comment->itemid != $USER->id)) {
            return false;
        }
    }

    // finally send the file
    send_stored_file($file, 86400, 0, true, $options);
}

/**
 * @param stdClass $comment
 * @param stdClass $options
 * @throws comment_exception
 */
function mod_hsuforum_comment_message(stdClass $comment, stdClass $options) {
    global $DB;

    if ($options->commentarea != 'userposts_comments') {
        throw new comment_exception('invalidcommentarea');
    }
    if (!$user = $DB->get_record('user', array('id'=>$options->itemid))) {
        throw new comment_exception('invalidcommentitemid');
    }
    $context = $options->context;

    if (!$cm = get_coursemodule_from_id('hsuforum', $context->instanceid)) {
        throw new comment_exception('invalidcontext');
    }

    // Get all the users with the ability to rate.
    $recipients = get_users_by_capability($context, 'mod/hsuforum:rate');

    // Add the item user if they are different from commenter.
    if ($comment->userid != $user->id and has_capability('mod/hsuforum:replypost', $context, $user)) {
        $recipients[$user->id] = $user;
    }

    // Sender is the author of the comment.
    $sender = $DB->get_record('user', array('id' => $comment->userid));

    // Make sure that the commenter is not getting the message.
    unset($recipients[$comment->userid]);

    $gareaid = component_callback('local_joulegrader', 'area_from_context', array($context, 'hsuforum'));
    $contexturl = new moodle_url('/local/joulegrader/view.php', array('courseid' => $cm->course,
            'garea' => $gareaid, 'guser' => $user->id));

    $params = array($comment, $recipients, $sender, $cm->name, $contexturl);
    component_callback('local_mrooms', 'comment_send_messages', $params);
}

/**
 * Set the per-forum maildigest option for the specified user.
 *
 * @param stdClass $forum The forum to set the option for.
 * @param int $maildigest The maildigest option.
 * @param stdClass $user The user object. This defaults to the global $USER object.
 * @throws invalid_digest_setting thrown if an invalid maildigest option is provided.
 */
function hsuforum_set_user_maildigest($forum, $maildigest, $user = null) {
    global $DB, $USER;

    if (is_number($forum)) {
        $forum = $DB->get_record('hsuforum', array('id' => $forum));
    }

    if ($user === null) {
        $user = $USER;
    }

    $course  = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
    $cm      = get_coursemodule_from_instance('hsuforum', $forum->id, $course->id, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // User must be allowed to see this forum.
    require_capability('mod/hsuforum:viewdiscussion', $context, $user->id);

    // Validate the maildigest setting.
    $digestoptions = hsuforum_get_user_digest_options($user);

    if (!isset($digestoptions[$maildigest])) {
        throw new moodle_exception('invaliddigestsetting', 'mod_hsuforum');
    }

    // Attempt to retrieve any existing forum digest record.
    $subscription = $DB->get_record('hsuforum_digests', array(
        'userid' => $user->id,
        'forum' => $forum->id,
    ));

    // Create or Update the existing maildigest setting.
    if ($subscription) {
        if ($maildigest == -1) {
            $DB->delete_records('hsuforum_digests', array('forum' => $forum->id, 'userid' => $user->id));
        } else if ($maildigest !== $subscription->maildigest) {
            // Only update the maildigest setting if it's changed.

            $subscription->maildigest = $maildigest;
            $DB->update_record('hsuforum_digests', $subscription);
        }
    } else {
        if ($maildigest != -1) {
            // Only insert the maildigest setting if it's non-default.

            $subscription = new stdClass();
            $subscription->forum = $forum->id;
            $subscription->userid = $user->id;
            $subscription->maildigest = $maildigest;
            $subscription->id = $DB->insert_record('hsuforum_digests', $subscription);
        }
    }
}

/**
 * Determine the maildigest setting for the specified user against the
 * specified forum.
 *
 * @param Array $digests An array of forums and user digest settings.
 * @param stdClass $user The user object containing the id and maildigest default.
 * @param int $forumid The ID of the forum to check.
 * @return int The calculated maildigest setting for this user and forum.
 */
function hsuforum_get_user_maildigest_bulk($digests, $user, $forumid) {
    if (isset($digests[$forumid]) && isset($digests[$forumid][$user->id])) {
        $maildigest = $digests[$forumid][$user->id];
        if ($maildigest === -1) {
            $maildigest = $user->maildigest;
        }
    } else {
        $maildigest = $user->maildigest;
    }
    return $maildigest;
}

/**
 * Retrieve the list of available user digest options.
 *
 * @param stdClass $user The user object. This defaults to the global $USER object.
 * @return array The mapping of values to digest options.
 */
function hsuforum_get_user_digest_options($user = null) {
    global $USER;

    // Revert to the global user object.
    if ($user === null) {
        $user = $USER;
    }

    $digestoptions = array();
    $digestoptions['0']  = get_string('emaildigestoffshort', 'mod_hsuforum');
    $digestoptions['1']  = get_string('emaildigestcompleteshort', 'mod_hsuforum');
    $digestoptions['2']  = get_string('emaildigestsubjectsshort', 'mod_hsuforum');

    // We need to add the default digest option at the end - it relies on
    // the contents of the existing values.
    $digestoptions['-1'] = get_string('emaildigestdefault', 'mod_hsuforum',
            $digestoptions[$user->maildigest]);

    // Resort the options to be in a sensible order.
    ksort($digestoptions);

    return $digestoptions;
}
