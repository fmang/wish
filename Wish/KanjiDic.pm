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
		my %k = parse_kanji($_);
		$self->{$k{kanji}} = \%k if %k;
	}
	close $dic;
}

sub lookup {
	my ($self, $query) = @_;
	wantarray ? %{$self->{$query}} : $self->{$query};
}

our %field_map = (
	P => 'skip',
);

sub parse_kanji {
	($_) = @_;
	my ($k, $jis, @fields) = split;
	return if !@fields;
	my %kanji = (kanji => $k);
	for (@fields) {
		if (/^{(.*)}$/) {
			push @{$kanji{english}}, $1;
		} elsif (/^(.)(.*)$/) {
			$kanji{$field_map{$1}} = $2 if exists $field_map{$1};
		}
	}
	wantarray ? %kanji : \%kanji;
}

1;
