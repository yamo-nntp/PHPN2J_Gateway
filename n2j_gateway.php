#!/usr/local/bin/php -q
<?
/*
PHPN2J_Gateway | Standalone N2J Gateway "NNTP to JNTP" for Inn

Copyright © 2013-2014 Julien Arlandis
Copyright © 2014 Gérald Niel
    @author : Julien Arlandis <julien.arlandis_at_gmail.com>
    @author : Gérald Niel <gerald.niel_at_gegeweb.org>
    
    @Licence : http://www.gnu.org/licenses/agpl-3.0.txt

    PHPN2J_Gateway is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    PHPN2J_Gateway is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with PhpNemoServer.  If not, see <http://www.gnu.org/licenses/>.

Usage :
-------
Copy this file and rename it wathever you want.
Better is to copy it in path_to_inn/bin and keep the name (in FreeBSD: /usr/local/news/bin/n2j_gateway.php).
Set the executable bit : chmod +x /usr/local/news/bin/n2j_gateway.php
Inn your newsfeeds file, for example :
JNTP!/from-jntp:[group pattern]:Tp,Wf:/usr/local/news/bin/n2j_gateway.php %s [server] 

Manualy : /usr/local/news/bin/n2j_gateway.php [token] [server]

You can now the token of an Usenet article with the command :
/usr/local/news/bin/grephistory '<Message-ID>'
*/

/*---- CONFIGURATION SECTION -----*/
error_reporting(E_ALL);					// For debug only

define('FROM', 'your.fqdn.domain');		// this fqdn MUST match with the source IP
define('PATH', 'nntp_path');			// what will be in the Path section of the NNTP Article
										// If not set, FROM will be used
define('ACTIVE_LOG', 0);				// Set to true for logging 
define('LOG_PATH', '/var/log/news');	// Path where is the logfile (must be writable by news user)
define('LOG_FEED_NAME', 'n2j.log');		// Name of the log file (must be writable by news user)

date_default_timezone_set('UTC');		// Default Timezone to UTC (don't modify)
/*--------------------------------*/

/*-----    CHECK REQUIRE     -----*/ 
if (!extension_loaded('curl')) 		{ echo "CURL Extension is missing.\n"; 	exit(); }
if (!extension_loaded('json'))		{ echo "JSON Extension is missing.\n"; 	exit(); }
if (!function_exists('shell_exec'))	{ echo "Shell exex disabled.\n"; 		exit(); }
/*--------------------------------*/


?>

