use utf8;
use Test::More tests => 2;

use URI::Escape;

my $fcgi = 'bin/wish.fcgi';

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
	`$fcgi`
}

like(get('/fhiufh'), qr/404/, '404 Not Found');
like(get('/search', q => 'rarity'), qr/rarity/, 'Search');
