bin_SCRIPTS = wdic
pkglibexec_SCRIPTS = wish.fcgi
dist_man_MANS = wdic.1 wish.fcgi.8

pmdir = $(pkgdatadir)/perl
pmwishdir = $(pmdir)/Wish
dist_pmwish_DATA = \
	lib/Wish/Edict2.pm \
	lib/Wish/KanjiDic.pm \
	lib/Wish/Unicode.pm

staticdir = $(pkgdatadir)/static
dist_static_DATA = \
	static/_flexbox.scss \
	static/wish.css \
	static/wish.scss

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

%.css: %.scss
	sass $< $@

CPAN_FILES = MANIFEST Build.PL
EXTRA_DIST = autogen.sh bin/wdic bin/wish.fcgi t $(CPAN_FILES)
CLEANFILES = wdic wdic.1 wish.fcgi wish.fcgi.8 static/wish.css
