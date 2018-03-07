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
 * File Export Adapter
 *
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\export;

use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/adapter_interface.php');

/**
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class print_adapter implements adapter_interface {
    /**
     * @var stdClass
     */
    protected $cm;

    public function __construct($cm) {
        $this->cm = $cm;

        hsuforum_cm_add_cache($this->cm);
    }

    /**
     * Initialization routine
     *
     * @param null|stdClass $discussion Only passed if exporting a single discussion
     * @return void
     */
    public function initialization($discussion = null) {
        global $PAGE, $OUTPUT;

        $PAGE->requires->js_init_call('window.print', null, true);
        $PAGE->set_pagelayout('embedded');

        echo $OUTPUT->header();
        echo $OUTPUT->box_start('mod_hsuforum_posts_container');
    }

    /**
     * Send a discussion and its posts to the export
     *
     * @param stdClass $discussion
     * @param stdClass[] $posts
     * @return void
     */
    public function send_discussion($discussion, $posts) {
        global $USER;

        foreach ($posts as $post) {
            $ownpost = ($USER->id == $post->userid);

            hsuforum_print_post($post, $discussion, $this->cm->cache->forum, $this->cm, $this->cm->cache->course, $ownpost, false, false,
                '', '', null, false, false, false, array());
        }
    }

    /**
     * Exporting is done, wrap things up.
     *
     * @return void
     */
    public function finish() {
        global $OUTPUT;

        echo $OUTPUT->box_end();
        echo $OUTPUT->footer();
    }
}
