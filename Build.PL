use Module::Build;

my $builder = Module::Build->new(
	module_name => 'Wish',
	dist_abstract => 'Japanese dictionary.',
	license => 'perl',
	dist_author => 'Frédéric Mangano <fmang+perl@mg0.fr>',
	dist_version_from => 'bin/wdic',
);

$builder->create_build_script();
