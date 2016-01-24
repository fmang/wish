bin_SCRIPTS = wdic
pkglibexec_SCRIPTS = wish.fcgi

if FOUND_POD2MAN
  MANPAGES = wdic.1 wish.fcgi.8
endif
man_MANS = $(MANPAGES)

pmdir = $(pkgdatadir)/perl
pmwishdir = $(pmdir)/Wish
dist_pmwish_DATA = \
	lib/Wish/Edict2.pm \
	lib/Wish/KanjiDic.pm \
	lib/Wish/Unicode.pm

if FOUND_SASS
  STYLESHEETS = static/wish.css
endif
staticdir = $(pkgdatadir)/static
static_DATA = $(STYLESHEETS)
dist_static_DATA = \
	static/_flexbox.scss \
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
	$(SASS) $< $@

CPAN_FILES = MANIFEST Build.PL
EXTRA_DIST = autogen.sh bin/wdic bin/wish.fcgi t $(CPAN_FILES)
CLEANFILES = wdic wdic.1 wish.fcgi wish.fcgi.8 static/wish.css
