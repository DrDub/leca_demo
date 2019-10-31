# Curriculum Alignment in Crisis Context Demo

See https://blog.learningequality.org/report-release-design-sprint-on-curriculum-alignment-in-crisis-contexts-57eb717b9e7e for context.

This demo makes use of the R-Tree SQLite3 and t-SNE projection to accelerate finding of nearby matches.

## DB loading

The schema is created by 

```
perl data_to_schema.pl  > db.schema
```

then editing the types by hand.

The DB can then be created:

```
cat db.schema | sqlite3 data.db
```

The DB is populated using the CSVs and the embeddings TSV obtained from https://colab.research.google.com/drive/1CwqE65mh-cgoLAqHqk0jlKY7fty8fYYy

```
perl -CS data_to_sql.pl | sqlite3 data.db
```

## The demo

You will need a PHP enabled web server. Just serve index.php
