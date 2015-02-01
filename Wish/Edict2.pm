package Wish::Edict2;

use strict;
use warnings;

use DB_File;
use DBM_Filter;
use File::Spec::Functions;

sub new {
	my $class = shift;
	my $dir = shift;
	my $self = bless {@_}, $class;
	my %hash;

	$self->{dir} = $dir;
	defined $self->{readonly} or $self->{readonly} = 1;

	$DB_BTREE->{flags} = R_DUP;
	$self->opendb('word') or return;

	$self;
}

sub opendb {
	my ($self, $name) = @_;
	my $dbname = $name . '_db';
	my $mode = $self->{readonly} ? O_RDONLY : O_CREAT | O_RDWR;
	my $path = catfile($self->{dir}, "$name.db");
	my %hash;
	$self->{$dbname} = tie %hash, 'DB_File', $path, $mode, 0666, $DB_BTREE;
	$self->{$dbname} or return;
	$self->{$dbname}->Filter_Push('utf8');
	$self->{$name} = \%hash;
	$self->{$dbname};
}

sub load {
	my ($self, $filename) = @_;
	return if $self->{readonly};
	open(my $dic, $filename) or return;
	binmode($dic, ':encoding(euc-jp)');
	<$dic>; # skip the first line
	while (<$dic>) {
		/^([^; (]+)/ or next;
		$self->{word}->{$1} = $_;
	}
	close $dic;
	$self->{word_db}->sync();
	1;
}

sub lookup {
	my ($self, $key) = @_;
	$self->{word_db}->get_dup($key);
}

sub search {
	[];
}

sub close {
	my $self = shift;
	%$self = ();
}

sub parse_word {
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
