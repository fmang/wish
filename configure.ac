AC_INIT([wish], [1.1], [fmang+perl@mg0.fr])
AC_PREREQ([2.61])
AM_INIT_AUTOMAKE([foreign])

AC_PROG_INSTALL

AC_CHECK_PROG([PERL], [perl], [perl])
if test "x$PERL" = x; then
  AC_MSG_ERROR([Perl not found; check your \$PATH.])
fi

AC_CONFIG_FILES([Makefile])
AC_OUTPUT
