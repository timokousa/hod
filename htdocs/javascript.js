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

var failed = false;
var player;

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

function check_stream_status(url, src) {
        var video = document.getElementById('video');

        if (video && src)
                player.poster("img.php?icon=wait&src=" + src);

        var http = new XMLHttpRequest();
        http.open('get', url, true);

        http.onreadystatechange = function () {
                if (http.readyState != 4)
                        return;

                if (video && src && http.status != 200) {
                        failed = true;

                        player.poster("img.php?icon=error&src=" + src);

                        var img = document.createElement('img');
                        img.alt = "error";
                        img.id = "status";
                        img.src = "img.php?h=30&w=30&icon=error";

                        document.getElementById('status').parentElement.replaceChild(img,
                                        document.getElementById('status'));

                        var errortext = document.createElement('span');
                        errortext.innerHTML = http.responseText.replace(/<[^>]*>/gm, '').trim().replace(/\n/gm, '<br>');
                        errortext.className = "tooltiptext";

                        document.getElementById('status').parentElement.insertBefore(errortext, img);

                        var timeout = http.getResponseHeader('Retry-After');

                        if (!timeout)
                                timeout = 10;

                        setTimeout(function() {
                                        document.getElementById('status').parentElement.removeChild(errortext);

                                        img.alt = "starting";
                                        img.src = "img.php?h=30&w=30&icon=wait";
                                        img.className = "spinccw";

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
                        var statusimg = document.getElementById('status');
                        var style = (1 / (1 + segcount)) + "s rotateright infinite linear";

                        statusimg.alt = "buffering";
                        statusimg.style.MozAnimation = style;
                        statusimg.style.webkitAnimation = style;

                        setTimeout(function() {
                                        check_stream_status(url, src);
                                        }, duration * 500);
                        return;
                }

                if (failed)
                        location.reload(true);

                if (video && src)
                        player.poster("img.php?icon=play&src=" + src);

                if (player.paused)
                        player.play();

                var readyimg = document.createElement('img');
                readyimg.alt = "ready";
                readyimg.id = "status";
                readyimg.src = "img.php?h=30&w=30";

                document.getElementById('status').parentElement.replaceChild(readyimg,
                                document.getElementById('status'));

                setTimeout(function() {
                                document.getElementById('status').style.display = 'none';
                                }, 3000);
        }

        http.withCredentials = true;
        http.send();
}

function update_divs() {
        var http = new XMLHttpRequest();
        http.open('get', window.location.href, true);

        http.onreadystatechange = function () {
                if (http.readyState != 4)
                        return;

                if (http.status != 200) {
                        setTimeout(function() { update_divs(); }, 600000);
                        return;
                }

                var divs = http.responseXML.getElementsByTagName('div');

                for (var i = 0; i < divs.length; i++) {
                        if (!divs[i].id || divs[i].id.indexOf("divup-") != 0)
                                continue;

                        div = document.getElementById(divs[i].id);
                        if (!div)
                                continue;

                        if (div.innerHTML != divs[i].innerHTML)
                                div.innerHTML = divs[i].innerHTML;
                }

                var reload = http.getResponseHeader('X-update-divs');

                if (reload)
                        setTimeout(function() { update_divs(); },
                                        reload * 1000);
        }

        http.responseType = "document";
        http.send();
}

function player_init() {
        var video = document.getElementById('video');

        if (typeof videojs === 'function') {
                var opts = {
                        html5: {
                                hls: { withCredentials: true },
                                hlsjsConfig: {
                                        levelLoadingTimeOut: 60000,
                                        manifestLoadingTimeOut: 60000,
                                        xhrSetup: function(xhr, url) {
                                                xhr.withCredentials = true;
                                        },
                                }
                        }
                };

                player = videojs(video, opts);
        }
        else
                player = {
                        paused: function() { return video.paused; },
                        play: function() { video.play(); },
                        poster: function(url) { video.poster = url; },
                };
}
