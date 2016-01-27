use utf8;
use Test::More tests => 5;

use Encode qw(decode_utf8);
use File::Spec::Functions;
use File::Temp qw(tempdir);
use URI::Escape;

my $dir = tempdir('wishXXXX', CLEANUP => 1);
system('bin/wdic', '-d', $dir, '--load', '--edict', catfile('t', 'edict2.sample'),
	'--kanjidic', catfile('t', 'kanjidic.sample'));

my $fcgi = "bin/wish.fcgi -d $dir";

sub get {
	my ($path, %args) = @_;
	my $query;
	for (keys %args) {
		$query and $query .= '&';
		$query .= "$_=" . uri_escape_utf8($args{$_});
	}
	local $ENV{REQUEST_METHOD} = 'GET';
	local $ENV{REQUEST_URI} = uri_escape_utf8($path) . "?$query";
	local $ENV{QUERY_STRING} = $query;
	decode_utf8(`$fcgi`)
}

like(get('/fhiufh'), qr/404/, '404 Not Found');

like(get('/search', q => '翠'), qr/green/, 'Word search');
like(get('/search', q => '愛'), qr/affection/, 'Kanji search');
like(get('/search', q => '2-4-9'), qr/(愛|&#x611B;)/, 'SKIP search');
like(get('/search', q => '2ー4ー9'), qr/(愛|&#x611B;)/, 'SKIP search with katakana dash');
