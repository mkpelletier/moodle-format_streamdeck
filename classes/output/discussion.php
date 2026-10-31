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

namespace format_streamdeck\output;

use mod_forum\local\container;
use mod_forum\local\entities\discussion as discussion_entity;
use mod_forum\local\entities\post as post_entity;
use moodle_url;

/**
 * An episode's discussion, shown in the course page modal: the thread, or the list of discussions to pick from.
 *
 * Posts are read through mod_forum's vaults and capability manager, so private replies, groups and the reply, edit
 * and delete permissions are the forum's own. Writing goes through mod_forum's post form, reshaped by
 * amd/src/forum_composer.js, so attachments, tags and subscriptions work as they do in the forum.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion {
    /** @var int Replies deeper than this are shown at this depth, to keep the thread readable. */
    private const MAX_DEPTH = 3;

    /** @var int Announcements shown in the panel; the rest are in the forum. */
    private const NEWS_LIMIT = 10;

    /** @var int How long an announcement counts as new when the forum does not track what was read. */
    private const NEW_PERIOD = WEEKSECS;

    /**
     * The panel for a forum: its only discussion as a thread, or a list of its discussions.
     *
     * @param \cm_info $cm The forum.
     * @param bool $alwayslist List the discussions even when there is only one, and when there are none (with the
     *     option to start one): for the course's discussion forum rather than an episode's.
     * @return string HTML, or '' when the learner has nothing to see.
     */
    public static function render_preview(\cm_info $cm, bool $alwayslist = false): string {
        $inner = self::render_view($cm, $alwayslist);
        if ($inner === '') {
            return '';
        }
        return \html_writer::div($inner, 'streamdeck-forum-preview sd-discussion', [
            'data-region' => 'sd-discussion',
            'data-contextid' => $cm->context->id,
            'data-alwayslist' => $alwayslist ? 1 : 0,
        ]);
    }

    /**
     * The content of the panel, without its card: the thread or the list.
     *
     * @param \cm_info $cm The forum.
     * @param bool $alwayslist See render_preview().
     * @return string
     */
    public static function render_view(\cm_info $cm, bool $alwayslist = false): string {
        global $OUTPUT, $USER;

        $forum = container::get_vault_factory()->get_forum_vault()->get_from_course_module_id($cm->id);
        if (!$forum) {
            return '';
        }
        $capabilities = container::get_manager_factory()->get_capability_manager($forum);
        $discussions = container::get_vault_factory()->get_discussion_vault()->get_all_discussions_in_forum($forum) ?? [];
        $discussions = array_values(array_filter($discussions, fn($d) => $capabilities->can_view_discussion($USER, $d)));

        if (!$alwayslist && count($discussions) === 1) {
            return $OUTPUT->render_from_template(
                'format_streamdeck/local/discussion',
                self::export_thread($cm, $discussions[0]->get_id())
            );
        }
        if (!$alwayslist && !$discussions) {
            return '';
        }
        $list = self::export_list($cm, $discussions);
        if ($capabilities->can_create_discussions($USER)) {
            $list['canstart'] = true;
            $list['starturl'] = (new moodle_url('/mod/forum/post.php', ['forum' => $forum->get_id()]))->out(false);
        }
        return $OUTPUT->render_from_template('format_streamdeck/local/discussion_list', $list);
    }

    /**
     * The course's announcements as a feed: newest first (pinned ones on top), the newest open, the rest opening
     * in place. Showing them marks them read, as opening them in the forum would.
     *
     * @param \cm_info $cm The announcements forum.
     * @return string HTML.
     */
    public static function render_news(\cm_info $cm): string {
        global $CFG, $DB, $OUTPUT, $USER;
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $vaults = container::get_vault_factory();
        $forum = $vaults->get_forum_vault()->get_from_course_module_id($cm->id);
        if (!$forum) {
            return '';
        }
        $capabilities = container::get_manager_factory()->get_capability_manager($forum);
        $context = $cm->context;

        $discussions = $vaults->get_discussion_vault()->get_all_discussions_in_forum($forum) ?? [];
        $discussions = array_filter($discussions, fn($d) => $capabilities->can_view_discussion($USER, $d));
        // The forum's own order: pinned first, then latest activity (or the scheduled start, if later).
        $latest = fn($d) => max($d->get_time_start(), $d->get_time_modified());
        usort($discussions, fn($a, $b) => [$b->is_pinned(), $latest($b)] <=> [$a->is_pinned(), $latest($a)]);
        $hasmore = count($discussions) > self::NEWS_LIMIT;
        $discussions = array_slice($discussions, 0, self::NEWS_LIMIT);

        $items = [];
        $readpostids = [];
        if ($discussions) {
            $byid = [];
            foreach ($discussions as $discussion) {
                $byid[$discussion->get_id()] = $discussion;
            }
            $posts = $vaults->get_post_vault()->get_first_post_for_discussion_ids(array_keys($byid));
            $replies = $vaults->get_post_vault()->get_reply_count_for_discussion_ids(
                $USER,
                array_keys($byid),
                $capabilities->can_view_any_private_reply($USER)
            );
            $legacyforum = container::get_legacy_data_mapper_factory()->get_forum_data_mapper()->to_legacy_object($forum);
            $tracked = forum_tp_can_track_forums($legacyforum) && forum_tp_is_tracked($legacyforum);
            $receipts = $tracked ? $vaults->get_post_read_receipt_collection_vault()->get_from_user_and_posts($USER, $posts) : null;
            $authorids = array_unique(array_map(fn($post) => $post->get_author_id(), $posts));
            $authors = $authorids ? $DB->get_records_list('user', 'id', $authorids, '', \core_user\fields::for_userpic()
                ->get_sql('', false, '', '', false)->selects) : [];

            foreach ($posts as $post) {
                $discussion = $byid[$post->get_discussion_id()] ?? null;
                if (!$discussion) {
                    continue;
                }
                $replycount = (int) ($replies[$discussion->get_id()] ?? 0);
                $item = self::export_post($post, $discussion, $capabilities, $authors, $context, $replycount > 0);
                $time = max($discussion->get_time_start(), $post->get_time_created());
                $item['isnew'] = $receipts ? !$receipts->has_user_read_post($USER, $post) : $time > time() - self::NEW_PERIOD;
                $item['discussionid'] = $discussion->get_id();
                $item['title'] = format_string($discussion->get_name(), true, ['context' => $context]);
                $item['pinned'] = $discussion->is_pinned();
                $item['time'] = self::relative_time($time);
                $item['datetime'] = userdate($time, get_string('strftimedatetime', 'langconfig'));
                $item['isodate'] = date('c', $time);
                $item['day'] = userdate($time, '%d');
                $item['month'] = userdate($time, '%b');
                $item['excerpt'] = shorten_text(html_to_text($item['message'], 0, false), 200);
                // Replies, and replying, happen in the discussion panel, not under the announcement.
                $item['candiscuss'] = $replycount > 0 || $item['canreply'];
                $item['replies'] = $replycount === 1
                    ? get_string('onereply', 'format_streamdeck')
                    : get_string('numreplies', 'format_streamdeck', $replycount);
                $item['hasactions'] = $item['candiscuss'] || $item['canedit'] || $item['candelete'];
                $items[$discussion->get_id()] = $item;
                if ($receipts && $item['isnew']) {
                    $readpostids[] = $post->get_id();
                }
            }
            // Back in feed order (the posts come keyed by post).
            $items = array_values(array_filter(array_map(fn($d) => $items[$d->get_id()] ?? null, $discussions)));
            if ($items) {
                $items[0]['expanded'] = true;
            }
        }

        if ($readpostids) {
            forum_tp_mark_posts_read($USER, $readpostids);
        }
        $newcount = count(array_filter($items, fn($item) => $item['isnew']));
        $canpost = $capabilities->can_create_discussions($USER);

        return $OUTPUT->render_from_template('format_streamdeck/local/announcements', [
            'forumname' => format_string($cm->name, true, ['context' => $context]),
            'forumurl' => (new moodle_url('/mod/forum/view.php', ['id' => $cm->id]))->out(false),
            'items' => $items,
            'hasitems' => !empty($items),
            'hasmore' => $hasmore,
            'newcount' => $newcount === 1
                ? get_string('onenewannouncement', 'format_streamdeck')
                : ($newcount ? get_string('numnewannouncements', 'format_streamdeck', $newcount) : ''),
            'canpost' => $canpost,
            'posturl' => $canpost ? (new moodle_url('/mod/forum/post.php', ['forum' => $forum->get_id()]))->out(false) : '',
        ]);
    }

    /**
     * The list of discussions in a forum.
     *
     * @param \cm_info $cm
     * @param discussion_entity[] $discussions The discussions the learner can see.
     * @return array Template context.
     */
    public static function export_list(\cm_info $cm, array $discussions): array {
        global $DB;

        $context = $cm->context;
        $counts = [];
        if ($discussions) {
            [$insql, $params] = $DB->get_in_or_equal(array_map(fn($d) => $d->get_id(), $discussions));
            $counts = $DB->get_records_sql_menu(
                "SELECT discussion, COUNT(1) - 1 FROM {forum_posts} WHERE discussion $insql GROUP BY discussion",
                $params
            );
        }
        usort($discussions, fn($a, $b) => $b->get_time_modified() <=> $a->get_time_modified());

        $items = [];
        foreach ($discussions as $discussion) {
            $replies = (int) ($counts[$discussion->get_id()] ?? 0);
            $items[] = [
                'id' => $discussion->get_id(),
                'name' => format_string($discussion->get_name(), true, ['context' => $context]),
                'replies' => $replies === 1
                    ? get_string('onereply', 'format_streamdeck')
                    : get_string('numreplies', 'format_streamdeck', $replies),
                'lastactivity' => self::relative_time($discussion->get_time_modified()),
                'pinned' => $discussion->is_pinned(),
            ];
        }
        return [
            'forumname' => format_string($cm->name, true, ['context' => $context]),
            'forumurl' => (new moodle_url('/mod/forum/view.php', ['id' => $cm->id]))->out(false),
            'discussions' => $items,
            'hasdiscussions' => !empty($items),
            'canstart' => false,
            'starturl' => '',
        ];
    }

    /**
     * One discussion as a thread: the opening post, then the replies nested under what they answer.
     *
     * @param \cm_info $cm
     * @param int $discussionid
     * @return array|null Template context, or null if the learner cannot see the discussion.
     */
    public static function export_thread(\cm_info $cm, int $discussionid): ?array {
        global $DB, $PAGE, $USER;

        $vaults = container::get_vault_factory();
        $forum = $vaults->get_forum_vault()->get_from_course_module_id($cm->id);
        $discussion = $vaults->get_discussion_vault()->get_from_id($discussionid);
        if (!$forum || !$discussion || $discussion->get_forum_id() !== $forum->get_id()) {
            return null;
        }
        $capabilities = container::get_manager_factory()->get_capability_manager($forum);
        if (!$capabilities->can_view_discussion($USER, $discussion)) {
            return null;
        }

        $posts = $vaults->get_post_vault()->get_from_discussion_id(
            $USER,
            $discussionid,
            $capabilities->can_view_any_private_reply($USER)
        );
        $posts = array_filter($posts, fn($post) => $capabilities->can_view_post($USER, $discussion, $post));
        $byid = [];
        foreach ($posts as $post) {
            $byid[$post->get_id()] = $post;
        }
        $posts = $byid;
        $parentids = array_flip(array_map(fn($post) => $post->get_parent_id(), $posts));
        $authorids = array_unique(array_map(fn($post) => $post->get_author_id(), $posts));
        $authors = $authorids ? $DB->get_records_list('user', 'id', $authorids, '', \core_user\fields::for_userpic()
            ->get_sql('', false, '', '', false)->selects) : [];

        $context = $cm->context;
        $exported = [];
        foreach ($posts as $post) {
            $exported[$post->get_id()] = self::export_post(
                $post,
                $discussion,
                $capabilities,
                $authors,
                $context,
                isset($parentids[$post->get_id()])
            );
        }

        // Nest replies under the post they answer, flattening anything deeper than MAX_DEPTH.
        $opening = null;
        $depths = [];
        foreach ($posts as $post) {
            $id = $post->get_id();
            if (!$post->has_parent() || !isset($exported[$post->get_parent_id()])) {
                $opening = $opening ?? $id;
                $depths[$id] = 0;
                continue;
            }
            $parent = $post->get_parent_id();
            while ($depths[$parent] >= self::MAX_DEPTH) {
                $parent = $posts[$parent]->get_parent_id();
            }
            $depths[$id] = $depths[$parent] + 1;
            $exported[$id]['depth'] = $depths[$id];
            $exported[$parent]['children'][] = &$exported[$id];
        }
        if ($opening === null) {
            return null;
        }
        // A flag, not {{#children.0}}: that would make the first child the template's context.
        foreach ($exported as &$item) {
            $item['haschildren'] = !empty($item['children']);
        }
        unset($item);
        $root = $exported[$opening];
        $replycount = count($posts) - 1;

        return [
            'discussionid' => $discussion->get_id(),
            'name' => format_string($discussion->get_name(), true, ['context' => $context]),
            'forumname' => format_string($cm->name, true, ['context' => $context]),
            'discussionurl' => (new moodle_url('/mod/forum/discuss.php', ['d' => $discussion->get_id()]))->out(false),
            'replycount' => $replycount === 1
                ? get_string('onereply', 'format_streamdeck')
                : get_string('numreplies', 'format_streamdeck', $replycount),
            'opening' => $root,
            'replies' => $root['children'] ?? [],
            'hasreplies' => !empty($root['children']),
            'canreply' => $root['canreply'],
            'replyurl' => $root['replyurl'],
            'useravatar' => (new \user_picture($USER))->get_url($PAGE)->out(false),
        ];
    }

    /**
     * One post for the template.
     *
     * @param post_entity $post
     * @param discussion_entity $discussion
     * @param \mod_forum\local\managers\capability $capabilities
     * @param \stdClass[] $authors Authors by id.
     * @param \context_module $context
     * @param bool $hasreplies Whether anyone has answered the post (learners cannot delete those).
     * @return array
     */
    private static function export_post(
        post_entity $post,
        discussion_entity $discussion,
        \mod_forum\local\managers\capability $capabilities,
        array $authors,
        \context_module $context,
        bool $hasreplies
    ): array {
        global $PAGE, $USER;

        $id = $post->get_id();
        $author = $authors[$post->get_author_id()] ?? null;
        $message = file_rewrite_pluginfile_urls($post->get_message(), 'pluginfile.php', $context->id, 'mod_forum', 'post', $id);

        $attachments = [];
        if ($post->has_attachments()) {
            $files = get_file_storage()->get_area_files($context->id, 'mod_forum', 'attachment', $id, 'filename', false);
            foreach ($files as $file) {
                $attachments[] = [
                    'name' => $file->get_filename(),
                    'url' => moodle_url::make_pluginfile_url(
                        $context->id,
                        'mod_forum',
                        'attachment',
                        $id,
                        $file->get_filepath(),
                        $file->get_filename(),
                        true
                    )->out(false),
                ];
            }
        }

        $canreply = $capabilities->can_reply_to_post($USER, $discussion, $post);
        $canedit = $capabilities->can_edit_post($USER, $discussion, $post);
        $candelete = $capabilities->can_delete_post($USER, $discussion, $post, $hasreplies);

        return [
            'id' => $id,
            'author' => $author ? fullname($author) : get_string('unknownuser'),
            'avatar' => $author ? (new \user_picture($author))->get_url($PAGE)->out(false) : '',
            'isown' => $post->get_author_id() == $USER->id,
            'time' => self::relative_time($post->get_time_created()),
            'datetime' => userdate($post->get_time_created(), get_string('strftimedatetime', 'langconfig')),
            'isodate' => date('c', $post->get_time_created()),
            'message' => format_text($message, $post->get_message_format(), ['context' => $context]),
            'attachments' => $attachments,
            'hasattachments' => !empty($attachments),
            'isprivate' => $post->is_private_reply(),
            'canreply' => $canreply,
            'replyurl' => $canreply ? (new moodle_url('/mod/forum/post.php', ['reply' => $id]))->out(false) : '',
            'canedit' => $canedit,
            'editurl' => $canedit ? (new moodle_url('/mod/forum/post.php', ['edit' => $id]))->out(false) : '',
            'candelete' => $candelete,
            'deleteurl' => $candelete ? (new moodle_url('/mod/forum/post.php', ['delete' => $id]))->out(false) : '',
            'hasactions' => $canreply || $canedit || $candelete,
            'depth' => 0,
            'children' => [],
        ];
    }

    /**
     * "3 hours ago", or the date for anything older than a week.
     *
     * @param int $time
     * @return string
     */
    private static function relative_time(int $time): string {
        $age = time() - $time;
        if ($age < MINSECS) {
            return get_string('now');
        }
        if ($age < WEEKSECS) {
            return get_string('ago', 'message', format_time($age - $age % ($age < HOURSECS ? MINSECS : HOURSECS)));
        }
        return userdate($time, get_string('strftimedatefullshort', 'langconfig'));
    }
}
