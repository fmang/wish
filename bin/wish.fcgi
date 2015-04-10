#!/usr/bin/env perl

use strict;
use warnings;
use utf8;

use CGI qw(:standard);
use CGI::Carp;
use CGI::Fast;
use Encode qw(decode);
use File::Spec::Functions qw(catdir);
use Getopt::Long qw(:config no_auto_abbrev);
use Wish::Edict2;
use Wish::KanjiDic;
use Wish::Unicode qw(kanjis);

my $usage = <<EOF;
Usage: wish.fcgi [-d DATABASE]
       wish.fcgi --help
EOF

my $help = <<EOF;
$usage
Options:
  -d, --database        Specify the location of the database directory.
  -h, --help            Show this help.
EOF

my $dbdir = $ENV{HOME} ? catdir($ENV{HOME}, '.wish') : 'db';
my $show_help;

GetOptions(
	'database|d=s' => \$dbdir,
	'help|h' => \$show_help,
) or die($usage);

if ($show_help) {
	print($help);
	exit(0);
}

my $kanjidic = Wish::KanjiDic->new($dbdir);
my $edict = Wish::Edict2->new($dbdir);

$kanjidic && $edict or die("Couldn't open the dictionary database at $dbdir: $!.\n");

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

################################################################################
# HTML generation

sub html_list {
	print "<ul>\n";
	print '<li>' . escapeHTML($_) . "</li>\n" for @_;
	print "</ul>\n";
}

sub hl_list {
	my $hl = shift;
	print "<ul>\n";
	for (@_) {
		my $li = escapeHTML($_) =~ s{($hl)}{<b>$1</b>}gr;
		print "<li>$li</li>\n";
	}
	print "</ul>\n";
}

sub word_entry {
	my ($e, $hl) = @_;
	print "<div class=\"entry\">\n";
	my $w = $e->{words} || $e->{readings};
	print '<h4>' . join(' / ', map { escapeHTML($_) =~ s{($hl)}{<b>$1</b>}gr } @$w) . "</h4>\n";

	print "<h5>Readings</h5>";
	$e->{readings} and hl_list($hl, @{$e->{readings}});
	print "<h5>Meanings</h5>";
	$e->{meanings} and hl_list($hl, @{$e->{meanings}});
	print "<h5>Part-of-speech</h5>\n";
	$e->{pos} and hl_list($hl, @{$e->{pos}});

	print "</div>\n";
}

sub kanji_entry {
	my $k = shift;
	print "<div class=\"kanji\">\n";
	print "<h4>" . escapeHTML($k->{kanji}) . "</h4>\n";

	print "<h5>On Readings</h5>\n";
	$k->{on} and html_list(@{$k->{on}});
	print "<h5>Kun Readings</h5>\n";
	$k->{kun} and html_list(@{$k->{kun}});
	print "<h5>Nanori</h5>\n";
	$k->{nanori} and html_list(@{$k->{nanori}});
	print "<h5>Meanings</h5>\n";
	$k->{english} and html_list(@{$k->{english}});

	print "</div>\n";
}

################################################################################

sub search_page {
	print header(-type => 'text/html', -charset => 'utf-8');
	my $q = param('q');
	$q = $q ? decode utf8 => $q : '';
	my $escaped_q = escapeHTML($q);
	my $title = $q ? "$escaped_q - Wish" : "Wish";
	print <<"EOF";
<html>
	<head>
		<title>$title</title>
	</head>
	<body>
		<h1>Wish</h1>
		<h2>$escaped_q</h2>
EOF
	if ($q) {

		if ($q =~ /^[1-4]-[0-9]*-[0-9]*$/) {
			print "<h3>SKIP Lookup</h3>\n";
			my @r = sort($kanjidic->skip_lookup($q));
			html_list(@r);

		} else {
			print "<h3>Kanji</h3>\n";
			my @ks = map { $kanjidic->lookup($_) } sort(kanjis($q));
			kanji_entry($_) for @ks;

			print "<h3>Words</h3>\n";
			my $hl = quotemeta($q);
			my @words = $edict->search($q);
			word_entry($_, $hl) for @words;
			if ($q =~ /\p{Han}/) {
				print "<h3>Homophones</h3>\n";
				my @hom = $edict->homophones($q);
				word_entry($_, $hl) for @hom;
			}
		}
	}

	print <<EOF;
	</body>
</html>
EOF
}
