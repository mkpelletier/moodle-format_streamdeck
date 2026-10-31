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
 * Moodle App: course data and helpers for mobile/course.html.
 *
 * Runs in the app with `this` bound to the plugin content component, each time the content loads or refreshes.
 *
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-disable no-invalid-this */
(function(component) {
    var data = JSON.parse(component.CONTENT_OTHERDATA.data);
    var courseId = component.course ? component.course.id : (component.data && component.data.course && component.data.course.id);

    // Remember the open episode per course, so refreshing after a completion change keeps the learner there.
    var store = window.formatStreamdeckView = window.formatStreamdeckView || {};

    var byNum = {};
    data.episodes.forEach(function(episode) {
        byNum[episode.num] = episode;
    });

    component.sd = data;

    component.sdEpisode = function(num) {
        return byNum[num] || null;
    };

    component.sdSeasonEpisodes = function(index) {
        var season = data.seasons[index];
        return season ? season.nums.map(function(num) {
            return byNum[num];
        }).filter(Boolean) : [];
    };

    component.sdOpen = function(num) {
        var episode = byNum[num];
        if (num && (!episode || episode.locked)) {
            return;
        }
        component.CONTENT_OTHERDATA.view = String(num || 0);
        store[courseId] = component.CONTENT_OTHERDATA.view;
        setTimeout(function() {
            var content = document.querySelector('.format-streamdeck-app');
            var scroller = content && content.closest('ion-content');
            if (scroller && scroller.scrollToTop) {
                scroller.scrollToTop(300);
            }
        });
    };

    // Open on the remembered episode, else the section the app was asked to show, else the course page.
    var initial = store[courseId];
    if (initial === undefined) {
        var asked = component.initialSectionNumber !== undefined
            ? component.initialSectionNumber
            : (component.data && component.data.initialSectionNumber);
        initial = asked && byNum[asked] && !byNum[asked].locked ? String(asked) : '0';
    }
    component.CONTENT_OTHERDATA.view = byNum[initial] ? initial : '0';
    component.CONTENT_OTHERDATA.season = data.currentseason;
})(this);
