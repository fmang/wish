bin_SCRIPTS = wdic
pkglibexec_SCRIPTS = wish.fcgi
dist_man_MANS = wdic.1 wish.fcgi.8

pmdir = $(pkgdatadir)
pmwishdir = $(pmdir)/Wish
dist_pmwish_DATA = \
	lib/Wish/Edict2.pm \
	lib/Wish/KanjiDic.pm \
	lib/Wish/Unicode.pm

edit = sed -e "s/VERSION = '[^']*'/VERSION = '$(VERSION)'/" \
           -e "2 a use lib '$(pmdir)';\n"

wdic: bin/wdic Makefile
	$(edit) < $< > $@

wdic.1: bin/wdic
	pod2man $< > $@

wish.fcgi: bin/wish.fcgi Makefile
	$(edit) < $< > $@

wish.fcgi.8: bin/wish.fcgi
	pod2man --section 8 $< > $@

CPAN_FILES = MANIFEST Build.PL
EXTRA_DIST = bin/wdic bin/wish.fcgi t $(CPAN_FILES)
CLEANFILES = wdic wdic.1 wish.fcgi wish.fcgi.8
