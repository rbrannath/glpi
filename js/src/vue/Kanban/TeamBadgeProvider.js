/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2024 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

export class TeamBadgeProvider {
    constructor(display_initials, max_team_images = 3) {
        this.badges = {
            User: {},
            Group: {},
            Supplier: {},
            Contact: {}
        };
        this.user_pictures_to_load = new Set([]);
        this.dark_theme = $('html').attr('data-glpi-theme-dark') === '1';
        /**
         * The size in pixels for the team badges
         * @type {number}
         */
        this.team_image_size = 24;
        this.max_team_images = max_team_images;
        this.display_initials = display_initials;

        /**
         * The event target to use to dispatch and listen for events from this cache including for when a new user picture is loaded and the badge is ready to be re-fetched.
         * @type {EventTarget}
         */
        this.event_target = new EventTarget();
    }

    /**
     * Get a hash for the team member's badge.
     * This can be used to determine if the content of the badge has changed such as when the image isn't loaded initially, but is loaded later.
     * @param team_member The team member
     * @return {string} The hash
     */
    getTeamBadgeHash(team_member) {
        const itemtype = team_member["itemtype"];
        const items_id = team_member["id"];
        const content = this.getTeamBadge(team_member);
        return btoa(itemtype + items_id + content).slice(0, 8);
    }

    /**
     * Gets the badge to show for the given team member.
     * If the badge wasn't generated before, it will be done at this time and cached for later use.
     * @param {{}} team_member The team member
     * @returns {string} HTML image or icon
     */
    getTeamBadge(team_member) {
        const itemtype = team_member["itemtype"];
        const items_id = team_member["id"];

        // If the picture is already cached, return cache value
        if (this.badges[itemtype] !== undefined && this.badges[itemtype][items_id] !== undefined) {
            return this.badges[itemtype][items_id];
        }

        // Pictures from users
        if (itemtype === 'User') {
            // Display a placeholder and keep track of the image to load it later
            this.user_pictures_to_load.add(items_id);
            this.badges[itemtype][items_id] = this.generateUserBadge(team_member);

            return this.badges[itemtype][items_id];
        }

        // Pictures from groups, supplier, contact
        switch (itemtype) {
            case 'Group':
                this.badges[itemtype][items_id] = this.generateOtherBadge(team_member, 'fa-users');
                break;
            case 'Supplier':
                this.badges[itemtype][items_id] = this.generateOtherBadge(team_member, 'fa-briefcase');
                break;
            case 'Contact':
                this.badges[itemtype][items_id] = this.generateOtherBadge(team_member, 'fa-user');
                break;
            default:
                this.badges[itemtype][items_id] = this.generateOtherBadge(team_member, 'fa-user');
        }
        return this.badges[itemtype][items_id];
    }

    /**
     * Attempt to load the user pictures that were previously determined to be needed when a badge was requested.
     */
    fetchRequiredUserPictures() {
        // Get user ids for which we must load their pictures
        const users_ids = Array.from(this.user_pictures_to_load.values());

        if (users_ids.length === 0) {
            // Nothing to be loaded
            return;
        }

        // Clear "to load" list
        this.user_pictures_to_load.clear();

        $.ajax({
            type: 'POST', // Too much data may break GET limit
            url: CFG_GLPI['root_doc'] + '/ajax/getUserPicture.php',
            data: {
                users_id: users_ids,
                size: this.team_image_size,
            }
        }).done((data) => {
            const to_reload = [];
            Object.keys(users_ids).forEach((user_id) => {
                if (data[user_id] !== undefined) {
                    // Store new image in cache
                    this.badges['User'][user_id] = "<span>" + data[user_id] + "</span>";
                    to_reload.push(user_id);
                }
            });
            this.event_target.dispatchEvent(new CustomEvent('kanban:team_badge:changed', {
                detail: {
                    User: to_reload
                }
            }));
        });
    }

    /**
     * Convert the given H, S, L values into a color hex code (with prepended hash symbol).
     * @param {number} h Hue
     * @param {number} s Saturation
     * @param {number} l Lightness
     * @return {string} Hex code color value
     */
    hslToHexColor(h, s, l) {
        let r, g, b;

        if (s === 0) {
            r = g = b = l;
        } else {
            const hue2rgb = function hue2rgb(p, q, t){
                if (t < 0)
                    t += 1;
                if (t > 1)
                    t -= 1;
                if (t < 1/6)
                    return p + (q - p) * 6 * t;
                if (t < 1/2)
                    return q;
                if (t < 2/3)
                    return p + (q - p) * (2/3 - t) * 6;
                return p;
            };

            const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
            const p = 2 * l - q;
            r = hue2rgb(p, q, h + 1/3);
            g = hue2rgb(p, q, h);
            b = hue2rgb(p, q, h - 1/3);
        }

        r = ('0' + (r * 255).toString(16)).substr(-2);
        g = ('0' + (g * 255).toString(16)).substr(-2);
        b = ('0' + (b * 255).toString(16)).substr(-2);
        return '#' + r + g + b;
    }

    /**
     * Compute a new badge color or retrieve the cached color from session storage.
     * @param team_member The team member
     * @returns {string} The color to use for the badge
     */
    getBadgeColor(team_member) {
        let cached_colors = JSON.parse(window.sessionStorage.getItem('badge_colors'));
        const itemtype = team_member['itemtype'];
        const baseColor = Math.random();
        const lightness = (Math.random() * 10) + (this.dark_theme ? 25 : 70);
        let bg_color = this.hslToHexColor(baseColor, 1, lightness / 100);

        if (cached_colors !== null && cached_colors[itemtype] !== null && cached_colors[itemtype][team_member['id']]) {
            bg_color = cached_colors[itemtype][team_member['id']];
        } else {
            if (cached_colors === null) {
                cached_colors = {
                    User: {},
                    Group: {},
                    Supplier: {},
                    Contact: {},
                    _dark_theme: this.dark_theme
                };
            }
            cached_colors[itemtype][team_member['id']] = bg_color;
            window.sessionStorage.setItem('badge_colors', JSON.stringify(cached_colors));
        }

        return bg_color;
    }

    getBadgeCanvas(bg_color) {
        const canvas = document.createElement('canvas');
        canvas.width = this.team_image_size;
        canvas.height = this.team_image_size;
        const context = canvas.getContext('2d');
        context.strokeStyle = "#f1f1f1";
        context.fillStyle = bg_color;
        context.beginPath();
        context.arc(this.team_image_size / 2, this.team_image_size / 2, this.team_image_size / 2, 0, 2 * Math.PI);
        context.fill();
        context.fillStyle = this.dark_theme ? 'white' : 'black';
        context.textAlign = 'center';
        context.font = 'bold ' + (this.team_image_size / 2) + 'px sans-serif';
        context.textBaseline = 'middle';
        return canvas;
    }

    generateUserBadge(team_member) {
        let initials = "";
        if (team_member["firstname"]) {
            initials += team_member["firstname"][0];
        }
        if (team_member["realname"]) {
            initials += team_member["realname"][0];
        }
        // Force uppercase initals
        initials = initials.toUpperCase();

        if (!this.display_initials || initials.length === 0) {
            return this.generateOtherBadge(team_member, 'fa-user');
        }

        const canvas = this.getBadgeCanvas(this.getBadgeColor(team_member));
        const context = canvas.getContext('2d');
        context.fillText(initials, this.team_image_size / 2, this.team_image_size / 2);
        const src = canvas.toDataURL("image/png");
        const name = team_member['name'].replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        return "<span><img src='" + src + "' title='" + name + "' data-bs-toggle='tooltip' data-placeholder-users-id='" + team_member["id"] + "'/></span>";
    }

    /**
     * Generate team member icon based on its name and a FontAwesome icon.
     * @param {{}} team_member The team member
     * @param {string} icon FontAwesome icon to use for this badge
     * @return {string} HTML icon of the generated badge
     */
    generateOtherBadge(team_member, icon) {
        const bg_color = this.getBadgeColor(team_member);
        const name = team_member['name'].replace(/"/g, '&quot;').replace(/'/g, '&#39;');

        return `
            <span class='fa-stack fa-lg' style='font-size: ${(this.team_image_size / 2)}px'>
                <i class='fas fa-circle fa-stack-2x' style="color: ${bg_color}" title="${team_member['name']}"></i>
                <i class='fas ${icon} fa-stack-1x' title="${name}" data-bs-toggle='tooltip'></i>
            </span>
        `;
    }

    /**
     * Generate a badge to indicate that 'overflow_count' number of team members are not shown on the Kanban item.
     * @param overflow_count Number of members without badges on the Kanban item
     * @return {string} HTML image of the generated overflow badge
     */
    generateOverflowBadge(overflow_count) {
        // Create fill color based on theme type
        const lightness = (this.dark_theme ? 40 : 80);
        const canvas = this.getBadgeCanvas(`hsl(255, 0%, ${lightness}%, 1)`);
        const context = canvas.getContext('2d');
        context.fillText("+" + overflow_count, this.team_image_size / 2, this.team_image_size / 2);
        const src = canvas.toDataURL("image/png");
        return "<span class='position-relative'><img src='" + src + "' title='" + __('%d other team members').replace('%d', overflow_count) + "' data-bs-toggle='tooltip'/></span>";
    }
}
