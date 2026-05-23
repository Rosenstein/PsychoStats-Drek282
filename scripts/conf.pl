#!/usr/bin/perl

# FindBin isn't going to work on systems that run the stats.pl as SETUID
BEGIN { 
  use FindBin; 
  use lib $FindBin::Bin;
  use lib $FindBin::Bin . "/lib";
  use lib $FindBin::Bin . "/../lib";
#  use lib "./lib";
}

BEGIN { # make sure we're running the minimum version of perl required
	my $minver = 5.08;
	my $curver = 0.0;
	my ($major,$minor,$release) = split(/\./,sprintf("%vd", $^V));
	$curver = sprintf("%d.%02d",$major,$minor);
	if ($curver < $minver) {
		print "Perl v$major.$minor.$release is too old to run PsychoStats.\n";
		print "Minimum version $minver is required. You must upgrade before continuing.\n";
		if (lc substr($^O,0,-2) eq "mswin") {
			print "\nPress ^C or <enter> to exit.\n";
			<>;
		}
		exit 1;
	}
}

BEGIN { # do checks for required modules
	our %PM_LOADED = ();
	my @modules = qw( DBI DBD::mysql );
	my @failed_at_life = ();
	my %bad_kitty = ();
	foreach my $module (@modules) {
		my $V = '';
		eval "use $module; \$V = \$${module}::VERSION;";
		if ($@) {	# module not found
			push(@failed_at_life, $module);
		} else {	# module loaded ok; store for later, if -V is used for debugging purposes
			$PM_LOADED{$module} = $V;
		}
	}

	# check the version of modules
	# DBD::mysql needs to be 3.x at a minimum
	if ($PM_LOADED{'DBD::mysql'} and substr($PM_LOADED{'DBD::mysql'},0,1) lt '3') {
		$bad_kitty{'DBD::mysql'} = '3.0008';
	}

	# if anything failed, kill ourselves, life isn't worth living.
	if (@failed_at_life or scalar keys %bad_kitty) {
		print "PsychoStats failed initialization!\n";
		if (@failed_at_life) {
			print "The following modules are required and could not be loaded.\n";
			print "\t" . join("\n\t", @failed_at_life) . "\n";
			print "\n";
		} else {
			print "The following modules need to be upgraded to the version shown below\n";
			print "\t$_ v$bad_kitty{$_} or newer (currently installed: $PM_LOADED{$_})\n" for keys %bad_kitty;
			print "\n";
		}

		if (lc substr($^O,0,-2) eq "mswin") {	# WINDOWS
			print "You can install the modules listed by using the Perl Package Manager.\n";
			print "Typing 'ppm' at the Start->Run menu usually will open it up. Enter the module\n";
			print "name and have it install. Then rerun PsychoStats.\n";
			print "\nPress ^C or <enter> to exit.\n";
			<>;
		} else {				# LINUX
			print "You can install the modules listed using either CPAN or if your distro\n";
			print "supports it by installing a binary package with your package manager like\n";
			print "'yum' (fedora / redhat), 'apt-get' or 'aptitude' (debian).\n";
		}
		exit 1;
	}
}

use strict;
use warnings;
use Data::Dumper;
use File::Spec::Functions qw(catfile);
use PS::CmdLine::Conf;				# special 'conf' object for this script
use PS::DB;
use PS::Config;					# use'd here only for the loadfile() function
use PS::ConfigHandler;
use PS::ErrLog;
use PS::Debug;

our $VERSION = '1.0';

our $ERR;					# Global Error handler (PS::Debug uses this)
our $DBCONF = {};				# Global database config

my ($opt, $dbconf, $db, $conf, $file);

binmode STDOUT, ":utf8";

$opt = new PS::CmdLine::Conf;				# Initialize command line paramaters

#$opt->set('conftype', 'main') unless $opt->conftype;

# display our version and exit
if ($opt->version) {
	print "PsychoStats config helper version $VERSION\n";
	print "Author:  Jason Morriss\n";
	exit;
}

# Load the basic stats.cfg for database settings (unless 'noconfig' is specified on the command line)
# The config filename can be specified on the commandline, otherwise stats.cfg is used. If that file 
# does not exist then the config is loaded from the __DATA__ block of this file.
$dbconf = {};
if (!$opt->noconfig) {
	if ($opt->get('config')) {
		PS::Debug->debug("Loading DB config from " . $opt->get('config'));
		$dbconf = PS::Config->loadfile( $opt->get('config') );
	} elsif (-e catfile($FindBin::Bin, 'stats.cfg')) {
		PS::Debug->debug("Loading DB config from stats.cfg");
		$dbconf = PS::Config->loadfile( catfile($FindBin::Bin, 'stats.cfg') );
	} else {
		PS::Debug->debug("Loading DB config from __DATA__");
		$dbconf = PS::Config->loadfile( *DATA );
	}
} else {
	PS::Debug->debug("-noconfig specified, No DB config loaded.");
}

# Initialize the primary Database object
# Allow command line options to override settings loaded from config
$DBCONF = {
	dbtype		=> $opt->dbtype || $dbconf->{dbtype},
	dbhost		=> $opt->dbhost || $dbconf->{dbhost},
	dbport		=> $opt->dbport || $dbconf->{dbport},
	dbname		=> $opt->dbname || $dbconf->{dbname},
	dbuser		=> $opt->dbuser || $dbconf->{dbuser},
	dbpass		=> $opt->dbpass || $dbconf->{dbpass},
	dbtblprefix	=> $opt->dbtblprefix || $dbconf->{dbtblprefix}
};
$db = PS::DB->new($DBCONF);

if ($opt->dump) {
	$file = $opt->file || $opt->pop_opt;
	$file = $opt->dump . ".cfg" unless $file;
	$conf = init($db, $opt->dump);
	err("Error creating file: $!") unless open(F, ">$file");

	my $config = $conf->get;
	err("No '" . $opt->dump . "' config found.") unless defined $config and keys %$config;
	PS::Config->savefile( 
		filename => *F,
		config => $config,
		header => "# Config dumped on " . scalar(localtime) . "\n#\$TYPE = " . $opt->dump . "\n\n", 
	);
	close(F);

	if ($file ne '-') {
		print "Config '" . $opt->dump . "' written to file $file\n";
	}
} elsif ($opt->save) {

} elsif ($opt->update) {
	$conf = init($db, $opt->update);
	my $config = $conf->get;
	err("No '" . $opt->update . "' config found.") unless defined $config and keys %$config;

}

sub init {
	my ($db, $type) = @_;
	my $conf = new PS::Config($db);
	my $total = $conf->load($type);
	$ERR = new PS::ErrLog($conf, $db);			# Now all error messages will be logged to the DB
	return $conf;
}

sub err {
	my $msg = shift;
	warn $msg . "\n";
	exit();
}

# PS::ErrLog points to this to actually exit on a fatal error, incase I need to do some cleanup
sub main::exit { CORE::exit(@_) }

__DATA__

# If no stats.cfg exists then this config is loaded instead

dbtype = mysql
dbhost = localhost
dbport = 
dbname = psychostats
dbuser = 
dbpass = 
dbtblprefix = ps_
