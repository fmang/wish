use utf8;
use Test::More tests => 4;

use File::Spec::Functions;
use File::Temp qw(tempdir);
use Wish::Edict2;

my $data = <<EOF;
入る [いる] /(v5r,vi) (See 気に入る) to get in/to go in/to come in/to flow into/to set/to set in/(P)/EntL1465580X/
入る(P);這入る [はいる] /(v5r,vi) (1) (ant: 出る・1) to enter/to go into/(2) to break into/(3) to join/to enroll/(4) to contain/to hold/to accommodate/(5) to have (an income of)/(6) to get/to receive/to score/(P)/EntL1465590X/
居る [いる] /(v1,vi) (1) (uk) (See 在る・1) to be (of animate objects)/to exist/(2) to stay/(v1,aux-v) (3) (the い is sometimes dropped) (after the -te form of a verb) verb indicating continuing action or state (i.e. to be ..ing, to have been ..ing)/(P)/EntL1577980X/
青い(P);蒼い(oK);碧い(oK) [あおい] /(adj-i) (1) blue/green/(2) (青い, 蒼い only) pale/(3) (青い, 蒼い only) unripe/inexperienced/(P)/EntL1381390X/
緑(P);翠 [みどり] /(n) (1) green/(2) greenery (esp. fresh verdure)/(P)/EntL1555300X/
EOF

my $dir = tempdir('wishXXXX', CLEANUP => 1);

open(my $src, '> ' . catfile($dir, 'source'));
binmode($src, ':encoding(euc-jp)');
$src->print($data);
$src->close();

my $dic = Wish::Edict2->new(catfile($dir, 'db'), readonly => 0);
is($dic->load(catfile($dir, 'derp')), undef, 'Nonexistent source');
ok($dic->load(catfile($dir, 'source')), 'Loading');
$dic->close();

$dic = Wish::Edict2->new(catfile($dir, 'db'));
is($dic->load(catfile($dir, 'source')), undef, 'Read-only mode');
ok($dic->lookup('居る'), 'Word lookup');
