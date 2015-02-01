use utf8;
use Test::More tests => 18;

use File::Spec::Functions;
use File::Temp qw(tempdir);
use Wish::Edict2;

my $data = <<EOF;

入る [いる] /(v5r,vi) (See 気に入る) to get in/to go in/to come in/to flow into/to set/to set in/(P)/EntL1465580X/
入る(P);這入る [はいる] /(v5r,vi) (1) (ant: 出る・1) to enter/to go into/(2) to break into/(3) to join/to enroll/(4) to contain/to hold/to accommodate/(5) to have (an income of)/(6) to get/to receive/to score/(P)/EntL1465590X/
居る [いる] /(v1,vi) (1) (uk) (See 在る・1) to be (of animate objects)/to exist/(2) to stay/(v1,aux-v) (3) (the い is sometimes dropped) (after the -te form of a verb) verb indicating continuing action or state (i.e. to be ..ing, to have been ..ing)/(P)/EntL1577980X/
込み居る [こみいる] /(v1,vi) (arch) (See 込み入る・2) to push in/to be crowded/EntL2815150/
青い(P);蒼い(oK);碧い(oK) [あおい] /(adj-i) (1) blue/green/(2) (青い, 蒼い only) pale/(3) (青い, 蒼い only) unripe/inexperienced/(P)/EntL1381390X/
緑(P);翠 [みどり] /(n) (1) green/(2) greenery (esp. fresh verdure)/(P)/EntL1555300X/
メッセージ通信処理環境 [メッセージつうしんしょりかんきょう] /(n) {comp} message handling environment/EntL2333870X/
ああいう(P);ああゆう /(exp,adj-pn) that sort of/like that/(P)/EntL2085090X/
噯;噯気;噫気;噯木(iK) [おくび(噯,噯気);あいき(噯気,噫気,噯木)] /(n) (uk) belch/eructation/burp/EntL2007450X/
EOF

my $dir = tempdir('wishXXXX', CLEANUP => 1);

open(my $src, '> ' . catfile($dir, 'source'));
binmode($src, ':encoding(euc-jp)');
$src->print($data);
$src->close();

my $dic = Wish::Edict2->new($dir);
is($dic, undef, 'Nonexistent database');

$dic = Wish::Edict2->new($dir, readonly => 0);
is($dic->load(catfile($dir, 'derp')), undef, 'Nonexistent source');
ok($dic->load(catfile($dir, 'source')), 'Loading');

$dic = Wish::Edict2->new($dir);
is($dic->load(catfile($dir, 'source')), undef, 'Read-only mode');
is($dic->lookup('何とか'), 0, 'Negative lookup');
is($dic->lookup('入る'), 2, 'Homograph');
is($dic->lookup('蒼い'), 1, 'Alternative kanji lookup');

is(Wish::Edict2::to_katakana('よう'), 'ヨウ', 'Katakana conversion');
is_deeply([sort(Wish::Edict2::kanjis('引き離す', '赤い'))], [sort('引', '離', '赤')], 'Kanji filter');

my @r = $dic->search('蒼い');
is(@r, 1, 'Alternative kanji search');
ok($r[0]->{common}, 'Good search results');

# Reading lookup
is($dic->search('みどり'), 1, 'Exact reading');
is($dic->search('あいき'), 1, 'Complicated reading');
is($dic->search('ミドリ'), 1, 'Katakana reading');
is($dic->search('い'), 0, 'Inexact kana search');

# Kana expressions
is($dic->search('アア'), 1, 'Kana prefix');
is($dic->search('いう'), 0, 'No kana suffix');

# Kanji search
is($dic->search('処理通信'), 1, 'Disordered kanji search');
