package Wish::KanjiDic;

use strict;
use warnings;

sub new {
	my $class = shift;
	bless {}, $class;
}

sub load {
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

our %field_map = (
	P => 'skip',
);

sub parse_kanji {
	($_) = @_;
	my %kanji;
	my ($k, $jis, @fields) = split;
	for (@fields) {
		if (/^{(.*)}$/) {
			push @{$kanji{english}}, $1;
		} elsif (/^(.)(.*)$/) {
			$kanji{$field_map{$1}} = $2 if exists $field_map{$1};
		}
	}
	%kanji;
}

1;
