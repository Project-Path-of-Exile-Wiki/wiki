-- Microsoft SQL Server schema for Cargo extension

CREATE TABLE /*_*/cargo_tables (
	template_id int NOT NULL,
	main_table varchar(200) NOT NULL,
	field_tables varchar(max) NOT NULL,
	field_helper_tables varchar(max) NOT NULL,
	table_schema varchar(max) NOT NULL
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX cargo_tables_main_table ON /*_*/cargo_tables (main_table);

CREATE TABLE /*_*/cargo_pages (
	page_id int NOT NULL,
	table_name varchar(200) NOT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX cargo_pages_page_id ON /*_*/cargo_pages (page_id);
