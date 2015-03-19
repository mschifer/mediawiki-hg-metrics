MediaWiki extension for Bugzilla
================================

This is a MediaWiki extension that provides read-only access to the 
[Bugzilla REST API](https://wiki.mozilla.org/Bugzilla:REST_API) 

__Please note that there are still big outstanding bugs!__

Requirements
================================

* Requires <a href="http://pear.php.net/package/HTTP_Request2">HTTP_Request2 from PEAR</a>
* For charting, requires <a href="http://libgd.bitbucket.org/">gd</a>
* If using the mysql cache for php. Must set query_cache_size = 32M in the myself .cnf file

Installation
================================

*These directions assume your MediaWiki installation is at /var/lib/mediawiki.
Please substitute your installation path if it is different*

1. Install the requirements above
2. Check the project out into `/var/lib/mediawiki/extensions/Bugzilla`
3. Edit `/etc/mediawiki/LocalSettings.php` and add
   `require_once("/var/lib/mediawiki/extensions/Bugzilla/Bugzilla.php");`
4. Edit `/etc/mediawiki/LocalSettings.php` and change/override any
configuration variables. Current configuration variables and their defaults
can be found at the end of `Bugzilla.php`
5. Run the MediaWiki update script to create the cache database table 
   `php /var/lib/mediawiki/maintenance/update.php`. __Note that you may need to
   add `$wgDBadminuser` and `$wgDBadminpassword` to 
   `/etc/mediawiki/LocalSettings.php` depending on your MediaWiki version__

Usage
================================

You use this extension in this way:

<hgm>
{
 "release":"%-37",
 "minimum_change":"0"
}
</hgm>


By default, it will output a colored table:

![Example output](http://i.imgur.com/IM6xd.png"Example output")

Note that the wiki tag name defaults to "bugzilla" but is 
configurable by the administrator.

Examples
================================


Limitations
================================

* This extension (by design) is read-only
* This extension currently requires the use of the hg-metrics tool to collect data

TODO
================================
* Update other display methods for showing data 
* 
