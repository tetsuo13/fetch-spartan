<?php
/**
 * Fetch-Spartan - Program to retrieve messages from an UNCG E-Spartan account.
 * Copyright (C) 2004 Andrei Nicholson <andre@neo-anime.org>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

define('_VERSION_', '0.6');
define('_PROGRAMNAME_', 'Fetch-Spartan');

require_once('Xml.class.php');

// A unique identifier cookie name.
define('_COOKIENAME_', 'Ebc1961SessionID');

// The useragent to fool the called HTML pages, should they use JavaScript to
// check. Code tested wit FireFox 0.10.1, generated JavaScript could alter if
// Internet Explorer is used; hasn't been tested yet.
define('_USERAGENT_', 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.7.10) Gecko/20050716 Firefox/1.0.6');

// Set to true to write debugging output to stdout.
define('_DEBUG_', false);

// Whether to give a short pause between URL requests.
define('_SLEEP_', false);

// After logging in, this is the domain in which all subsequent anchor links use.
$domino_server = 'email1.uncg.edu';

// The page visited to bring up the login screen.
$login_domain = 'e-spartan.uncg.edu';

// Visiting $login_domain might redirect us to another directory. Leave this blank
// if no redirect occurs.
$login_domain_page = 'uncglogin/index.html';


if (_DEBUG_)
{
    error_reporting(E_ALL);
}

// Verify that PHP has CURL support compiled in.
if (!function_exists('curl_init'))
{
    $error = 'CURL support must be compiled into PHP in order to utilize this script.

In order to compile CURL, you must also compile PHP --with-curl[=DIR]
where DIR is the location of the directory containing the lib and
include directories.

Exiting.';

    flush_output($error);
    exit();
}


// ----------------------------------------------------------------------------
// "Submits" a form on a page via POST.
//
// @arg string $url - The action URI of a form.
// @arg string $args - Any POST arguments to pass along.
// @arg string $referer - The page "viewed" before submitting this form.
// @return string - The resulting HTML of the submitted form.
// ----------------------------------------------------------------------------
function& post_page($url, $args, $referer)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
    curl_setopt($ch, CURLOPT_REFERER, $referer);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, _USERAGENT_);
    curl_setopt($ch, CURLOPT_COOKIEJAR, _COOKIENAME_);
    curl_setopt($ch, CURLOPT_COOKIEFILE, _COOKIENAME_);
    //curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

    $res = curl_exec($ch);
    curl_close($ch);

    return $res;
}

// ----------------------------------------------------------------------------
// Issues a standard URI GET.
//
// @arg string $url - The desired target URL.
// @arg string $referer - The preceeding page.
// @return string - The resulting HTML of the requested URL.
// ----------------------------------------------------------------------------
function& get_page($url, $referrer)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_REFERER, $referrer);
    curl_setopt($ch, CURLOPT_USERAGENT, _USERAGENT_);
    curl_setopt($ch, CURLOPT_COOKIEJAR, _COOKIENAME_);
    curl_setopt($ch, CURLOPT_COOKIEFILE, _COOKIENAME_);

    $res = curl_exec($ch);
    curl_close($ch);

    return $res;
}

// ----------------------------------------------------------------------------
// Domino displays dates as "20041101T161749,55Z". Parse this into a valid RDF
// date and return.
// Don't know what the ",55" is.
//
// @arg string $domino_date
// @return string
// ----------------------------------------------------------------------------
function& make_date($domino_date)
{
    $ret  = substr($domino_date, 0, 4);
    $ret .= '-';
    $ret .= substr($domino_date, 4, 2);
    $ret .= '-';
    $ret .= substr($domino_date, 6, 5);
    $ret .= ':';
    $ret .= substr($domino_date, 11, 2);
    $ret .= ':';
    $ret .= substr($domino_date, 13, 2);

    return $ret;
}

// ----------------------------------------------------------------------------
// Prints out a tab $times number of times. The "tab" may either be a \t character
// or a given number of spaces.
//
// @arg int $times - Number of tabs to print.
// @return void
// ----------------------------------------------------------------------------
function tab($times = 1)
{
    for ($i = 0; $i < $times; $i++)
    {
        print("\t");
    }
}

// ----------------------------------------------------------------------------
// Compiles all of the information gathered from the mailbox and generates an
// XML-compliant page, with each of the messages as an entry.
//
// @arg array $messages - Array indexed by message unid containing all messages.
// @return void
// ----------------------------------------------------------------------------
function print_xml(&$messages)
{
    global $login_domain;

    header('Expires: '.gmdate('D, d M Y H:i:s', time()).' GMT');
    header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
    header('Content-type: application/xml', true);

    print('<?xml version="1.0" encoding="iso-8859-1"?>'."\n");
    print<<<HTML
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns="http://purl.org/rss/1.0" xmlns:sy="http://purl.org/rss/1.0/modules/syndication/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:slash="http://purl.org/rss/1.0/modules/slash/">

HTML;

    print(tab(1)."<channel rdf:about=\"http://$login_domain/\">\n");
    print(tab(2)."<title>UNCG e-Spartan</title>\n");
    print(tab(2)."<link>http://$login_domain/</link>\n");
    printf(tab(2)."<modified>%s</modified>\n", date('Y-m-d\TH:i:s-05:00'));
    print(tab(2)."<creator>andre@neo-anime.org</creator>\n");
    print(tab(2)."<sy:updatePeriod>hourly</sy:updatePeriod>\n" );
    print(tab(2)."<sy:updateFrequency>6</sy:updateFrequency>\n" );
    print(tab(2)."<sy:updateBase>2000-01-01T12:00+00:00</sy:updateBase>\n" );
    print(tab(1)."</channel>\n");

    foreach ($messages as $unid => $detail)
    {
        print(tab(1)."<item>\n");
        printf(tab(2)."<title>%s</title>\n", $detail['Subject']);
        printf(tab(2)."<issued>%sZ</issued>\n", make_date($detail['Date']));
        print(tab(2)."<sunh>$unid</sunh>\n");

        print(tab(2)."<author>\n");
        printf(tab(3)."<name><![CDATA[%s]]></name>\n", $detail['From']);

        if (!empty($detail['X-Mailer']))
        {
            printf(tab(3)."<xmailer>%s</xmailer>\n", $detail['X-Mailer']);
        }
        else
        {
            print(tab(3)."<xmailer />\n");
        }

        print(tab(2)."</author>\n");

        printf(tab(2).'<content><![CDATA[%s]]></content>'."\n", $detail['Body']);
        print(tab(1)."</item>\n");
    }

    print('</rdf:RDF>');
}

// ----------------------------------------------------------------------------
// Display everything in the output buffer. Normally this is done at the end of
// the script.
//
// @arg string $str
// @return void
// ----------------------------------------------------------------------------
function flush_output($str)
{
    if (!_DEBUG_)
    {
        return;
    }

    print($str);
    ob_flush();
    flush();
}

// ----------------------------------------------------------------------------
// Parse through returned XML for all message UNIDs in the current folder view.
//
// @arg string $message_list - XML containing basic info about each message.
// @return array - Reference to array whose keys hold the message unids.
// ----------------------------------------------------------------------------
function& fill_message_unid(&$message_list)
{
    $ret_array = array();

    $xml = new Xml($message_list);

    for ($i = 0; $i < count($xml->data_keys['VIEWENTRY']); $i++)
    {
        $tag_detail = $xml->data_vals[$xml->data_keys['VIEWENTRY'][$i]];

        if ($tag_detail['type'] != 'open')
        {
            continue;
        }

        if (!isset($tag_detail['attributes']['UNID']))
        {
            continue;
        }

        $ret_array[$tag_detail['attributes']['UNID']] = '';
    }

    return $ret_array;
}

// ----------------------------------------------------------------------------
//
// @arg array $messages - Array whose keys are the message unid.
// @arg string $username - Domino login name.
// @arg string $s_UNH - Unique session identifier.
// @return void
// ----------------------------------------------------------------------------
function fill_message_info(&$messages, $username, $s_UNH)
{
    global $domino_server;

    foreach ($messages as $unid => $val)
    {
        flush_output('  -> Fetching $unid...');

        $res =& get_page("https://$domino_server/mail/$username.nsf/(\$All)/$unid/?OpenDocument&PresetFields=s_ViewName;(%24All),s_FromMail;1,s_SortBy;4",
            "https://$domino_server/mail/$username.nsf/iNotes/Mail/?OpenDocument&PresetFields=s_ViewLabel;All%20Documents,s_ViewName;(%24All)&KIC&UNH=$s_UNH");

        $messages[$unid]['Body']        =& parse_js("var BodyHtml= '(.*)';",        &$res);
        $messages[$unid]['Subject']     =& parse_js("var SubjectHtml= '(.*)';",     &$res);
        $messages[$unid]['Date']        =& parse_js("var s_DocCreated = '(.*)';",   &$res);
        $messages[$unid]['To']          =& parse_js("var SendTo = '(.*)';",         &$res);
        $messages[$unid]['User-Agent']  =& parse_js("var USER_AGENT = '(.*)';",     &$res);
        $messages[$unid]['X-Mailer']    = $messages[$unid]['User-Agent'];

        // For most of the time, this JavaScript variable will comprise of the
        // sender's name and email address.
        $from_name =& parse_js("var From = '(.*)';", &$res);

        // Other times, for some weird UNCG accounts, this variable will be
        // defined and will contain the actual email address while From will
        // contain an LDAP path in the form of:
        //     CN=Jason J Johnson JJJOHNS2/OU=facultystaff/O=uncg
        $from_email =& parse_js("var INETFROM = '(.*)';", &$res);

        if (!empty($from_email))
        {
            $messages[$unid]['From'] = "$from_name <$from_email>";
        }
        else
        {
            $messages[$unid]['From'] = $from_name;
        }

        // Newline characters are instead "\n" strings. Strip out all newline
        // variations and replace them with actual newline characters.
        $messages[$unid]['Body'] = str_replace('\r\n', "\n", $messages[$unid]['Body']);
        $messages[$unid]['Body'] = str_replace('\r', "", $messages[$unid]['Body']);
        $messages[$unid]['Body'] = str_replace('\n', "\n", $messages[$unid]['Body']);
        $messages[$unid]['Body'] = str_replace('\\n\n', "\n\n", $messages[$unid]['Body']);

        flush_output("done.\n");

        if (_SLEEP_)
        {
            usleep(500000);
        }
    }
}

// ----------------------------------------------------------------------------
// Performs a regular expression match against $input searching for $preg.
//
// @arg string $preg
// @arg string $input
// @return void
// ----------------------------------------------------------------------------
function& parse_js($preg, &$input)
{
    $tmp = preg_match("/$preg/", $input, $matches);

    if (count($matches))
    {
        return $matches[1];
    }
    else
    {
        return '';
    }
}


// If user hasn't logged in yet, get login info.
if (!isset($_SERVER['PHP_AUTH_USER']))
{
    header('WWW-Authenticate: Basic realm="e-Spartan login"');
    header('HTTP/1.0 401 Unauthorized');

    print('e-Spartan uses UNCG Novell usernames and passwords. ');
    print('The same username and password used to login to the UNCG network.');
    exit();
}

$username = $_SERVER['PHP_AUTH_USER'];
$password = $_SERVER['PHP_AUTH_PW'];


$preable  = sprintf("\n%s v%s Copyright (C) 2005 Andrei Nicholson <andre@neo-anime.org>\n",
    _PROGRAMNAME_, _VERSION_);
$preable .= sprintf("%s comes with ABSOLUTELY NO WARRANTY.\n", _PROGRAMNAME_);
$preable .= "This is free software, and you are welcome to redistribute it\n";
$preable .= "under certain conditions; see the file COPYING for details.\n\n";
flush_output($preable);

// First log in.
flush_output("Authenticaing to E-Spartan as $username...");
post_page("https://$login_domain/names.nsf?Login",
    "%%ModDate=&Username=$username&password=$password&RedirectTo=/homepage.nsf?Open",
    "https://$login_domain/$login_domain_page");
flush_output("done.\n");

if (_SLEEP_)
{
    sleep(1);
}

// After authenticating, a "please wait" page is shown with a meta-refresh in
// the head tag. Don't bother waiting, just continue.
flush_output('Logging in to mailbox...');
$res =& get_page("https://$domino_server/mail/$username.nsf",
    "https://$login_domain/names.nsf?Login");
flush_output("done.\n");

if (_SLEEP_)
{
    sleep(1);
}

// Domino generates a unique identifier upon initial request and stores it
// into a global JavaScript variable.
$s_UNH =& parse_js("var s_UNH = '(.*)';", &$res);

if (_SLEEP_)
{
    sleep(1);
}

// Request the "All Documents" view.
flush_output('Requesting all documents view...');
get_page("https://$domino_server/mail/$username.nsf/iNotes/Mail/?OpenDocument&PresetFields=s_ViewLabel;All%20Documents,s_ViewName;(%24All)&KIC&UNH=$s_UNH",
    "https://$domino_server/mail/$username.nsf");
flush_output("done.\n");

flush_output('Fetching XML list of documents...');
$message_list =& get_page("https://$domino_server/mail/$username.nsf/iNotes/Proxy/?OpenDocument&Form=s_ReadViewEntries&PresetFields=FolderName;(\$All),s_UsingHttps;1,noPI;1,UnreadOnly;0&TZType=UTC&Start=1&Count=40&resortdescending=4",
    "https://$domino_server/mail/$username.nsf/iNotes/Mail/?OpenDocument&PresetFields=s_ViewLabel;All%20Documents,s_ViewName;(%24All)&KIC&UNH=$s_UNH");
flush_output("done.\n");

$messages =& fill_message_unid(&$message_list);

flush_output("Found ".count($messages)." messages, retrieving\n");

fill_message_info(&$messages, $username, $s_UNH);

print_xml(&$messages);


// Remove the cookie when we're all finished.
unlink(_COOKIENAME_);

?>
