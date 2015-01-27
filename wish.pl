use Encode qw(decode);

binmode(STDOUT, ":utf8");

lookup(shift);

sub lookup
{
	my ($query) = @_;
	open(my $dic, "data/kanjidic");
	binmode($dic, ":encoding(euc-jp)");
	<$dic>; # skip the first line
	while (<$dic>) {
		my @k = split;
		print "$_" if $k[0] eq $query;
	}
	close $dic;
}
