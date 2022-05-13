# Project Path of Exile Wiki

A community effort to move the contents of the Wiki and update it to the current patch.

### Website: [Project Path of Exile Wiki](https://poewiki.net)

# Local Development

## Prerequisites

- [Docker](https://www.docker.com/) is the only prerequisite

## Install instructions

- Create a `docker-compose.yml` file with the following content:

```
# MediaWiki with MariaDB
#
# Access via "http://localhost:8080"
#   (or "http://$(docker-machine ip):8080" if using docker-machine)
version: '3'
services:
  mediawiki:
    image: mediawiki
    restart: always
    ports:
      - 8080:80
    links:
      - database
    volumes:
      - /var/www/html/images
      # After initial setup, download LocalSettings.php to the same directory as
      # this yaml and uncomment the following line and use compose to restart
      # the mediawiki service
      # - ./LocalSettings.php:/var/www/html/LocalSettings.php
      - ./html:/var/www/html:cached
  # This key also defines the name of the database host used during setup instead of the default "localhost"
  database:
    image: mariadb
    restart: always
    environment:
      # @see https://phabricator.wikimedia.org/source/mediawiki/browse/master/includes/DefaultSettings.php
      MYSQL_DATABASE: my_wiki
      MYSQL_USER: wikiuser
      MYSQL_PASSWORD: example
      MYSQL_RANDOM_ROOT_PASSWORD: 'yes'
```

- Download Wikimedia and extract to `/html`
- Initiate the site running `docker-compose up`
- Navigate to `http://localhost:8080` and follow the Wiki initial setup
- Once the `LocalSettings.php` file is downloaded, stop the running `docker-compose`
- Uncomment `# - ./LocalSettings.php:/var/www/html/LocalSettings.php`
- Move the downloaded `LocalSettings.php` to the directory where the `docker-compose.yml` lives
- Update `LocalSettings.php` with the contents from: [here](https://raw.githubusercontent.com/Project-Path-of-Exile-Wiki/wiki/main/before-import.md)
- [PoeWiki uses a lot more extensions](https://www.poewiki.net/wiki/Special:Version), but installing all of them is tedious. There are some I found that were required:
  - [Scribunto](https://www.mediawiki.org/wiki/Extension:Scribunto)
  - [Cargo](https://www.mediawiki.org/wiki/Extension:Cargo/Download_and_installation)
- Start the wiki again with `docker-compose up`
- Download the current XML dump from: [Here](https://github.com/Project-Path-of-Exile-Wiki/wiki/blob/main/dump.zip)
- Unzip in the XML dump into `/html/dump`
- SSH into the docker container: `sudo docker exec -it mediawiki-mediawiki-1 /bin/bash`
- Navigate to the `maintenance` directory, it should be: `/var/www/html/maintenance`
- Start the import script running: `php importDump.php < ../dump/pathofexilefandomcom-20210718-current.xml`, this can take a while to run. Grab yourself a coffee.
- Follow on-screen instructions after the import, but that should be it.

Once you have imported the dump and have ~76,541 pages populated on the wiki, go to https://pathofexile.fandom.com/wiki/Special:CargoTables and click on the links that are to the right of the table name.

For example: 

> amulets (View | Drilldown) - 133 rows (Declared by Template:Item/cargo/amulets, attached by Template:Item/cargo/attach/amulets)

Copy "Template:Item/cargo/amulets" and add it the the URL on your installation. This takes you to a template page which should have a message saying "This template declares the cargo table 'amulets'. The tables does not exist yet."

Edit the page without making any changes, just save. A new button should appear on the top right labeled "Create data tables". Click it and create the table.

Do the same steps for the attached Template (note: some have and some dont have attach templates, some have the attach statement in the same template as the declare, etc.)
