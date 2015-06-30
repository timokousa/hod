#!/usr/bin/php
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

include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'rc.php';

if (!isset($workdir) || !$workdir)
        exit;

$opts = getopt('f');

if (!isset($opts['f']) &&
                (!isset($cache['expires']) || $cache['expires'] > time()))
        exit;

if (isset($cache['expires']))
        unset($cache['expires']);

if (isset($cache['sources']))
        unset($cache['sources']);

include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'sources.php';

?>
