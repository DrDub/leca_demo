#!/usr/bin/perl -w -CS

use strict;
use Text::CSV;
use Data::Dumper;

use utf8;

my $csv = Text::CSV->new ({ binary => 1, auto_diag => 1, decode_utf8=> 1 });
for my $doc(qw(curriculumdocuments.csv userprofiles.csv)) {
    my$name = $doc;
    $name=~s/\.csv//;
    print "-- $name\n\n";
    print "BEGIN TRANSACTION;\n";
    
    open my $fh, "<:encoding(UTF-8)", "$doc" or die "$doc: $!";

    $csv->getline ($fh); # header
    while (my $row = $csv->getline ($fh)) {
        my@row = @$row;
        print "INSERT INTO `$name` VALUES (";
        for my$i(0..$#row){
            my$v = $row[$i];
            chomp($v);
            my$alpha = 0;
            if($v =~ m/^(\-|\+)?[0-9]+\.?[0-9]*$/){
                $alpha = 1;
            }
            if($v eq ""){
                print "NULL";
            }elsif($alpha){
                print $v;
            }else{
                $v =~ s/\n/\\n/g;
                $v =~ s/\'/\'\'/g;
                print "'$v'";
            }
            if($i < $#row){
                print ", ";
            }
        }
        print ");\n";
    }
    close $fh;
    print "COMMIT;\n";
    print "\n\n";
}

# human jugdments, record which ids are there
my%assessed_ids = ();

my$doc="humanjudgments.csv";
my$name = $doc;
$name=~s/\.csv//;
print "-- $name\n\n";
print "BEGIN TRANSACTION;\n";

open my $fh, "<:encoding(UTF-8)", "$doc" or die "$doc: $!";

$csv->getline ($fh); # header
while (my $row = $csv->getline ($fh)) {
    my@row = @$row;
    print "INSERT INTO `$name` VALUES (";
    $assessed_ids{$row[1]} = 1;
    $assessed_ids{$row[2]} = 1;
    for my$i(0..$#row){
        my$v = $row[$i];
        chomp($v);
        my$alpha = 0;
        if($v =~ m/^(\-|\+)?[0-9]+\.?[0-9]*$/){
            $alpha = 1;
        }
        if($v eq ""){
            print "NULL";
        }elsif($alpha){
            print $v;
        }else{
            $v =~ s/\n/\\n/g;
            $v =~ s/\'/\'\'/g;
            print "'$v'";
        }
        if($i < $#row){
            print ", ";
        }
    }
    print ");\n";
}
close $fh;
print "COMMIT;\n";
print "\n\n";

# get the row ids from the nodes
my@row_to_id = ();
my@row_to_title = ();

$doc="standardnodes.csv";
$name = $doc;
$name=~s/\.csv//;
print "-- $name\n\n";
print "BEGIN TRANSACTION;\n";

open $fh, "<:encoding(UTF-8)", "$doc" or die "$doc: $!";

$csv->getline ($fh); # header
while (my $row = $csv->getline ($fh)) {
    my@row = @$row;
    push @row_to_id, $row[0];
    push @row_to_title, $row[8];
    
    print "INSERT INTO `$name` VALUES (";
    for my$i(0..$#row){
        my$v = $row[$i];
        chomp($v);
        my$alpha = 0;
        if($v =~ m/^(\-|\+)?[0-9]+\.?[0-9]*$/){
            $alpha = 1;
        }
        if($v eq ""){
            print "NULL";
        }elsif($alpha){
            print $v;
        }else{
            $v =~ s/\n/\\n/g;
            $v =~ s/\'/\'\'/g;
            print "'$v'";
        }
        print ", ";
    }
    if($assessed_ids{$row[0]}){
        print"1";
    }else{
        print"0";
    }
    print ");\n";
}
close $fh;
print "COMMIT;\n";
print "\n\n";


open $fh, "<:encoding(UTF-8)", "tsne-data.tsv" or die "tsne-data.tsv: $!";
my$rownum=0;
print "\n\n-- embeddings\nBEGIN TRANSACTION;\n";
while(<$fh>){
    chomp;
    my@fields = split(/\t/,$_);
    my$title = shift@fields;
    my$x = shift@fields;
    my$y = shift@fields;
    my$other = $row_to_title[$rownum];
    $title =~ s/[^A-z0-9 ]//g;
    $other =~ s/[^A-z0-9 ]//g;
    die "Mismatch at $rownum:\n\t$title\n\t$other" unless $title eq $other;
    my$id = $row_to_id[$rownum];

    print "INSERT INTO embeddings VALUES ( $id, " . join(",", @fields) . ");\n";
    print "INSERT INTO embeddings_index VALUES ( $id, $x, $x, $y, $y );\n";

    $rownum++;
}
print "COMMIT;\n";

    
