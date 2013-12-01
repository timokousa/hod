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

$workdir = '/tmp/hod';
$key_force_https = false;

ini_set('session.use_only_cookies', 0);
date_default_timezone_set('Europe/Helsinki');

session_name('hod');
session_start();

include_once 'auth.php';

$cache = array();

if (file_exists($workdir . DIRECTORY_SEPARATOR . 'hod-cache'))
        $cache = unserialize(file_get_contents(
                                $workdir . DIRECTORY_SEPARATOR . 'hod-cache'));

if (isset($cache['expires']) && $cache['expires'] < time())
        unset($cache['sources'], $cache['expires']);

?>
