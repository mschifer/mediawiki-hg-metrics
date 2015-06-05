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
$wgUseCache = FALSE;

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
$wgReportType                       = 'churn';
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

$wgHGMSQLChurn    = "SELECT metrics_files.file_name, metrics_files.file_id, metrics_files.mean, metrics_files.stdev, " .
                    "metrics_summary.percent_change, metrics_releases.release_name, metrics_summary.bugs, " .  
                    "metrics_summary.backout_count, metrics_summary.committers, metrics_summary.reviewers, " .
                    "metrics_summary.approvers, metrics_summary.msgs, metrics_summary.total_commits, " .
                    ""metrics_summary.regression_count,  metrics_summary.bug_count " .
                    "FROM metrics_files, metrics_summary, metrics_releases " .
                    "WHERE metrics_releases.release_id = metrics_summary.release_id "  .
                    "AND metrics_files.file_id = metrics_summary.file_id " .
                    "AND metrics_summary.percent_change > (metrics_files.mean + metrics_files.stdev + :min_change) ";
                       
$wgChurnWhere1    = "AND metrics_releases.release_name LIKE :release_name " ;
$wgChurnWhere2    = "AND metrics_releases.release_id = " .
                    "(SELECT release_id FROM metrics_releases WHERE release_name LIKE :release_name1 AND release_number = " .
                    "(SELECT max(release_number) FROM metrics_release_master_view WHERE release_name LIKE :release_name2 AND start_date <= DATE()))";

$wgHGMSQLChurnOrder = "ORDER BY metrics_summary.release_id,metrics_summary.percent_change DESC LIMIT 10000; " ;

$wgHGMSQLHistory    = "SELECT mr.release_number, mr.release_name, mb.bug_count, mb.regression_count, mb.bug_fixed, mb.regression_fixed, mb.backout_count " .
                    "FROM metrics_bug_stats mb, metrics_releases mr " .
                    "WHERE mb.release_id = mr.release_id AND mr.release_number > 23 " .
                    "ORDER BY mr.release_number";

$wgHGMSQLBugHistory = array();

$wgHGMSQLBugHistory['release_history'] = "SELECT mr.release_number, mr.release_name, " .
                    "mb.bug_count, mb.regression_count, " .
                    "mb.bug_fixed, mb.regression_fixed, mb.backout_count " .
                    "FROM metrics_bug_stats mb, metrics_releases mr " .
                    "WHERE mb.release_id = mr.release_id " .
                    "AND mr.release_number > 23 "
                    "ORDER BY mr.release_number LIMIT 100";

$wgHGMSQLBugHistory['detail_history'] = "SELECT count(mb.bug) bug_count, " . 
                    "SUM(is_regression) regression_count, mb.release_id, " .
                    "mr.release_name, mr.release_number, component " .
                    "FROM metrics_bugs mb, metrics_releases mr " .
                    "WHERE mr.release_id = mb.release_id " .
                    "AND product = 'firefox' " .
                    "AND component != 'untriaged' " .
                    "GROUP BY mr.release_number, component " .
                    "ORDER BY mr.release_number, bug_count DESC";

$wgHGMSQLBugHistory['team_regression_history'] = "SELECT manager, department, lines_changed, regressions, " . 
                    "release_number, ROUND((regressions/lines_changed),3) AS regression_rate, " .
                    "ROUND((backouts/lines_changed),3) AS backout_rate " .
                    "FROM metrics_team_regression_rate_view " .
                    "WHERE regression_rate > 0 ";

$wgHGMSQLBugHistory['file_regression_history'] = "SELECT file_name, lines_changed, regressions, " .
                    "backouts, ROUND((backouts/lines_changed),3) AS backout_rate, " .
                    "ROUND((regressions/lines_changed),3) AS regression_rate " .
                    "FROM metrics_file_regression_rate_view WHERE regression_rate > 0 ";

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
#$wgHGMDefaultFieldsChurn = array(

$wgHGMDefaultFields = array();
$wgHGMDefaultFields['churn'] = array(
    'release_name',
    'file_name',
    'percent_change',
    'mean',
    'stdev',
    'backout_count',
    'regression_count',
    'bug_count',
    'committers',
    'reviewers',
    'approvers',
    'bugs',
    'file_id',
    'total_commits',
    'msgs'
);

#$wgHGMDefaultFieldsHistory = array(
$wgHGMDefaultFields['release_history'] = array(
    "release_number",
    "release_name",
    "bug_count",
    "regression_count",
    "bug_fixed",
    "regression_fixed",
    "backout_count",
);

$wgHGMDefaultFields['detail_history'] = array(
    "release_number",
    "release_name",
    "bug_count",
    "regression_count",
    "component"
);

$wgHGMDefaultFields['team_regression_history'] = array(
    "manager",
    "department",
    "release_number",
    "lines_changed",
    "backouts",
    "backout_rate",
    "regressions",
    "regression_rate"
);

$wgHGMDefaultFields['file_regression_history'] = array(
    "file_name",
    "lines_changed",
    "backouts",
    "backout_rate",
    "regressions",
    "regression_rate",
);

$wgHGMTable = array(
  'pageSize' => 10, //default pagination count
  'lengthMenu' => '[[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]]', //default length set
);
