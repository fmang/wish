#!/usr/bin/env perl

use CGI::Fast qw(:standard);

print "Ready.\n";

while (my $q = new CGI::Fast) {
	print $q->header();
	print "Hello\n";
}
