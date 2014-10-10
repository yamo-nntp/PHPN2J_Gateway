#!/usr/local/bin/php -q
<?php
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
JNTP!/from-jntp:[group pattern]:Tp,Wf:/usr/local/news/bin/n2j_gateway.php %s [server] [from (optional)]

Manualy : /usr/local/news/bin/n2j_gateway.php [token] [server] [from (optional)] 

You can now the token of an Usenet article with the command :
/usr/local/news/bin/grephistory '<Message-ID>'
*/

/*---- CONFIGURATION SECTION -----*/
error_reporting(E_ALL);						// For debug only

define('GW_NAME', 'PHP N2J Gateway');		// Name of this script
define('GW_VERSION', '0.93.r02');			// Version number

$domain = gethostname();					// this fqdn MUST match with the source IP else use optionnal from argv.
define('PATH', 'n2J.'.$domain);				// what will be in the Path section of the NNTP Article
											// If not set, FROM will be used
define('ACTIVE_LOG', 1);					// Set to true for logging 
define('LOG_PATH', '/var/log/news');		// Path where is the logfile (must be writable by news user)
define('LOG_FILE', 'n2j_gateway.log');		// Name of the log file (LOG_PATH must be writable by news user)
define('SM_PATH', '/usr/local/news/bin/sm');

date_default_timezone_set('UTC');			// Default Timezone to UTC (don't touch!!)
/*--------------------------------*/

/*-----    CHECK REQUIRE     -----*/ 
if (!extension_loaded('curl')) 		{ fwrite(STDERR, "CURL Extension is missing.\n"); 		exit(1); }
if (!extension_loaded('mbstring')) 	{ fwrite(STDERR, "mbstring extension is missing.\n"); 	exit(1); }
if (!extension_loaded('iconv')) 	{ fwrite(STDERR, "iconv extension is missing.\n"); 		exit(1); }
if (!extension_loaded('json'))		{ fwrite(STDERR, "json extension is missing.\n");  		exit(1); }
if (!function_exists('shell_exec'))	{ fwrite(STDERR, "Shell exec disabled.\n"); 			exit(1); }
/*--------------------------------*/

if (isset($argv)) {
	if (!empty($argv[1])) $token = $argv[1];	else { fwrite(STDERR, "token is missing.\n");	exit(1); }
	if (!empty($argv[2])) $server = $argv[2];	else { fwrite(STDERR, "server is missing.\n");	exit(1); }
	if (!empty($argv[3])) $domain = $argv[3];	// Set From with the 3rd argv
}

$CURL = curl_init();
if(empty($CURL)) { fwrite(STDERR, "CURL not ready.\n"); exit(1); }

$article = NNTP::articleN2J(shell_exec(SM_PATH." ".$token));

$options = array(
	CURLOPT_URL            => 'http://'.$server.'/jntp/',
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HEADER         => false,
	CURLOPT_FAILONERROR    => true,
	CURLOPT_POST           => true,
	CURLOPT_POSTFIELDS     => '',
	CURLOPT_TIMEOUT	       => 20
);

$post = null;
$post[0] = "ihave";
$post[1]{'Jid'} = array($article{'Jid'});
$post[1]{'From'} = $domain;

$post = json_encode($post);
$options[CURLOPT_POSTFIELDS] = $post;
NNTP::logGateway($post, $server, '>');

curl_setopt_array($CURL, $options);
$reponse = curl_exec($CURL);
NNTP::logGateway($reponse, $server, '<');
$reponse = json_decode($reponse, true);

if($reponse[0] === 'iwant' && count($reponse[1]{'Jid'}) !=0 && $reponse[1]{'Jid'}[0] === $article{'Jid'})
{
	$post = array();
	$post[0] = "diffuse";
	$post[1]{'From'} = $domain;
	$post[1]{'Packet'} = $article;

	$post = json_encode($post);
	$options[CURLOPT_POSTFIELDS] = $post;
	NNTP::logGateway($post, $server, '>');

	curl_setopt_array($CURL, $options);
	$reponse = curl_exec($CURL);
	NNTP::logGateway($reponse, $server, '<');
	$reponse = json_decode($reponse, true);
}

curl_close($CURL);


/*----- Class NNTP From PhpNemoServer -----*/

class NNTP
{
	static function articleN2J($txt)
	{
		
		$article = null;
		$article{'Jid'} = null;
		$article{'Route'} = array();
		$article{'Data'}{'DataType'} = "Article";
		$article{'Data'}{'References'} = array();
		$article{'Data'}{'FollowupTo'} = array();
		$article{'Data'}{'NNTPHeaders'} = null;
		$article{'Data'}{'Protocol'} = "JNTP-Transitional";
		$article{'Data'}{'Origin'} = "NNTP";

		$pos =  strpos($txt, "\n\n");
		$head = substr($txt, 0, $pos);
		$body = substr($txt, $pos+2);
		$lignes = preg_split("/\n(?![ \t])/", $head);
		$isContentType = false;
		$isInjectionDate = false;
		mb_internal_encoding('UTF-8');

		foreach ($lignes as $ligne)
		{
			$pos = strpos($ligne, ": ");
			$header = substr($ligne, 0, $pos);
			$champ = strtolower($header);
			$value = substr($ligne, $pos + 2);

			if($champ === "path" && strrpos($value, "!from-jntp"))
			{
				exit(1);
			}
			elseif($champ === "jntp-protocol")
			{
				exit(1);
			}
			elseif($champ === "control")
			{
				$args = array();
				$args = explode(" ", trim($value));

				if ($args[0] === 'cancel')
				{
					$args[1] = substr($args[1], 1, strlen($args[1])-2);
					$article{'Data'}{'Control'} = $args;
				}
				elseif ($args[0] === 'newgroup')
				{
					$article{'Data'}{'Control'} = $args;
				}
				elseif ($args[0] === 'rmgroup')
				{
					$article{'Data'}{'Control'} = $args;
				}
				elseif ($args[0] === 'checkgroups')
				{
					$article{'Data'}{'Control'} = $args;
				}
			}
			elseif($champ === "supersedes") 
			{
				$article{'Data'}{'Supersedes'} = substr($value, 1, strlen($value)-2);
			}
			elseif($champ === "message-id") 
			{
				$value = trim($value);
				$article{'Jid'} = substr($value, 1, strlen($value)-2);
			}
			elseif($champ === "from") 
			{
				$from = iconv_mime_decode($value, 2, 'UTF-8');
				preg_match('#<(.*?)>#', $from, $mail);
				preg_match('#\s*(.*?)\s*<#', $from, $name);
				$article{'Data'}{'FromName'} = "".$name[1];
				$article{'Data'}{'FromMail'} = ($mail[1]) ? $mail[1] : $from;
	
			}
			elseif($champ === "subject")
			{
				$article{'Data'}{'Subject'} = iconv_mime_decode($value, 2, 'UTF-8');
			}
			elseif($champ === "newsgroups")
			{
				$groupes = explode(",", $value);
				foreach($groupes as $groupe)
				{
					$groupe = trim($groupe);
				}
				$article{'Data'}{'Newsgroups'} = $groupes;
			}
			elseif($champ === "followup-to") 
			{
				$groupes = explode(",", $value);
				foreach($groupes as $groupe)
				{
					$groupe = trim($groupe);
				}
				$article{'Data'}{'FollowupTo'} = $groupes;
			}
			elseif($champ === "references")
			{
				$references = preg_split("/[<>, \n\t]+/", $value, 0, PREG_SPLIT_NO_EMPTY);
				if(count($references) > 0)
				{
					$article{'Data'}{'References'} = preg_split("/[<>, \n\t]+/", $value, 0, PREG_SPLIT_NO_EMPTY);
				}
			}
			elseif($champ === "user-agent")
			{
				$article{'Data'}{'UserAgent'} = $value;
			}
			elseif($champ === "reply-to")
			{
				$article{'Data'}{'ReplyTo'} = $value;
			}
			elseif($champ === "organization")
			{
				$article{'Data'}{'Organization'} = iconv_mime_decode($value, 2, 'UTF-8');
			}
			elseif($champ === "content-type")
			{
				$token = "[A-Za-z0-9\\-_.]+";
				$optFWS = "(?:\n?[ \t])*";
				$pattern = "charset{$optFWS}={$optFWS}(\"?)({$token})\\1";
				$regexp = "/;{$optFWS}{$pattern}{$optFWS}(?:[(;]|$)/i";
				$charset = preg_match($regexp, $value, $matches) ? $matches[2] : "UTF-8";
				$isContentType = true;
				$article{'Data'}{'NNTPHeaders'}{'Content-Type'} = $value;
			}
			elseif($champ === "content-transfer-encoding")
			{
				if(strstr(strtolower($value), "quoted-printable"))
				{
					$body = quoted_printable_decode($body);
				}
				elseif(strstr($value, "base64"))
				{
					$body = base64_decode($body);
				}
				$article{'Data'}{'NNTPHeaders'}{'Content-Transfer-Encoding'} = $value;
			}
			elseif($champ === "nntp-posting-date")
			{
				$article{'Data'}{'NNTPHeaders'}{'NNTP-Posting-Date'} = $value;
			}
			elseif($champ === "injection-date")
			{
				$article{'Data'}{'NNTPHeaders'}{'Injection-Date'} = $value;		
			}
			elseif($champ === "date")
			{
				$article{'Data'}{'NNTPHeaders'}{'Date'} = $value;
			}
			elseif($champ === "x-trace")
			{
				$article{'Data'}{'NNTPHeaders'}{'X-Trace'} = $value;
				if (strpos($value,"("))
				{
					$start = strpos($value,"(") + 1;
					$end =  strpos($value,")",$start);
					$xtracedate = substr($value, $start, $end - $start);
				}
			}
			elseif ($champ !== "xref")
			{
				$article{'Data'}{'NNTPHeaders'}{$header} = $value;
			}
		}
		
		/*
			Take first Référence if présent for ThreadID
		*/
		if(count($article{'Data'}{'References'}) == 0)
		{
			$article{'Data'}{'ThreadID'} = $article{'Jid'};
		}

		$article{'Data'}{'NNTPHeaders'}{'Gateway'} = GW_NAME.' '.GW_VERSION;
		if(!$isContentType){
			$charset = mb_detect_encoding($body);
			$article{'Data'}{'NNTPHeaders'}{'CharsetDetect'} = $charset;
		}


		if($article{'Data'}{'NNTPHeaders'}{'NNTP-Posting-Date'})
		{
			$injection_date = new DateTime($article{'Data'}{'NNTPHeaders'}{'NNTP-Posting-Date'});
			$injection_date->setTimezone(new DateTimeZone('UTC'));
			//$article{'Data'}{'NNTPHeaders'}{'X-InjectionDate-Header'} = 'NNTP-Posting-Date';
		}
		elseif($article{'Data'}{'NNTPHeaders'}{'Injection-Date'})
		{
			$injection_date = new DateTime($article{'Data'}{'NNTPHeaders'}{'Injection-Date'});
			$injection_date->setTimezone(new DateTimeZone('UTC'));
			//$article{'Data'}{'NNTPHeaders'}{'X-InjectionDate-Header'} = 'Injection-Date';		
		}
		elseif(isset($xtracedate))
		{
			$injection_date = new DateTime($xtracedate);
			$injection_date->setTimezone(new DateTimeZone('UTC'));
			//$article{'Data'}{'NNTPHeaders'}{'X-InjectionDate-Header'} = 'X-Trace';			
		}
		elseif($article{'Data'}{'NNTPHeaders'}{'Date'})
		{
			$injection_date = new DateTime($article{'Data'}{'NNTPHeaders'}{'Date'});
			$injection_date->setTimezone(new DateTimeZone('UTC'));
			//$article{'Data'}{'NNTPHeaders'}{'X-InjectionDate-Header'} = 'Date';	
		}
		else
		{
			exit(1);
		}
	
		$now = new DateTime("now");
		$now->setTimezone(new DateTimeZone('UTC'));

		if ($injection_date->getTimestamp() > $now->getTimestamp()) 
		{
			$date_offset = $now->diff($injection_date);
			//$article{'Data'}{'NNTPHeaders'}{'X-InjectionDate-Offset'} = $date_offset->format('%R%H:%I:%S');
			$injection_date = $now;
		}
		$article{'Data'}{'NNTPHeaders'}{'Path'} = PATH.'!'.$article{'Data'}{'NNTPHeaders'}{'Path'};
		$article{'Data'}{'InjectionDate'} = $injection_date->format("Y-m-d\TH:i:s\Z");
		$pattern = '/\n-- \n((.|\n)*|$)/';
		$body = preg_replace($pattern, "\n[signature]$1[/signature]", $body);
		$article{'Data'}{'Body'} = mb_convert_encoding($body, "UTF-8", $charset);

		return $article;
	}
	
	static function logGateway($post, $server, $direct = '<')
	{
		if(ACTIVE_LOG)
		{
			$handle = fopen(LOG_PATH.'/'.LOG_FILE, 'a');
			$put = '['.date(DATE_RFC822).'] ['.$server.'] '.$direct.' '.rtrim(mb_strimwidth($post, 0, 300))."\n";
			fwrite($handle, $put);
			fclose($handle);
		}
	}
}
?>
