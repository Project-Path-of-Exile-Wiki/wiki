-- Postgres schema for Cargo extension

BEGIN;

CREATE TABLE cargo_tables (
  template_id              INTEGER  NOT NULL,
  main_table               TEXT     NOT NULL UNIQUE,
  field_tables             TEXT     NOT NULL,
  field_helper_tables      TEXT     NOT NULL,
  table_schema             TEXT     NOT NULL
);

CREATE UNIQUE INDEX cargo_tables_main_table ON cargo_tables (main_table);

CREATE SEQUENCE cargo_pages_page_id_seq;
CREATE TABLE cargo_pages (
  page_id                  INTEGER NOT NULL DEFAULT nextval('cargo_pages_page_id_seq'),
  table_name               TEXT    NOT NULL
);
CREATE INDEX cargo_pages_page_id ON cargo_pages (page_id);

COMMIT;
