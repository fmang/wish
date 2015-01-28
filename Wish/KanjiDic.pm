package Wish::KanjiDic;

use strict;
use warnings;

sub new
{
	my $class = shift;
	bless {}, $class;
}

sub load
{
	my ($self, $file) = @_;
	open(my $dic, $file);
	binmode($dic, ":encoding(euc-jp)");
	<$dic>; # skip the first line
	while (<$dic>) {
		my @k = split;
		print $k[0];
	}
	print "\n";
	close $dic;
}

1;
