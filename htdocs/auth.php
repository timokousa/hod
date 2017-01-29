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

$passwd = array(
                array('user', 'password'),
                array('user2', 'password2'),
               );

if (isset($_REQUEST['logout'])) {
        $_SESSION = array();

        if (isset($_COOKIE[session_name()]))
                setcookie(session_name(), '', time() - 42000,
                                ini_get('session.cookie_path'));

        session_destroy();

        header('Location: ' . (isset($_SERVER['HTTPS']) ?
                                'https://' : 'http://') .
                        $_SERVER['HTTP_HOST'] .  $_SERVER['SCRIPT_NAME']);

        exit;
}

if ($force_https && !isset($_SERVER['HTTPS'])) {
        header('Location: https://' .
                        $_SERVER['HTTP_HOST'] .
                        $_SERVER['REQUEST_URI']);

        exit;
}

if (isset($_POST['user'], $_POST['password'])) {
        $_SESSION['user'] = $_POST['user'];
        $_SESSION['password'] = $_POST['password'];

        header('Location: ' . (isset ($_SERVER['HTTPS']) ?
                                'https://' : 'http://') .
                        $_SERVER['HTTP_HOST'] .  $_SERVER['REQUEST_URI']);

        exit;
}

if (!isset($_SESSION['user'], $_SESSION['password']) ||
                !in_array(array($_SESSION['user'], $_SESSION['password']),
                        $passwd)) {
        header("HTTP/1.0 403 Forbidden");
        header("Status: 403 Forbidden");
?>
<html>
 <head>
  <title>HOD</title>
  <meta name="viewport" content="width=device-width, height=device-height,
        initial-scale=1" />
  <link rel="stylesheet" type="text/css" href="style.css" />
 </head>
 <body>
  <div max-width="640px" align="center">
   <fieldset class="box">
    <legend>Log in:</legend>
    <form method="post">
     <table>
      <tr>
       <td>User:</td>
       <td><input name="user"></td>
      </tr>
      <tr>
       <td>Password:</td>
       <td><input name="password" type="password"></td>
      </tr>
     </table>
     <input type="submit">
    </form>
   </fieldset>
  </div>
 </body>
</html>
<?php
        exit;
}

if (ini_get('session.cookie_lifetime') && session_name() && session_id())
        setcookie(session_name(), session_id(),
                        time() + ini_get('session.cookie_lifetime'),
                        ini_get('session.cookie_path'),
                        ini_get('session.cookie_domain'),
                        ini_get('session.cookie_secure'));

?>
