package Wish::Edict2;

use strict;
use warnings;

use DB_File;
use DBM_Filter;
use File::Spec::Functions;
use List::Util qw(any max reduce);

use Wish::Unicode qw(kanjis to_katakana kanji_count);

sub init {
	my $class = shift;
	my $dir = shift;
	my $self = bless {@_}, $class;

	$self->{dir} = $dir;
	defined $self->{readonly} or $self->{readonly} = 1;
	$self;
}

sub new {
	my $self = init(@_);
	$self->opendb('entl') or return;
	$self->opendb('words') or return;
	$self->opendb('readings') or return;
	$self->opendb('kindex') or return;
	$self;
}

sub opendb {
	my ($self, $name) = @_;
	my $dbname = $name . '_db';
	my $mode = $self->{readonly} ? O_RDONLY : O_CREAT | O_RDWR;
	my $path = catfile($self->{dir}, "$name.db");
	my %hash;
	$DB_BTREE->{flags} = R_DUP;
	$self->{$dbname} = tie %hash, 'DB_File', $path, $mode, 0666, $DB_BTREE;
	$self->{$dbname} or return;
	$self->{$dbname}->Filter_Push('utf8');
	$self->{$name} = \%hash;
	$self->{$dbname};
}

sub clean {
	shift =~ s/\([^()]*\)//gr;
}

sub load {
	my ($self, $filename) = @_;
	return if $self->{readonly};
	open(my $dic, $filename) or return;
	binmode($dic, ':encoding(euc-jp)');
	<$dic>; # skip the first line
	while (my $line = <$dic>) {
		my $e = parse_entry($line) or next;
		$self->{entl}->{$e->{entl}} = $line;
		$self->{words}->{to_katakana(clean($_))} = $e->{entl} for @{$e->{words}};
		$self->{readings}->{to_katakana(clean($_))} = $e->{entl} for @{$e->{readings}};
		$self->{kindex}->{$_} = $e->{entl} for kanjis(@{$e->{words}});
	}
	close $dic;
	$self->sync();
	1;
}

sub sync {
	my $self = shift;
	$self->{entl_db}->sync();
	$self->{words_db}->sync();
	$self->{readings_db}->sync();
	$self->{kindex_db}->sync();
}

sub entl_lookup {
	my $self = shift;
	map { parse_entry($self->{entl}->{$_}) } @_;
}

sub lookup {
	my ($self, $key) = @_;
	$self->entl_lookup($self->{words_db}->get_dup(to_katakana($key)));
}

sub reading_lookup {
	my ($self, $key) = @_;
	$self->entl_lookup($self->{readings_db}->get_dup(to_katakana($key)));
}

sub prefix_lookup {
	my ($self, $key) = @_;
	$key = to_katakana($key);
	my $prefix = quotemeta($key);
	$prefix = qr/^$prefix/;
	my ($value, $entry, %results, $st);

	for ($st = $self->{words_db}->seq($key, $value, R_CURSOR);
	     $st == 0;
	     $st = $self->{words_db}->seq($key, $value, R_NEXT)) {

		next if exists $results{$value};
		$entry = parse_entry($self->{entl}->{$value});
		if (any { to_katakana($_) =~ /$prefix/ } @{$entry->{words}}) {
			$results{$value} = $entry;
		} else {
			last;
		}

	}
	values %results;
}

sub kanji_lookup {
	my ($self, $key) = @_;
	my (%counts, $n);
	for my $k (kanjis($key)) {
		$counts{$_}++ for $self->{kindex_db}->get_dup($k);
		$n++;
	}
	my @r = grep { $counts{$_} == $n } keys %counts;
	$self->entl_lookup(@r);
}

sub max_kanji_count {
	max map { kanji_count($_) } @{shift->{words}}
}

sub main {
	my $e = shift;
	my $w = $e->{words} ? $e->{words}->[0] : $e->{readings}->[0];
	$w =~ s/\(.*\)//g;
	$w
}

sub cmp_positive {
	# negative numbers are like infinity
	my ($a, $b) = @_;
	$a < 0 ? ($b < 0 ? 0 : 1)
	       : ($b < 0 ? -1 : $a <=> $b)
}

sub highlight_pos {
	my ($q, $e) = @_;
	$e->{words} or return -1;
	my @indices = map { index($_, $q) } @{$e->{words}};
	reduce { cmp_positive($a, $b) < 0 ? $a : $b } @indices
}

sub compare_entries {
	my ($q, $a, $b) = @_;
	cmp_positive(highlight_pos($q, $a), highlight_pos($q, $b))
	|| max_kanji_count($a) <=> max_kanji_count($b)
	|| main($a) cmp main($b)
}

sub search {
	my ($self, $q) = @_;
	my @results;
	my $kl = kanji_count($q);
	if ($q =~ /^[\p{Hira}\p{Kana}]+$/) {
		@results = $self->reading_lookup($q);
		push(@results, sort { main($a) cmp main($b) } $self->prefix_lookup($q));
		# the two result sets shouldn't intersect
	} elsif ($kl != 0) {
		@results = sort { compare_entries($q, $a, $b) }
			($kl == 1 ? $self->prefix_lookup($q =~ s/(\p{Han}).*$/$1/r)
			          : $self->kanji_lookup($q));
	}
	# English maybe?
	@results;
}

sub homophones {
	my ($self, $q) = @_;
	my @ws = $self->lookup($q);
	my (%rs, %entl, %res);
	for my $e (@ws) {
		$rs{to_katakana($_)} = undef for @{$e->{readings}};
	}
	for my $r (keys %rs) {
		$entl{$_} = undef for $self->{readings_db}->get_dup($r);
		$entl{$_} = undef for $self->{words_db}->get_dup($r);
	}
	delete $entl{$_->{entl}} for @ws;
	$self->entl_lookup(keys %entl);
}

sub close {
	my $self = shift;
	%$self = ();
}

sub parse_entry {
	shift =~ m|^([^ ]+)( \[([^\]]+)\])? /(.*?)/(\(P\)/)?EntL([0-9]+)X?/$| or return;
	my %w = (
		words => [split(';', $1)],
		readings => $3 ? [split(';', $3)] : undef,
		common => defined $5,
		entl => $6,
	);
	my $meaning;
	for (split('/', $4)) {
		# First marker is always a part-of-speech marker
		if (!defined $w{pos}) {
			s/^\(([^()]+)\) // or return;
			$w{pos}->{$_} = undef for split(',', $1);
		}
		# Looking for numbers now, with an optional POS marker
		if (s/^(\(([^()]+)\) )?\([0-9]+\) //) {
			push(@{$w{meanings}}, $meaning) if $meaning;
			$meaning = '';
			if ($2) { $w{pos}->{$_} = undef for split(',', $2); }
		}
		$meaning = $meaning ? "$meaning/$_" : $_;
	}
	push(@{$w{meanings}}, $meaning) if $meaning;
	$w{pos} = [keys %{$w{pos}}];
	\%w;
}

1;
