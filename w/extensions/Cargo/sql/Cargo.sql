CREATE TABLE /*_*/cargo_tables (
	template_id int NOT NULL,
	main_table varchar(200) NOT NULL,
	field_tables text NOT NULL,
	field_helper_tables text NOT NULL,
	table_schema text NOT NULL
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX cargo_tables_main_table ON /*_*/cargo_tables (main_table);

CREATE TABLE /*_*/cargo_pages (
	page_id int NOT NULL,
	table_name varchar(200) NOT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX cargo_pages_page_id ON /*_*/cargo_pages (page_id);
