<?php
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

/**
 * This is the configuration file for mediawiki-hgm. It contains important
 * settings that should be reviewed and customized for your environment. Please
 * see the instructions on each line for details about what should be
 * customized and how to properly install the application.
 *
 * For maximum commpatibility with Mediawiki, settings modifications should be
 * made in the mediawiki/LocalSettings.php file. See the README for
 * instructions.
 */

/**
 * Application metadata and credits. Should not be changed.
 */

$wgExtensionCredits['other'][] = array(
    'name'        => 'HGM',
    'author'      => 'Christian Legnittoi - Marc Schifer',
    'url'         => 'https://github.com/LegNeato/mediawiki-hgm',
    'descriptionmsg' => 'hgm-desc',
);

$wgResourceModules['ext.HGM'] = array(
    'scripts' => array( 'web/js/jquery.dataTables.js' ),
    'styles' => array( 'web/css/demo_page.css', 'web/css/demo_table.css', 'web/css/hgm.css' ),
    'messages' => array( 'hgm-hello-world', 'hgm-goodbye-world' ),
    'dependencies' => array( 'jquery.ui.core' ),
    'position' => 'top', // jquery.dataTables.js errors otherwise :(
    'localBasePath' => __DIR__,
    'remoteExtPath' => 'HGM'
);

/**
 * Classes to be autoloaded by mediawiki. Should you add any cache options, you
 * should include them in this list.
 */

$cwd = dirname(__FILE__); // We don't need to do this more than once!

$wgExtensionMessagesFiles['HGM'] =  "$cwd/HGM.i18n.php";

$wgAutoloadClasses['HGM']           = $cwd . '/HGM.class.php';
$wgAutoloadClasses['HGMQuery']      = $cwd . '/HGMQuery.class.php';
$wgAutoloadClasses['HGMOutput']     = $cwd . '/HGMOutput.class.php';
$wgAutoloadClasses['HGMCacheI']     = $cwd . '/cache/HGMCacheI.class.php';
$wgAutoloadClasses['HGMCacheDummy'] = $cwd . '/cache/HGMCacheDummy.class.php';
$wgAutoloadClasses['HGMCacheApc']   = $cwd . '/cache/HGMCacheApc.class.php';
$wgAutoloadClasses['HGMCacheMemcache'] = $cwd . '/cache/HGMCacheMemcache.class.php';
$wgAutoloadClasses['HGMCacheSql']   = $cwd . '/cache/HGMCacheSql.class.php';

/**
 * These hooks are used by mediawiki to properly display the plugin information
 * and properly interpret the tags used.
 */

$wgHooks['LoadExtensionSchemaUpdates'][] = 'HGMCreateCache';
$wgHooks['BeforePageDisplay'][]          = 'HGMIncludeHTML';
$wgHooks['ParserFirstCallInit'][]        = 'HGMParserInit';

// Schema updates for the database cache
function HGMCreateCache($updater) {

    global $wgHGMCacheType;

    global $wgOut;
    $wgOut->enableClientCache(false);

    $class = HGM::getCacheClass($wgHGMCacheType);
    $class::setup($updater);

    // Let the other hooks keep processing
    return true;
}

// Add content to page HTML
function HGMIncludeHTML( &$out, &$sk ) {

    global $wgScriptPath;
    global $wgVersion;
    global $wgHGMJqueryTable;
    global $wgHGMTable;

    if( $wgHGMJqueryTable ) {
        if( version_compare( $wgVersion, '1.17', '<') ) {
            // Use local jquery
            $out->addScriptFile("$wgScriptPath/extensions/HGM/web/jquery/1.6.2/jquery.min.js");

            // Use local jquery ui
            $out->addScriptFile("$wgScriptPath/extensions/HGM/web/jqueryui/1.8.14/jquery-ui.min.js");

            // Add a local jquery css file
            $out->addStyle("$wgScriptPath/extensions/HGM/web/jqueryui/1.8.14/themes/base/jquery-ui.css");

            // Add a local jquery UI theme css file
            $out->addStyle("$wgScriptPath/extensions/HGM/web/jqueryui/1.8.14/themes/smoothness/jquery-ui.css");

            // Add a local script file for the datatable
            $out->addScriptFile("$wgScriptPath/extensions/HGM/web/js/jquery.dataTables.js");

            // Add local datatable styles
            $out->addStyle("$wgScriptPath/extensions/HGM/web/css/demo_page.css");
            $out->addStyle("$wgScriptPath/extensions/HGM/web/css/demo_table.css");

            // Add local hgm extension styles
            $out->addStyle("$wgScriptPath/extensions/HGM/web/css/hgm.css");

        }

        // Add the script to do table magic
        $out->addInlineScript('$(document).ready(function() {
            $("table.hgm").dataTable({
            "bJQueryUI": true,
            "aLengthMenu": ' . $wgHGMTable['lengthMenu'] . ',
            "iDisplayLength" : ' . $wgHGMTable['pageSize'] . ',
            /* Disable initial sort */
            "aaSorting": [],
            })});'
        );
    }

    // Let the user optionally override hgm extension styles
    if( file_exists("$wgScriptPath/extensions/HGM/web/css/custom.css") ) {
        $out->addStyle("$wgScriptPath/extensions/HGM/web/css/custom.css");
    }

    $out->addModules('ext.HGM');

    // Let the other hooks keep processing
    return TRUE;
}

// Hook our callback function into the parser
function HGMParserInit( Parser &$parser ) {
    global $wgHGMTagName;

    // Register the desired tag
    $parser->setHook( $wgHGMTagName, 'HGMRender' );

    // Let the other hooks keep processing
    return true;
}

// Function to be called when our tag is found by the parser
function HGMRender($input, array $args, Parser $parser, $frame=null ) {
    global $wgHGMRESTURL;

    // We don't want the page to be cached
    // TODO: Not sure if we need this
    $parser->disableCache();

    $input = $parser->recursiveTagParse($input, $frame);

    // Create a new hgm object
    $hg = HGM::create($args, $input, $parser->getTitle());
    // Show the desired output (or an error if there was one)
    return $hg->render();
}

/**
 * This configuration is the default configuration for mediawiki-hgm.
 * Please feel free to customize it for your environment. Be sure to make
 * changes in the mediawiki/LocalSettings.php file, to ensure upgrade
 * compatibility.
 */

// Remote API
$wgBugzillaURL    = 'https://bugzilla.mozilla.org'; // The URL for your Bugzilla installation
$wgHGMTagName     = 'hgm'; // The tag name for your HGM installation (default: 'hgm')
$wgHGMSQL         = "SELECT metrics_files.file_name,metrics_files.file_id, metrics_files.mean, metrics_files.stdev, " .
                           "metrics_summary.percent_change, metrics_releases.release_name, metrics_summary.bugs " .
                      "FROM metrics_files, metrics_summary, metrics_releases " .
                     "WHERE metrics_releases.release_id = metrics_summary.release_id "  .
                       "AND metrics_files.file_id = metrics_summary.file_id " .
                       "AND metrics_summary.percent_change > (metrics_files.mean + metrics_files.stdev + :min_change) " .
                       "AND metrics_releases.release_name LIKE :release_name " .
                  "ORDER BY metrics_summary.release_id,metrics_summary.percent_change DESC; " ;

#$wgHGMSQL         = "SELECT metrics_files.file_name, " .
#                                " metrics_releases.release_name, " .
#                                " metrics_changes.delta, " .
#                                " metrics_changes.total_lines, " .
#                                " metrics_changes.percent_change, " .
#                                " metrics_files.mean, " .
#                                " metrics_files.stdev, " .
#                                " metrics_changes.bug  " .
#                         " FROM   metrics_files, " .
#                                " metrics_changes, " .
#                                " metrics_releases " .
#                         " WHERE  metrics_changes.file_id = metrics_files.file_id " .
#                         "   AND  metrics_releases.release_id = metrics_changes.release_id " .
#                         "   AND  metrics_releases.release_name like :release_name " .
#                         "   AND  metrics_changes.percent_change > (metrics_files.mean + metrics_files.stdev + :min_change ) " .
#                         " ORDER BY metrics_changes.release_id,metrics_changes.percent_change DESC; " ;
$wgHGMRelease   = "%";
$wgHGMMin_Value = 0;

// Cache
// NOTE: $wgHGMUseCache has been removed. Use $wgHGMCacheType below only:
// - any valid value for using it
// - equivalent to previous $wgHGMUseCache = false; is $wgHGMCacheType = 'dummy';
$wgHGMCacheType = 'mysql'; // valid values are: memcache, apc, mysql, postgresql, sqlite, dummy.
$wgHGMCacheMins = 1; // Minutes to cache results (default: 5)

$wgHGMJqueryTable = true; // Use a jQuery table for display (default: true)

// Charts
$wgHGMChartStorage = realpath($cwd . '/charts'); // Location to store generated bug charts
$wgHGMFontStorage = $cwd . '/pchart/fonts'; // Path to font directory for font data
$wgHGMChartUrl = $wgScriptPath . '/extensions/HGM/charts'; // The URL to use to display charts
// The default fields to display

$wgHGMDefaultFields = array(
    'release_name',
    'file_id',
    'file_name',
    'percent_change',
    'mean',
    'stdev',
    'bugs'
);


$wgHGMTable = array(
  'pageSize' => 10, //default pagination count
  'lengthMenu' => '[[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]]', //default length set
);
