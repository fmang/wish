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
	$self->opendb('kanji') or return;

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
		$self->{kanji}->{$1} = $_;
	}
	close $dic;
	$self->{kanji_db}->sync();
	1;
}

sub lookup {
	my ($self, $key) = @_;
	$self->{kanji_db}->get_dup($key);
}

sub close {
	my $self = shift;
	%$self = ();
}

1;
