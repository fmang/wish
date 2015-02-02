use utf8;
use Test::More tests => 9;

use File::Spec::Functions;
use File::Temp qw(tempdir);
use Wish::KanjiDic;

my $data = <<EOF;

愛 3026 U611b B87 C61 G4 S13 F640 J2 N2829 V1927 H2492 DP3133 DK1606 DL2191 L737 DN796 K436 O2018 DO456 MN10947 MP4.1123 E417 IN259 DS339 DF545 DH441 DT602 DC268 DJ1079 DG790 DM745 P2-4-9 I4i10.1 Q2024.7 DR2067 ZPP2-1-12 ZPP2-6-7 Yai4 Wae アイ いと.しい かな.しい め.でる お.しむ まな T1 あ あし え かな なる めぐ めぐみ よし ちか {love} {affection} {favourite} 
EOF

my $dir = tempdir('wishXXXX', CLEANUP => 1);

open(my $src, '> ' . catfile($dir, 'source'));
binmode($src, ':encoding(euc-jp)');
$src->print($data);
$src->close();

my $dic = Wish::KanjiDic->new($dir);
is($dic, undef, 'Nonexistent database');

$dic = Wish::KanjiDic->new($dir, readonly => 0);
is($dic->load(catfile($dir, 'derp')), undef, 'Nonexistent source');
ok($dic->load(catfile($dir, 'source')), 'Loading');

my $ai = $dic->lookup('愛');
is($ai && $ai->{skip}, '2-4-9', 'Lookup');
is($dic->lookup('相'), undef, 'Negative lookup');

$dic = Wish::KanjiDic->new($dir);
$ai = $dic->lookup('愛');
is($ai && $ai->{skip}, '2-4-9', 'Persistence');
is($dic->load(catfile($dir, 'source')), undef, 'Read-only mode');

is($dic->skip_lookup('2-4-9'), 1, 'SKIP lookup');
is($dic->skip_lookup('1-1-1'), 0, 'Negative SKIP lookup');
