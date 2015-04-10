use utf8;
use Test::More tests => 9;

use File::Spec::Functions;
use File::Temp qw(tempdir);

my $wdic = 'bin/wdic';

system($wdic, '--bad-option');
isnt($?, 0, 'Bad option');

system($wdic, '--help');
is($?, 0, 'Help');

my $dir = tempdir('wishXXXX', CLEANUP => 1);
my $db = catdir($dir, 'db');

system($wdic, '-d', $db, '--load', '--edict', catfile('t', 'edict2.sample'),
	'--kanjidic', catfile('t', 'kanjidic.sample'));
is($?, 0, 'Loading');
ok(-d $db, 'Database directory creation');

$ENV{PAGER} = 'touch ' . catdir($dir, 'pager');
my $wdic = "$wdic -d $db --nocolor --nopager";
unlike(`$wdic 青い`, qr/\033/, 'No colors');
ok(! -e catdir($dir, 'pager'), 'No pager');

like(`$wdic 青い`, qr/blue/, 'Word lookup');
like(`$wdic --kanji 愛`, qr/love/, 'Kanji lookup');
like(`$wdic --homophones 居る`, qr/to get in/, 'Homophone lookup');
