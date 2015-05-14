AC_INIT([wish], [1.2], [fmang+perl@mg0.fr])
AC_PREREQ([2.61])
AM_INIT_AUTOMAKE([foreign])

AC_PROG_INSTALL
AC_PROG_SED

AC_CHECK_PROG([PERL], [perl], [perl])
if test "x$PERL" = x; then
  AC_MSG_ERROR([Perl not found; check your \$PATH.])
fi

AC_CHECK_PROG([sass], [sass], [yes], [no])
AM_CONDITIONAL([FOUND_SASS], [test "x$sass" = xyes])
AM_COND_IF([FOUND_SASS],, [AC_MSG_WARN([sass not found; CSS stylesheets won't be generated.])])

AC_CHECK_PROG([pod2man], [pod2man], [yes], [no])
AM_CONDITIONAL([FOUND_POD2MAN], [test "x$pod2man" = xyes])
AM_COND_IF([FOUND_POD2MAN],, [AC_MSG_WARN([pod2man not found; man pages won't be generated.])])

AC_CONFIG_FILES([Makefile])
AC_OUTPUT
