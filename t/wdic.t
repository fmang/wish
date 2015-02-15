use utf8;
use Test::More tests => 9;

use File::Spec::Functions;
use File::Temp qw(tempdir);

system('./wdic', '--bad-option');
isnt($?, 0, 'Bad option');

system('./wdic', '--help');
is($?, 0, 'Help');

my $dir = tempdir('wishXXXX', CLEANUP => 1);
my $db = catdir($dir, 'db');

sub dic_data {
	open(my $src, '> ' . catfile($dir, shift));
	binmode($src, ':encoding(euc-jp)');
	$src->print(shift);
	$src->close();
}

dic_data 'kanjidic.demo' => <<EOF;

愛 3026 U611b B87 C61 G4 S13 F640 J2 N2829 V1927 H2492 DP3133 DK1606 DL2191 L737 DN796 K436 O2018 DO456 MN10947 MP4.1123 E417 IN259 DS339 DF545 DH441 DT602 DC268 DJ1079 DG790 DM745 P2-4-9 I4i10.1 Q2024.7 DR2067 ZPP2-1-12 ZPP2-6-7 Yai4 Wae アイ いと.しい かな.しい め.でる お.しむ まな T1 あ あし え かな なる めぐ めぐみ よし ちか {love} {affection} {favourite} 
EOF

dic_data 'edict2.demo' => <<EOF;

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

system('./wdic', '-d', $db, '--load', '--edict', catfile($dir, 'edict2.demo'),
	'--kanjidic', catfile($dir, 'kanjidic.demo'));
is($?, 0, 'Loading');
ok(-d $db, 'Database directory creation');

$ENV{PAGER} = 'touch ' . catdir($dir, 'pager');
my $wdic = "./wdic -d $db --nocolor --nopager";
unlike(`$wdic 青い`, qr/\033/, 'No colors');
ok(! -e catdir($dir, 'pager'), 'No pager');

like(`$wdic 青い`, qr/blue/, 'Word lookup');
like(`$wdic --kanji 愛`, qr/love/, 'Kanji lookup');
like(`$wdic --homophones 居る`, qr/to get in/, 'Homophone lookup');
