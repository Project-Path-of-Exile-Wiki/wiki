# Project Path of Exile Wiki

A community effort to move the contents of the Wiki and update it to the current patch.


## Make sure you read before-import.md before importing the dump. 

Follow the import instructions here: https://www.mediawiki.org/wiki/Manual:Importing_XML_dumps

Once you have imported the dump and have ~76,541 pages populated on the wiki, go to https://pathofexile.fandom.com/wiki/Special:CargoTables and click on the links that are to the right of the table name.

For example: 
amulets (View | Drilldown) - 133 rows (Declared by Template:Item/cargo/amulets, attached by Template:Item/cargo/attach/amulets)

Copy "Template:Item/cargo/amulets" and add it the the URL on your installation. This takes you to a template page which should have a message saying "This template declares the cargo table 'amulets'. The tables does not exist yet."

Edit the page without making any changes, just save. A new button should appear on the top right labeled "Create data tables". Click it and create the table.

Do the same steps for the attached Template (note: some have and some dont have attach templates, some have the attach statement in the same template as the declare, etc.)
