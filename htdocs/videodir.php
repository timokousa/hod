<?php
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

if (!isset($cache))
        exit;

$videodir = '/var/video';
$extensions = array('avi', 'flv', 'iso', 'm4v', 'mkv', 'mov', 'mp4', 'mpe?g',
                'og[gv]', 'ts', 'webm', 'wmv');

$videos = find($videodir, '/\.(' . implode('|', $extensions) . ')$/i', true);

foreach ($videos as $video) {
        $key = md5(filesize($video) . $video . filemtime($video));

        $i = pathinfo($video);

        $cache['sources'][$key]['live'] = false;
        $cache['sources'][$key]['encrypt'] = true;
        $cache['sources'][$key]['title'] = str_replace(
                        array('_', '.'), ' ', $i['filename']);
        $cache['sources'][$key]['input'] = $video;
        $cache['sources'][$key]['description'] = ltrim(preg_replace('/^' .
                        preg_quote($videodir, '/') . '/', '', $i['dirname']),
                        DIRECTORY_SEPARATOR);
}

if (!isset($cache['expires']))
        $cache['expires'] = time() + 1800;

?>
