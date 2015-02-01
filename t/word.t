use utf8;
use Test::More tests => 8;

use Wish::Edict2;

my $sample = '噯;噯気;噫気;噯木(iK) [おくび(噯,噯気);あいき(噯気,噫気,噯木)] /(n) (uk) belch/eructation/burp/EntL2007450X/';
my $sample2 = 'サイレント /(adj-na) (1) silent/(n) (2) (abbr) (See サイレント映画) silent movie/silent film/(3) silent letter/(P)/EntL1056180X/';

my %bad = Wish::Edict2::parse_word('sorry');
my %okubi = Wish::Edict2::parse_word($sample);
my %silent = Wish::Edict2::parse_word($sample2);

is(%bad, 0, 'Bad input');
is($okubi{entl}, '2007450', 'EntL code');
is_deeply($okubi{words}, [qw/噯 噯気 噫気 噯木(iK)/], 'Words');
is_deeply($okubi{readings}, [qw/おくび(噯,噯気) あいき(噯気,噫気,噯木)/], 'Readings');
is_deeply([sort(@{$silent{pos}})], [qw/adj-na n/], 'Part-of-speech');
is_deeply($okubi{meanings}, ['(uk) belch/eructation/burp'], 'Single meaning');
is_deeply($silent{meanings}, ['silent', '(abbr) (See サイレント映画) silent movie/silent film', 'silent letter'], 'Multiple meanings');
ok(!$okubi{common} && $silent{common}, 'Commonness marker');
