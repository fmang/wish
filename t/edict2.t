use utf8;
use Test::More tests => 17;

use File::Spec::Functions;
use File::Temp qw(tempdir);
use Wish::Edict2;

my $source = catdir('t', 'edict2.sample');
my $dir = tempdir('wishXXXX', CLEANUP => 1);

my $dic = Wish::Edict2->new($dir);
is($dic, undef, 'Nonexistent database');

$dic = Wish::Edict2->new($dir, readonly => 0);
is($dic->load(catfile($dir, 'derp')), undef, 'Nonexistent source');
ok($dic->load($source), 'Loading');

$dic = Wish::Edict2->new($dir);
is($dic->load($source), undef, 'Read-only mode');
is($dic->lookup('何とか'), 0, 'Negative lookup');
is($dic->lookup('入る'), 2, 'Homograph');
is($dic->lookup('蒼い'), 1, 'Alternative kanji lookup');

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

is($dic->search('処理通信'), 1, 'Disordered kanji search');

my @h = $dic->homophones('入る');
ok(@h == 1 && $h[0]->{words}->[0] eq '居る', 'Homophones');
