use utf8;
use Test::More tests => 6;

use Wish::KanjiDic;

my $sample = '逢 3029 U9022 B162 G9 S10 S9 F2116 N4694 V6054 DP4002 DL2774 L2417 DN2497 O1516 MN38901X MP11.0075 P3-3-7 I2q7.15 Q3730.4 DR2555 ZRP3-4-7 Yfeng2 Wbong ホウ あ.う むか.える T1 あい おう {meeting} {tryst} {date} {rendezvous} ';

my $bad = Wish::KanjiDic::parse_kanji('sorry');
my $kanji = Wish::KanjiDic::parse_kanji($sample);

is($bad, undef, 'Bad input');
is($kanji->{skip}, '3-3-7', 'SKIP code');
is_deeply($kanji->{on}, ['ホウ'], 'On readings');
is_deeply($kanji->{kun}, [qw/あ.う むか.える/], 'Kun readings');
is_deeply($kanji->{nanori}, [qw/あい おう/], 'Name readings');
is_deeply($kanji->{english}, [qw(meeting tryst date rendezvous)], 'English meanings');
