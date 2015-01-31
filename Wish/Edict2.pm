package Wish::Edict2;

use strict;
use warnings;

use DB_File;
use DBM_Filter;

sub new {
	my $class = shift;
	my $basename = shift;
	my $self = bless {@_}, $class;
	my %hash;

	defined $self->{readonly} or $self->{readonly} = 1;
	my $mode = $self->{readonly} ? O_RDONLY : O_CREAT | O_RDWR;

	$self->{db} = tie %hash, 'DB_File', $basename, $mode, 0666, $DB_BTREE;
	$self->{db} or return;
	$self->{db}->Filter_Push('utf8');
	$self->{hash} = \%hash;
	$self;
}

sub load {
	my ($self, $filename) = @_;
	return if $self->{readonly};
	open(my $dic, $filename) or return;
	binmode($dic, ':encoding(euc-jp)');
	<$dic>; # skip the first line
	while (<$dic>) {
		/^([^; (]+)/ or next;
		$self->{hash}->{$1} = $_;
	}
	close $dic;
	$self->{db}->sync();
	1;
}

sub lookup {
	my ($self, $key) = @_;
	$self->{hash}->{$key};
}

sub close {
	my $self = shift;
	delete $self->{hash};
	delete $self->{db};
}

sub DESTROY {
	my $self = shift;
	$self->close();
}


1;
