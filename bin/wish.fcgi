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

=head2 Webserver configuration

Wish doesn't require any FastCGI settings but the standard ones. However, the
webserver has to redirect the F</static> location to the path specified during the
install.

=head3 About FastCGI

CGI and FastCGI are standard interfaces for webservers to call programs that
generate dynamic pages. Each web server has its own way to configure these.

CGI is highly discouraged as the database would be reloaded every time a page
is generated. Use FastCGI instead.

=head3 Spawning

Spawning a FastCGI application is done using a tool like B<spawn-fcgi> or
B<multiwatch>. These tools spawn the FastCGI process and create a socket for
the webserver to connect to.

The path you choose for the socket is to be specified in the webserver's
configuration file.

=head3 Nginx

Here's a sample nginx configuration fragment:

    server {
        listen 80;
        listen [::]:80; # IPv6
        charset utf-8;
        location / {
            include fastcgi.conf;
            fastcgi_pass unix:/run/wish/wish.sock; # see the section above
        }
        location /static/ {
            alias /usr/share/wish/static/;
        }
    }

=head1 OPTIONS

=over 4

=item B<-d>, B<--database>

Specify the location of the database directory.

By default, F<~/.wish> is used.

=item B<-h>, B<--help>

Display a brief help.

=back

=head1 SEE ALSO

L<wdic(1)>, L<spawn-fcgi(1)>, L<multiwatch(1)>.

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

################################################################################
# HTML generation

my %markers = (
	# Part of speech
	'adj-i' => 'Adjective (keiyoushi)',
	'adj-na' => 'Adjectival nouns or quasi-adjectives (keiyodoshi)',
	'adj-no' => 'Nouns which may take the genitive case particle &lsquo;no&rsquo;',
	'adj-pn' => 'Pre-noun adjectival (rentaishi)',
	'adj-t' => '&lsquo;taru&rsquo; adjective',
	'adj-f' => 'Noun or verb acting prenominally',
	'adj' => 'Adjective', # former adjective classification (being removed)
	'adv' => 'Adverb (fukushi)',
	'adv-n' => 'Adverbial noun',
	'adv-to' => 'Adverb taking the &lsquo;to&rsquo; particle',
	'aux' => 'Auxiliary',
	'aux-v' => 'Auxiliary verb',
	'aux-adj' => 'Auxiliary adjective',
	'conj' => 'Conjunction',
	'ctr' => 'Counter',
	'exp' => 'Expressions (phrases, clauses, etc.)',
	'int' => 'Interjection (kandoushi)',
	'iv' => 'Irregular verb',
	'n' => 'Noun (futsuumeishi)',
	'n-adv' => 'Adverbial noun (fukushitekimeishi)',
	'n-pref' => 'Noun, used as a prefix',
	'n-suf' => 'Noun, used as a suffix',
	'n-t' => 'Noun (temporal) (jisoumeishi)',
	'num' => 'Numeric',
	'pn' => 'Pronoun',
	'pref' => 'Prefix',
	'prt' => 'Particle',
	'suf' => 'Suffix',
	'v1' => 'Ichidan verb',
	'v2a-s' => 'Nidan verb with &lsquo;u &rsquo;ending (archaic)',
	'v4h' => 'Yodan verb with &lsquo;hu/fu&rsquo; ending (archaic)',
	'v4r' => 'Yodan verb with &lsquo;ru&rsquo; ending (archaic)',
	'v5' => 'Godan verb (not completely classified)',
	'v5aru' => 'Godan verb - -aru special class',
	'v5b' => 'Godan verb with &lsquo;bu&rsquo; ending',
	'v5g' => 'Godan verb with &lsquo;gu&rsquo; ending',
	'v5k' => 'Godan verb with &lsquo;ku&rsquo; ending',
	'v5k-s' => 'Godan verb - iku/yuku special class',
	'v5m' => 'Godan verb with &lsquo;mu&rsquo; ending',
	'v5n' => 'Godan verb with &lsquo;nu&rsquo; ending',
	'v5r' => 'Godan verb with &lsquo;ru&rsquo; ending',
	'v5r-i' => 'Godan verb with &lsquo;ru&rsquo; ending (irregular verb)',
	'v5s' => 'Godan verb with &lsquo;su&rsquo; ending',
	'v5t' => 'Godan verb with &lsquo;tsu&rsquo; ending',
	'v5u' => 'Godan verb with &lsquo;u&rsquo; ending',
	'v5u-s' => 'Godan verb with &lsquo;u&rsquo; ending (special class)',
	'v5uru' => 'Godan verb - uru old class verb (old form of Eru)',
	'v5z' => 'Godan verb with &lsquo;zu&rsquo; ending',
	'vz' => 'Ichidan verb - zuru verb - (alternative form of -jiru verbs)',
	'vi' => 'Intransitive verb',
	'vk' => 'Kuru verb - special class',
	'vn' => 'Irregular nu verb',
	'vs' => 'Noun or participle which takes the aux. verb suru',
	'vs-c' => 'su verb - precursor to the modern suru',
	'vs-i' => 'suru verb - irregular',
	'vs-s' => 'suru verb - special class',
	'vt' => 'Transitive verb',

	# Field of application
	'Buddh' => 'Buddhist term',
	'MA' => 'Martial arts term',
	'comp' => 'Computer terminology',
	'food' => 'Food term',
	'geom' => 'Geometry term',
	'gram' => 'Grammatical term',
	'ling' => 'Linguistics terminology',
	'math' => 'Mathematics',
	'mil' => 'Military',
	'physics' => 'Physics terminology',
	'biol' => 'Biology',

	# Misc
	'X' => 'Rude or X-rated term',
	'abbr' => 'Abbreviation',
	'arch' => 'Archaism',
	'ateji' => 'Ateji (phonetic) reading',
	'chn' => 'Children&apos;s language',
	'col' => 'Colloquialism',
	'derog' => 'Derogatory term',
	'eK' => 'Exclusively kanji',
	'ek' => 'Exclusively kana',
	'fam' => 'Familiar language',
	'fem' => 'Female term or language',
	'gikun' => 'Gikun (meaning) reading',
	'hon' => 'Honorific or respectful (sonkeigo) language',
	'hum' => 'Humble (kenjougo) language',
	'ik' => 'Word containing irregular kana usage',
	'iK' => 'Word containing irregular kanji usage',
	'id' => 'Idiomatic expression',
	'io' => 'Irregular okurigana usage',
	'm-sl' => 'Manga slang',
	'male' => 'Male term or language',
	'male-sl' => 'Male slang',
	'oK' => 'Word containing out-dated kanji',
	'obs' => 'Obsolete term',
	'obsc' => 'Obscure term',
	'ok' => 'Out-dated or obsolete kana usage',
	'on-mim' => 'Onomatopoeic or mimetic word',
	'poet' => 'Poetical term',
	'pol' => 'Polite (teineigo) language',
	'rare' => 'Rare (now replaced by "obsc")',
	'sens' => 'Sensitive word',
	'sl' => 'Slang',
	'uK' => 'Word usually written using kanji alone',
	'uk' => 'Word usually written using kana alone',
	'vulg' => 'Vulgar expression or word',
	'P' => 'Common',
);

sub html_list {
	my $class = shift;
	@_ == 0 and $class .= ' empty';
	@_ == 1 and $class .= ' single';
	print "<ol class=\"$class\">\n";
	print "<li>$_</li>\n" for @_;
	print "</ol>\n";
}

sub inline {
	my $hl = shift;
	join ', ' => map {
		$_ = escapeHTML($_);
		$hl and s{($hl)}{<b>$1</b>}g;
		s{\(([^()]*)\)}{marker($1)}eg;
		$_
	} @_;
}

sub marker {
	my $m = shift;
	my $tooltip = $markers{$m};
	$tooltip = " title=\"$tooltip\"" if $tooltip;
	"<span class=\"marker\"$tooltip>$m</span>"
}

sub word_entry {
	my ($e, $hl) = @_;
	print "<div class=\"entry\">\n";

	my $w = $e->{words} || $e->{readings};
	print '<span class="heading">' . inline($hl, @$w) . "</span>\n";

	if ($e->{readings}) {
		print '<span class="readings">['
		    . inline($hl, @{$e->{readings}})
		    . "]</span>\n";
	}

	if ($e->{pos}) {
		print '<span class="pos">'
		. join(', ', map { marker($_) } sort(@{$e->{pos}}))
		. "</span>\n";
	}

	$e->{meanings} and html_list('meanings', map {
		$_ = escapeHTML($_);
		s@([\({])([a-zA-Z\-]+)([\)}])@$1.marker($2).$3@eg;
		s@\(See ([^\)]+)\)@'(See <a href="?q='.$1.'">'.$1.'</a>)'@eg;
		$_
	} @{$e->{meanings}});

	print "</div>\n"; # .entry
}

sub readings {
	my ($k, $field, $title) = @_;
	$k->{$field} && @{$k->{$field}} or return;
	print
	"<tr class=\"$field\">\n"
	. "<td><b>$title</b></td>\n"
	. '<td>' . join(', ', map {
		$_ = escapeHTML($_);
		$field eq 'kun' and s{\.(.*)$}{<span class="okurigana">&middot;$1</span>};
		$_
	} @{$k->{$field}}) . "</td>\n"
	. "</tr>\n";
}

sub kanji_entry {
	my $k = shift;
	print "<div class=\"kanji\">\n";
	print '<div class="heading">' . escapeHTML($k->{kanji}) . "</div>\n";

	print "<table class=\"readings\">\n";
	readings($k, on => '&#x97F3;');
	readings($k, kun => '&#x8A13;');
	readings($k, nanori => '&#x540D;');
	readings($k, english => '&#x82F1;'); # well, not really readings but eh
	print "</table>\n";

	print "</div>\n"; # .kanji
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
		print "<div class=\"nothing\">No words.</div>\n";
	}
	if ($homophones && @$homophones) {
		print "<h4>Homophones</h4>\n";
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
		<meta name="viewport" content="width=device-width, initial-scale=1" />
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
