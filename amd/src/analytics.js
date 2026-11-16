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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Highlighting report of the Underly.it activity: shows or hides the technical columns.
 *
 * The report itself is rendered on the server with the mod_underlyit/report template.
 *
 * @module     mod_underlyit/analytics
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    /**
     * Binds the technical columns toggle.
     */
    function init() {
        const root = document.getElementById('underlyit-analytics');
        const button = root?.querySelector('[data-action="toggle-technical"]');
        if (!button) {
            return;
        }
        button.addEventListener('click', () => {
            const expanded = root.classList.toggle('underlyit-show-technical');
            button.setAttribute('aria-pressed', String(expanded));
            button.textContent = expanded ? button.dataset.hideLabel : button.dataset.showLabel;
        });
    }

    return {init};
});
