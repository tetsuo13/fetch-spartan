# Fetch-Spartan

Fetch-spartan is a project started by Andrei Nicholson ([tetsuo13]).

The intent of the project was to be able to read mail from a Lotus Domino mail
server and format it into an RDF so that it could then be read by Thunderbird
or any other program which supports XML feeds.

Lotus Domino Web Access is heavily JavaScript-driven. Program will request the
pages involved in sequential order as if a browser were involved to simulate
normal behaviour and to ensure all JavaScript variables have been set
accordingly. After each successful page request a short pause is needed so as to
not request too many hits too quickly.

Program is centered around Domino Web Access for UNCG, probbly no reason why it
can't be modified to work with other Domino Web Access installations.

Program is extremely slow. This is mainly due to Lotus Domino Web Access being a
pig. Typical page, like the inbox, is around 300kB to download with complete
JavaScripts, CSS, and HTML files.

Has been tested with Lotus Domino 6.5.1-5 at UNCG.

## Usage

Add URL to `index.php` to the reader. Upon first read it will prompt for a
username and password to the Domino account.

Change the extension on `index.php` to RDF or XML if reader complains about it.
