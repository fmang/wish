use Wish::KanjiDic;

binmode(STDOUT, ":utf8");

my $w = Wish::KanjiDic->new();
$w->load('data/kanjidic');
