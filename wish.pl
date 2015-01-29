#!/usr/bin/perl

use Dancer;
use Data::Dumper;
use Wish::KanjiDic;

binmode(STDOUT, ":utf8");

my $w = Wish::KanjiDic->new();
$w->load('data/kanjidic');

get '/kanji/:query' => sub {
	my %kanji = $w->lookup(param('query'));
	return Dumper(\%kanji) if %kanji;
	return 'Unknown kanji';
};

dance;
