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

function play_video() {
        var video = document.getElementById('video');

        if (video && video.paused)
                video.play();

        orientation_check();
}

function orientation_check() {
        var video = document.getElementById('video');

        if (!video)
                return;

        switch (window.orientation) {
                case -90:
                case 90:
                        if (!video.webkitDisplayingFullscreen && !video.paused)
                                video.webkitEnterFullScreen();
                        break;
                default:
                        if (video.webkitDisplayingFullscreen)
                                video.webkitExitFullScreen();
        }
}

function check_stream_status(url, src) {
        var video = document.getElementById('video');

        if (video && src)
                video.poster = "img.php?icon=wait&src=" + src;

        var http = new XMLHttpRequest();
        http.open('get', url.replace(/^.*\/hod.php/, 'hod.php'), true);

        http.onreadystatechange = function () {
                if (http.readyState != 4)
                        return;

                if (video && src && http.status != 200) {
                        video.poster = "img.php?icon=error&src=" + src;

                        var timeout = http.getResponseHeader('Retry-After');

                        if (!timeout)
                                timeout = 10;

                        setTimeout(function() {
                                        check_stream_status(url, src);
                                        }, timeout * 1000);

                        return;
                }

                var stream_inf = false;
                var duration = 10;
                var segcount = 0;
                var end = false;

                var tmp = http.responseText.split("\n");

                for (var i = 0; i < tmp.length; i++) {
                        if (tmp[i].match(/^#EXT-X-TARGETDURATION/))
                                duration = tmp[i].match(/^#EXT-X-TARGETDURATION:(\d+)/)[1];
                        if (tmp[i].match(/^#EXT-X-STREAM-INF/))
                                stream_inf = true;
                        if (tmp[i].match(/^#EXT-X-ENDLIST/))
                                end = true;
                        if (tmp[i].match(/^#EXTINF/))
                                segcount++;
                        if (tmp[i].match(/^#/))
                                continue;
                        if (stream_inf) {
                                check_stream_status(tmp[i], src);
                                return;
                        }
                }

                if (segcount < 3 && !end) {
                        setTimeout(function() {
                                        check_stream_status(url, src);
                                        }, duration * 1000);
                        return;
                }

                if (video && src)
                        video.poster = "img.php?icon=play&src=" + src;

                play_video();
        }

        http.send();
}
