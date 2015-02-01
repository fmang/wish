use utf8;
use Test::More tests => 7;

use Wish::Edict2;

my $sample = '噯;噯気;噫気;噯木(iK) [おくび(噯,噯気);あいき(噯気,噫気,噯木)] /(n) (uk) belch/eructation/burp/EntL2007450X/';
my $sample2 = 'っぽい(P);ぽい /(suf,adj-i) (1) (col) -ish/-like/(n) (2) (ぽい only) (on-mim) (See ぽいと) tossing something out/throwing something away/(P)/EntL2083720X/';

my %bad = Wish::Edict2::parse_word('sorry');
my %okubi = Wish::Edict2::parse_word($sample);
my %ppoi = Wish::Edict2::parse_word($sample2);

is(%bad, 0, 'Bad input');
is($okubi{entl}, '2007450', 'EntL code');
is_deeply($okubi{words}, [qw/噯 噯気 噫気 噯木(iK)/], 'Words');
is_deeply($okubi{readings}, [qw/おくび(噯,噯気) あいき(噯気,噫気,噯木)/], 'Readings');
is_deeply($ppoi{pos}, [qw/suf adj-i n/], 'Part-of-speech');
is_deeply($ppoi{meanings}, ['(col) -ish/-like', '(ぽい only) (on-mim) (See ぽいと) tossing something out/throwing something away'], 'Meanings');
ok($ppoi{common}, 'Commonness marker');
