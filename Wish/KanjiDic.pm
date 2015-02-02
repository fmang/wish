package Wish::KanjiDic;

use strict;
use warnings;

use Wish::Edict2;

sub new {
	my $self = Wish::Edict2::init(@_);
	Wish::Edict2::opendb($self, 'kanjidic') or return;
	Wish::Edict2::opendb($self, 'skip') or return;
	$self;
}

sub load {
	my ($self, $filename) = @_;
	return if $self->{readonly};
	open(my $dic, $filename) or return;
	binmode($dic, ':encoding(euc-jp)');
	<$dic>; # skip the first line
	while (<$dic>) {
		my %k = parse_kanji($_) or next;
		$self->{kanjidic}->{$k{kanji}} = $_;
		$k{skip} and $self->{skip}->{$k{skip}} = $k{kanji};
	}
	close $dic;
	$self->{kanjidic_db}->sync();
	$self->{skip_db}->sync();
	1;
}

sub lookup {
	my ($self, $q) = @_;
	my $k = $self->{kanjidic}->{$q} or return;
	parse_kanji($k);
}

sub skip_lookup {
	my ($self, $q) = @_;
	map { scalar $self->lookup($_) } $self->{skip_db}->get_dup($q);
}

our %field_map = (
	P => 'skip',
);

sub parse_kanji {
	($_) = @_;
	my ($k, $jis, @fields) = split;
	return if !@fields;
	my %kanji = (kanji => $k);
	my $nanori;
	for (@fields) {
		if (/^([A-Z])(.*)$/) {
			$kanji{$field_map{$1}} = $2 if exists $field_map{$1};
			$1 eq 'T' and $nanori = 1;
		} elsif (/\p{Kana}/) {
			push @{$kanji{on}}, $_;
		} elsif (/\p{Hira}/) {
			push @{$kanji{$nanori ? 'nanori' : 'kun'}}, $_;
		} elsif (/^{(.*)}$/) {
			push @{$kanji{english}}, $1;
		}
	}
	wantarray ? %kanji : \%kanji;
}

1;
