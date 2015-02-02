package Wish::Edict2;

use strict;
use warnings;
use utf8;

use DB_File;
use DBM_Filter;
use File::Spec::Functions;
use List::Util qw(any);

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
		my %e = parse_entry($line) or next;
		$self->{entl}->{$e{entl}} = $line;
		$self->{words}->{to_katakana(clean($_))} = $e{entl} for @{$e{words}};
		$self->{readings}->{to_katakana(clean($_))} = $e{entl} for @{$e{readings}};
		$self->{kindex}->{$_} = $e{entl} for kanjis(@{$e{words}});
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
	map { scalar parse_entry($self->{entl}->{$_}) } @_;
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

sub kanjis {
	my %k;
	for (@_) {
		$k{$_} = undef for /\p{Han}/g;
	}
	keys %k;
}

sub to_katakana {
	shift =~ tr/あ-ゖ/ア-ヶ/r;
}

sub search {
	my ($self, $q) = @_;
	if ($q =~ /^[\p{Hira}\p{Kana}]+$/) {
		my @results = $self->reading_lookup($q);
		push(@results, $self->prefix_lookup($q));
		# the two result sets shouldn't intersect
		return @results;
	} elsif ($q =~ /\p{Han}/) {
		return $self->kanji_lookup($q);
	}
	# English maybe?
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
		pos => undef,
		meanings => undef,
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
	wantarray ? %w : \%w;
}

1;
