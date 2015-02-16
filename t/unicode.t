use utf8;
use Test::More tests => 3;

use Wish::Unicode qw(kanjis to_katakana kanji_count);

is(to_katakana('よう'), 'ヨウ', 'Katakana conversion');

is_deeply(
	[sort(kanjis('引き離す', '赤い'))],
	[sort('引', '離', '赤')],
	'Kanji filter'
);

is(kanji_count('引き離す'), 2, 'Kanji count');
