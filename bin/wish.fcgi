#!/usr/bin/env perl

=encoding utf8

=head1 NAME

wish.fcgi - Japanese dictionary lookup web interface

=head1 SYNOPSIS

wish.fcgi [-d DATABASE]

=head1 DESCRIPTION

Wish is a Perl tool for looking up words and kanjis from a Japanese word and
kanji database. The current supported formats are KANJIDIC and EDICT2.

B<wish.fcgi> is its FastCGI interface, also compatible with CGI.

=head2 Managing dictionaries

Databases are created and managed by the B<wdic> tool. See its man page for
further information.

=head2 About FastCGI

CGI and FastCGI are standard interfaces for web servers to call programs that
generate dynamic pages. Each web server has its own way to configure these.
Wish doesn't require any settings but the standard ones.

CGI is highly discouraged as the database would be reloaded every time a page
is generated. Use FastCGI instead.

=head1 OPTIONS

=over 4

=item B<-d>, B<--database>

Specify the location of the database directory.

By default, F<~/.wish> is used.

=item B<-h>, B<--help>

Display a brief help.

=back

=head1 SEE ALSO

L<wdic(1)>, L<spawn-fcgi(1)>.

=cut

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

sub skip_block {
	my ($q, $kanjis) = @_;
	my $escaped_q = escapeHTML($q);
	print "<h2>SKIP $escaped_q</h2>\n";
	print "<div class=\"skip\">\n";
	for (@$kanjis) {
		my $k = escapeHTML($_);
		print "<a href=\"?q=$k\">$k</a>\n";
	}
	print "</div>\n";
}

sub results_block {
	my ($q, $kanjis, $words, $homophones) = @_;
	my $escaped_q = escapeHTML($q);
	my $hl = escapeHTML(quotemeta($q));

	print "<h2>$escaped_q</h2>\n";
	print "<div class=\"results\">\n";
	if ($kanjis && @$kanjis) {
		print "<div class=\"kanjis\">\n";
		print "<h3>Kanji</h3>\n";
		kanji_entry($_) for @$kanjis;
		print "</div>\n";
	}
	print "<div class=\"words\">\n";
	print "<h3>Words</h3>\n";
	if ($words && @$words) {
		word_entry($_, $hl) for @$words;
	} else {
		print "<span class=\"nothing\">No words.</span>\n";
	}
	if ($homophones && @$homophones) {
		print "<h3>Homophones</h3>\n";
		word_entry($_, $hl) for @$homophones;
	}
	print "</div>\n"; # .words
	print "</div>\n"; # .results
}

################################################################################

sub search_page {
	print header(-type => 'text/html', -charset => 'utf-8');
	my $q_arg = param('q');
	my @qs = $q_arg ? split(/[ ,;]+/, decode('utf8', $q_arg)) : ();
	my $title = @qs ? escapeHTML(join(', ', @qs)) . ' - Wish' : 'Wish';
	print <<"EOF";
<!doctype html>
<html>
	<head>
		<title>$title</title>
		<meta charset="utf-8" />
		<link rel="stylesheet" type="text/css" href="static/wish.css" />
	</head>
	<body>
		<form id="header">
			<input type="text" name="q" placeholder="Search&#8230;" />
			<input type="submit" value="&#x691C;&#x7D22;" />
		</form>
		<div id="main">
EOF
	for my $q (@qs) {

		if ($q =~ /^[1-4]-[0-9]*-[0-9]*$/) {
			my @r = $kanjidic->skip_lookup($q);
			@r = sort(@r);
			skip_block($q, \@r);

		} else {
			my @kanjis = map { $kanjidic->lookup($_) } sort(kanjis($q));
			my @words = $edict->search($q);
			my @homophones = $q =~ /\p{Han}/ ? $edict->homophones($q) : ();
			results_block($q, \@kanjis, \@words, \@homophones);
		}
	}

	print <<EOF;
		</div>
	</body>
</html>
EOF
}
