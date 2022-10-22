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

In the case a system default database was set up, usually in
F</usr/share/wish/db>, that one would be used as a fallback instead of creating
a new database in the user's home directory.

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
use Encode qw(encode_utf8 decode_utf8);
use File::Spec::Functions qw(catdir);
use Getopt::Long qw(:config no_auto_abbrev);
use JSON::XS;
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

my $dbdir;
my $show_help;

GetOptions(
	'database|d=s' => \$dbdir,
	'help|h' => \$show_help,
) or die($usage);

if ($show_help) {
	print($help);
	exit(0);
}

if (!$dbdir) {
	my $home = catdir($ENV{HOME} || '.', '.wish');
	my $shared_db = undef; # fill with autoconf & al.
	if (-d $home) {
		$dbdir = $home;
	} elsif ($shared_db && -d $shared_db) {
		$dbdir = $shared_db;
	} else {
		$dbdir = $home;
	}
	# I'm sorry I copied this from wdic.
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

sub escape {
	return encode_utf8(escapeHTML(@_));
}

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
		$_ = escape($_);
		$hl and s{($hl)}{<b>$1</b>}g;
		s{\(([^()]*)\)}{marker($1)}eg;
		$_
	} @_;
}

sub marker {
	my $m = shift;
	my $tooltip = $markers{$m};
	$tooltip and return "<span class=\"marker\" title=\"$tooltip\">$m</span>";
	"<span class=\"marker\">$m</span>"
}

sub cross_links {
	my $out = '';
	for (split(',', shift)) {
		my $word = /&#x30FB;/ ? substr($_, 0, $-[0]) : $_;
		$out and $out .= ", ";
		$out .= "<a href=\"?q=$word\">$_</a>"
	}
	$out
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
		$_ = escape($_);
		s@([^ ])/([^ ])@$1 / $2@g;
		s@([\({])([a-zA-Z\-]+)([\)}])@$1.marker($2).$3@eg;
		s@\(See ([^\)]+)\)@'(See '.cross_links($1).')'@eg;
		$_
	} @{$e->{meanings}});

	print "</div>\n"; # .entry
}

sub readings {
	my ($k, $field, $title, $tip) = @_;
	$k->{$field} && @{$k->{$field}} or return;
	print
	"<tr class=\"$field\">\n"
	. "<td><b title=\"$tip\">$title</b></td>\n"
	. '<td>' . join(', ', map {
		$_ = escape($_);
		$field eq 'kun' and s{\.(.*)$}{<span class="okurigana">&middot;$1</span>};
		$_
	} @{$k->{$field}}) . "</td>\n"
	. "</tr>\n";
}

sub kanji_entry {
	my $k = shift;
	print "<div class=\"kanji\">\n";
	print '<div class="heading">' . escape($k->{kanji}) . "</div>\n";

	print "<table class=\"readings\">\n";
	readings($k, on => '&#x97F3;', 'On-yomi readings');
	readings($k, kun => '&#x8A13;', 'Kun-yomi readings');
	readings($k, nanori => '&#x540D;', 'Nanori readings');
	readings($k, english => '&#x82F1;', 'English meanings'); # well, not really readings but eh
	print "</table>\n";

	print "</div>\n"; # .kanji
}

sub skip_block {
	my ($q, $kanjis) = @_;
	my $escaped_q = escape($q);
	print "<h2>SKIP $escaped_q</h2>\n";
	print "<div class=\"skip\">\n";
	for (@$kanjis) {
		my $k = escape($_);
		print "<a href=\"?q=$k\">$k</a>\n";
	}
	print "</div>\n";
}

sub results_block {
	my ($q, $kanjis, $words, $homophones) = @_;
	my $escaped_q = escape($q);
	my $hl = escape(quotemeta($q));

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
# JSON API

sub exact_search_api {
	print header(-type => 'application/json', -charset => 'utf-8');
	my $q = decode_utf8(param('q'));
	my @words = $q ? $edict->lookup($q) : ();
	print encode_json(\@words);
}

################################################################################

sub page_header {
	my $title = shift;
	$title = $title ? escape($title) . ' - Wish' : 'Wish';
	print <<"EOF";
<!doctype html>
<html>
	<head>
		<title>$title</title>
		<meta charset="utf-8" />
		<meta name="keywords" content="japanese,english,dictionary,edict,kanjidic,kanji,wish" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<link rel="stylesheet" type="text/css" href="/static/wish.css" />
		<script src="/static/wanakana.min.js"></script>
	</head>
	<body onload="wanakana.bind(document.getElementById('ime'));">
		<form id="header" action="/search">
			<input id="ime" type="text" name="q" placeholder="Search&#8230;" />
			<input type="submit" value="&#x691C;&#x7D22;" />
		</form>
		<div id="main">
EOF
}

sub page_footer {
	print <<EOF;
		</div>
		<div id="footer">
			<div><a href="https://github.com/fmang/wish/" title="This project">Wish</a></div>
			<ul>
				<li><a href="https://www.edrdg.org/wiki/index.php/KANJIDIC_Project" title="Free kanji dictionary">KANJIDIC</a></li>
				<li><a href="https://www.edrdg.org/jmdict/edict.html" title="Free word dictionary">EDICT2</a></li>
				<li><a href="https://wanakana.com/" title="Amazing JavaScript IME">WanaKana</a></li>
				<li><a href="https://www.colourlovers.com/palette/2085059/Partnership" title="Partnership by cantc">Colors</a></li>
				<li><a href="https://www.perl.org/" title="Cool programming language">Perl</a></li>
			</ul>
		</div>
	</body>
</html>
EOF
}

sub search_page {
	print header(-type => 'text/html', -charset => 'utf-8');
	my $q_arg = param('q');
	my @qs = $q_arg ? split(/[ ,;]+/, decode_utf8($q_arg)) : ();
	page_header(join(', ', @qs));
	for my $q (@qs) {
		if ($q =~ /^[1-4](-|ー)[0-9]*(-|ー)[0-9]*$/) {
			$q =~ s/ー/-/g;
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
	page_footer();
}

sub home_page {
	print header(-type => 'text/html', -charset => 'utf-8');
	page_header();
	print <<EOF;
<h2>Welcome!</h2>
<p>Welcome to <i>Wish</i>, a homemade Japanese-to-English dictionary browsing tool.<p>
<p>Here's what you should know about it:
<ul>
	<li><b>Wish focuses on kanji search.</b> The order in which you write
	them or the kanas you place in-between won't affect the search results
	much, though it orders them by relevancy. Sadly, you'll probably need
	an <a href="https://en.wikipedia.org/wiki/Input_method_editor">IME</a>
	to use that feature.</li>
	<li><b>It shows homophones,</b> so that you may see dangerous
	ambiguities.</li>
	<li><b>Dumb kana searches.</b> It will show you the words that sound
	like what you searched for.</li>
	<li><b>No grammar features though.</b> You'd better either use kanji
	search, or use non-inflected forms.</li>
	<li><b>SKIP patterns are supported.</b> If you don't know what these
	are, check <a href="http://nihongo.monash.edu/SKIP.html">this</a>.</li>
</ul>
</p>
<p>Wish is free software. You may run clones or use the command-line interface
without requiring an Internet connection. The source code is available on
<a href="https://github.com/fmang/wish/">GitHub</a>.</p>
<h2>Credits</h2>
<p>
Wish is built on top of various other projects, all of which deserving credit.
<ul>
	<li><a href="https://www.edrdg.org/wiki/index.php/KANJIDIC_Project" title="Free kanji dictionary">KANJIDIC</a> and <a href="https://www.edrdg.org/jmdict/edict.html" title="Free word dictionary">EDICT2</a>, which are free, pretty exhaustive, and high-quality dictionaries.</li>
	<li><a href="https://wanakana.com/" title="Amazing JavaScript IME">WanaKana</a> is the lightweight JavaScript IME we use for the search bar.</li>
	<li><a href="https://www.perl.org/" title="Cool programming language">Perl5</a> is a mature programming language, and is delightful.</li>
	<li><a href="https://www.colourlovers.com/palette/2085059/Partnership" title="Partnership by cantc">Partnership</a> is the color scheme on which Wish's web interface is based.</li>
</ul>
</p>
<p>— Wish team</p>
EOF
	page_footer();
}

while (new CGI::Fast) {
	my $url = url(-absolute => 1);
	if ($url eq '/') {
		home_page();
	} elsif ($url eq '/search') {
		search_page();
	} elsif ($url eq '/api/exact') {
		exact_search_api();
	} else {
		print header('text/plain', '404 Not Found');
		print "404 Not Found\n";
	}
}
