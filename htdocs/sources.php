<?php
/*
   Copyright (C) 2013 Timo Kousa

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

#$cache['sources']['example']['title'] = 'example';
#$cache['sources']['example']['description'] = 'example live source';
#$cache['sources']['example']['input'] = 'udp://239.255.0.1:1234';
#$cache['sources']['example']['live'] = true;
#$cache['sources']['example']['encrypt'] = true;

#$cache['sources']['example2']['title'] = 'example2';
#$cache['sources']['example2']['description'] = 'example static source';
#$cache['sources']['example2']['input'] = '/var/video/file.mkv';
#$cache['sources']['example2']['live'] = false;
#$cache['sources']['example2']['encrypt'] = true;

#$cache['expires'] = time() + 600;

function find($dir, $q, $recursive = true) {
        $ret = array();

        $files = glob(rtrim($dir, DIRECTORY_SEPARATOR) .
                        DIRECTORY_SEPARATOR . '*');

        foreach ($files as $file) {
                if ($recursive && is_dir($file))
                        $ret = array_merge($ret, find($file, $q, true));
                elseif (preg_match($q, basename($file)))
                        $ret[] = $file;
        }

        return $ret;
}

include_once 'vdrstreamdev.php';
include_once 'vdrvideodir.php';
include_once 'videodir.php';

file_put_contents($workdir . DIRECTORY_SEPARATOR . 'hod-cache',
                serialize($cache));

?>
