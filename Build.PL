use Module::Build;

my $builder = Module::Build->new(
	module_name => 'Wish',
	dist_abstract => 'Japanese dictionary.',
	license => 'perl',
	dist_author => 'Frédéric Mangano <fmang+perl@mg0.fr>',
	dist_version => 1.0,
);

$builder->create_build_script();
