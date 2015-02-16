package Wish::Unicode;

use strict;
use warnings;
use utf8;

use Exporter qw(import);
our @EXPORT_OK = qw(kanjis to_katakana kanji_count);

sub kanjis {
	my %k;
	for (@_) {
		$k{$_} = undef for /\p{Han}/g;
	}
	keys %k;
}

sub to_katakana {
	shift =~ tr/あ-ゖ/ア-ヶ/r;
}

sub kanji_count {
	scalar (() = shift =~ /\p{Han}/g)
}
