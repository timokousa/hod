/*
   Copyright (C) 2015 Timo Kousa

   This file is part of HLS On Demand.

   HLS On Demand is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   HLS On Demand is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with HLS On Demand.  If not, see <http://www.gnu.org/licenses/>.
*/

function thumbs_up() {
        document.removeEventListener('scroll', thumbs_up);

        var imgs = document.getElementsByTagName("img");
        var thumb_down = false;

        for (var i = 0; i < imgs.length; i++) {
                var img = imgs[i];

                if (img.className != "thumbnail" || !img.alt)
                        continue;

                var height = window.innerHeight;
                var rects = img.getClientRects();
                var visible = false;

                for (var j = 0; j < rects.length; j++)
                        if ((rects[j].top >= 0 && rects[j].top <= height) ||
                                        (rects[j].bottom >= 0 &&
                                         rects[j].bottom <= height)) {
                                visible = true;
                                break;
                        }

                if (!visible) {
                        thumb_down = true;
                        continue;
                }

                var alt = img.alt;
                img.alt = "";
                img.onload = thumbs_up;
                img.src = alt;

                break;
        }

        if (thumb_down && i >= imgs.length)
                document.addEventListener('scroll', thumbs_up);
}

function load_poster(src) {
        var video = document.getElementById('video');

        if (video)
                video.poster = "img.php?src=" + src;
}

function play_video() {
        var video = document.getElementById('video');

        if (video) {
                video.play();
                video.webkitEnterFullScreen();
        }
}
