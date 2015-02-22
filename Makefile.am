bin_SCRIPTS = wdic
dist_man_MANS = wdic.1

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

CPAN_FILES = MANIFEST Build.PL
EXTRA_DIST = bin/wdic t $(CPAN_FILES)
CLEANFILES = wdic wdic.1
