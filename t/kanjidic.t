use utf8;
use Test::More tests => 9;

use File::Spec::Functions;
use File::Temp qw(tempdir);
use Wish::KanjiDic;

my $source = catdir('t', 'kanjidic.sample');
my $dir = tempdir('wishXXXX', CLEANUP => 1);

my $dic = Wish::KanjiDic->new($dir);
is($dic, undef, 'Nonexistent database');

$dic = Wish::KanjiDic->new($dir, readonly => 0);
is($dic->load(catfile($dir, 'derp')), undef, 'Nonexistent source');
ok($dic->load($source), 'Loading');

my $ai = $dic->lookup('愛');
is($ai && $ai->{skip}, '2-4-9', 'Lookup');
is($dic->lookup('相'), undef, 'Negative lookup');

$dic = Wish::KanjiDic->new($dir);
$ai = $dic->lookup('愛');
is($ai && $ai->{skip}, '2-4-9', 'Persistence');
is($dic->load($source), undef, 'Read-only mode');

is($dic->skip_lookup('2-4-9'), 1, 'SKIP lookup');
is($dic->skip_lookup('1-1-1'), 0, 'Negative SKIP lookup');
