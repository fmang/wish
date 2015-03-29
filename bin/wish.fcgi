#!/usr/bin/env perl

use strict;
use warnings;
use utf8;

use Encode qw(decode);
use CGI qw(:standard);
use CGI::Fast;

print "Ready!\n";

sub search_page {
	print header(-type => 'text/html', -charset => 'utf-8');
	my $query = decode utf8 => param('q');
	my $escaped_query = escapeHTML($query);
	my $title = $query ? "$escaped_query - Wish" : "Wish";
	print <<EOF
<html>
	<head>
		<title>$title</title>
	</head>
	<body>
		<h1>Wish</h1>
		<h2>$escaped_query</h2>
	</body>
</html>
EOF
}

while (new CGI::Fast) {
	my $url = url(-absolute => 1);
	if ($url eq '/') {
		print redirect('/search');
	} elsif ($url eq '/search') {
		search_page();
	} else {
		print header('text/plain', '404 Not Found');
		print "404 Not Found\n";
	}
}
