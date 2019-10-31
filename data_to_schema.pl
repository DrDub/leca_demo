#!/usr/bin/perl -w

use strict;
use Text::CSV;
use Data::Dumper;

my $csv = Text::CSV->new ({ binary => 1, auto_diag => 1 });

for my $doc(qw(curriculumdocuments.csv  humanjudgments.csv  standardnodes.csv  userprofiles.csv)) {
    open my $fh, "<:encoding(utf8)", "$doc" or die "$doc: $!";
    my @row = @{$csv->getline ($fh)};
    close $fh;

    my$name = $doc;
    $name=~s/\.csv//;
    print "CREATE TABLE `$name` (\n";
    print "  `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,\n";
    for my$col(1..$#row){
        print "  `" . $row[$col]."` TEXT";
        if($col < $#row){
            print ",";
        }
        print "\n";
    }
    print ");\n\n";
}
