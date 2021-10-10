## Horizon

### Create new instance in horizon

From https://horizon.wikimedia.org/ naviagate to Compute->Instances and
Launch an Instance with the following configuration:

* source: Debian-10
* flavor: m1.large

After launching the instance click on the instance name. From this page
there is a tab called `Puppet Configuration`. Edit `PuppetClasses` to include:

	role::labs::mediawiki_vagrant

Then navigate in horizon to DNS->Web Proxies. Create the following proxies
pointing at your instance on port 8080:

* cirrustest-`<instance name>`.wmflabs.org
* commons-`<instance name>`.wmflabs.org
* ru-`<instance name>`.wmflabs.org

## From wmcs instance

### Initial setup

Edit /srv/mediawiki-vagrant/Vagrantfile. The lxc options used depend on the
version of lxc installed. For lxc on debian 10 in wmcs replace

	lxc.customize 'aa_profile', 'unconfined'

with

	lxc.customize 'apparmor.profile', 'unconfined'

If `vagrant up` fails due to the nfs mount failing try further editing
the Vagrantfile. Inside the condition `if settings[:nfs_shares]` add:

	root_share_options[:nfs_udp] = false
	root_share_options[:nfs_version] = 4

### Starting the MWV Instance

Run the following commands from /srv/mediawiki-vagrant. The final vagrant up
command should take around 10 minutes or so to run. It may give an error at the
end, often this is a single component not initializing. If you have a
sufficient backscroll there will be items highlighted red that failed during
the run. With any luck whatever it is can be ignored.

	$ vagrant hiera npm::node_version 10
	$ vagrant config nfs_force_v3 true
	$ vagrant roles enable cirrussearch geodata_elastic interwiki langwikis poolcounter sitematrix
	$ vagrant up

Check that provisioning installed extensions:

	$ ls -l /srv/mediawiki-vagrant/mediawiki/extensions/

If not try editing puppet/modules/role/manifests and remove

	include ::role::eventbus

Along with a later reference in a `require` statement to:

	Class['eventschemas']

And then change puppet/modules/elasticsearch/templates/CirrusSearch.php.erb,
on the line that has:

	'handlers' => array( 'eventgate-analytics' ),

Change that to:

	'handlers' => array( 'blackhole' ),

Then try provisioning again

	$ vagrant destroy -f && vagrant provision

### Additional MWV configuration

Create a file as `/srv/mediawiki-vagrant/settings.d/00-cirrus-integration.php`
containing:

	<?php

	$wgPasswordAttemptThrottle = false;
	$wgSiteMatrixSites['wiki']['host'] = 'www.wiki.local.wmftest.net:8080'
	$wgSiteMatrixSites['wiki']['name'] = 'wiki';
	require_once "$IP/extensions/CirrusSearch/tests/jenkins/Jenkins.php";

## Inside the MWV instance

Everything in this section is run from inside the mediawiki-vagrant
instance. To get a shell inside the instance from the host:

	$ cd /srv/mediawiki-vagrant
	$ vagrant ssh


### Additional Software

Install necessary additional software. Ignore warnings about
chown and /vagrant/cache:

$ sudo apt-get -y install chromium-driver

Install dependencies from npm:

$ sudo npm install -g npm
$ cd /vagrant/mediawiki/extensions/CirrusSearch
$ npm install

### Reset to expected state

Before the tests can be run, the MWV instance should be reset to a known state:

$ bash /vagrant/mediawiki/extensions/CirrusSearch/tests/jenkins/resetMwv.sh

### Chromedriver

Chromedriver provides the browser api, it must be running for tests to run.
Start it with:

$ chromedriver --url-base=/wd/hub --port=4444 &

### Run the tests

Then the tests can be run. A variety of environment variables control how access
works. Be sure to change `MWV_LABS_HOSTNAME` to your hostname.

	cd /vagrant/mediawiki/extensions/CirrusSearch/
	export MWV_LABS_HOSTNAME=cirrus-integ-02
	export MEDIAWIKI_USER=Admin
	export MEDIAWIKI_PASSWORD=vagrant
	export MEDIAWIKI_CIRRUSTEST_BOT_PASSWORD=vagrant
	export MEDIAWIKI_COMMONS_BOT_PASSWORD=vagrant
	export MEDIAWIKI_RU_BOT_PASSWORD=vagrant
	$ node_modules/grunt/bin/grunt webdriver:test


If the test run says something like:

	>> Something went wrong: listen EADDRINUSE: address already in use /tmp/cirrussearch-integration-tagtracker

Verify all related processes are dead, this should be empty. Kill the processes
if not:

	$ ps ax | grep node

Then delete the tracker and re-run the tests:

	$ rm /tmp/cirrussearch-integration-tagtracker

