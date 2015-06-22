<?php
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

require_once 'HTTP/Request2.php';

// Factory class
class HGMQuery {
    public static function create($type, $options, $title) {

        return new HGMSQLQuery($type, $options, $title); break; ;
    }
}

// Base class
abstract class HGMBaseQuery {

    public function __construct($type, $options, $title) {

        $this->type             = $type;
        $this->title            = $title;
        $this->url              = FALSE;
        $this->id               = FALSE;
        $this->error            = FALSE;
        $this->data             = array();
        $this->synthetic_fields = array();
        $this->cache            = FALSE;
        $this->_set_options($options);

    }

    protected function _getCache()
    {
        if (!$this->cache) {
            $this->cache = HGM::getCache();
        }
        return $this->cache;
    }

    public function id() {

        // If we have already generated an id, return it
        if( $this->id ) { return $this->id; }

        return $this->_generate_id();
    }

    protected function _generate_id() {

        // No need to generate if there are errors
        if( !empty($this->error) ) { return; }

        // FIXME: Should we strtolower() the keys?

        // Sort it so the keys are always in the same order
        ksort($this->options);

        // Treat include_fields special because we don't want to query multiple
        // times if the same fields were requested in a different order
        $saved_include_fields = array();
        if( isset($this->options['include_fields']) &&
            !empty($this->options['include_fields']) ) {

            $saved_include_fields = $this->options['include_fields'];

            // This is important. If a user asks for a subset of the default
            // fields and another user has the same query w/ a subset,
            // it is silly to cache the queries separately. We know the 
            // defaults will always be pulled, so anything asking for
            // any combination of the defaults (or any combined subset) are
            // esentially the same
            $include_fields = $this->synthetic_fields;

            $tmp = @explode(',', $this->options['include_fields']);
            foreach( $tmp as $tmp_field ) {
                $field = trim($tmp_field);
                // Catch if the user specified the same field multiple times
                if( !empty($field) && !in_array($field, $include_fields) ) {
                    array_push($include_fields, $field);
                }
            }
            sort($include_fields);
            $this->options['include_fields'] = @implode(',', $include_fields);
        }

        // Get a string representation of the array
        $id_string = serialize($this->options);

        // Restore the include_fields to what the user wanted
        if( $saved_include_fields ) {
            $this->options['include_fields'] = $saved_include_fields;
        }

        // Hash it
        $this->id = sha1($id_string);

        return $this->id;
    }

    // Connect and fetch the data
    public function fetch() {

        global $wgHGMCacheMins;

        // We need *some* options to do anything
        if( !isset($this->options) || empty($this->options) ) { return; }

        // Don't do anything if we already had an error
        if( $this->error ) { return; }

        $cache = $this->_getCache();
        $row = $cache->get($this->id());
        
        // If the cache entry is older than this we need to invalidate it
        $expiry = strtotime("-$wgHGMCacheMins minutes");

        # Always use a fresh query for testing
        if( !$row ) {
            // No cache entry
            $this->cached = false;
            // Does the HGM query in the background and updates the cache
            $this->_fetch_by_options();
            $this->_update_cache();
            return $this->data;
        } else {
            // Cache is good, use it
            $this->cached = true;
            $this->data = unserialize(base64_decode($row));
        }
    }

    protected function _set_options($query_options_raw) {
        global $wgHGMRelease ;
        global $wgHGMMin_Value;
        global $wgHGMLatest;
        global $wgReportType;
        global $wgGroupBy;
        global $wgOrderBy;

        // Make sure query options are valid JSON
        $this->options = json_decode($query_options_raw, true);
        if( !$query_options_raw || !$this->options ) {
            $this->error = 'Query options must be valid json';
            return;
        }

        // Default Values
        $wgHGMLatest  = false;
        $wgGroupBy    = '';
        $wgReportType = 'churn';
        foreach( $this->options as $key => $value ) {
            switch ($key) {
                case 'release':
                   $wgHGMRelease = $value;
                   break;
                case 'minimum_change':
                   $wgHGMMin_Value = $value;
                   break;
                case 'latest':
                   $wgHGMLatest = true;
                   break;
                case 'report':
                   $wgReportType = $value;
                   break;
                case 'group':
                   $wgGroupBy = "GROUP BY ". $value;
                   break;
                case 'order':
                   $wgOrderBy = "ORDER BY ". $value;
                   break;
            }
        }

        switch( $this->type ) {
            case 'history':
                switch ($wgReportType) {
                    case 'time_to_fix_by_release':
                        $wgOrderBy   = '';
                        break;
                    default:
                        $wgOrderBy   = 'ORDER BY regression_rate DESC';
                        break;
                }
            default:
                $wgOrderBy   = '';
        }



    }

    abstract public function _fetch_by_options();

    protected function _update_cache()
    {
        $cache = $this->_getCache();
        $cache->set($this->id(), base64_encode(serialize($this->data)));
    }

}


/**
*/

class HGMSQLQuery extends HGMBaseQuery {

    function __construct($type, $options, $title='') {
        global $wgHGMDefaultFieldsHistory;
        global $wgHGMSQLChurn;
        global $wgHGMSQLHistory;
        global $wgHGMLatest;
        global $wgHGMSQLChurnOrder;
        global $wgChurnWhere1;
        global $wgChurnWhere2;
        global $wgHGMSQLBugHistory;
        global $wgReportType;
        global $wgOrderBy;
        global $wgGroupBy;

        parent::__construct($type, $options, $title);

        // See what sort of SQL query we are going to
        switch( $type ) {

            // Whitelist
            case 'history':
                # Add ORDER BY clause if specified
            
                $this->sql = $wgHGMSQLBugHistory[$wgReportType] . " " . $wgGroupBy . " " . $wgOrderBy;
                break;
            case 'churn':
            default:
                if ( $wgHGMLatest ) {
                    $hgOp = $wgChurnWhere2;
                } else {
                    $hgOp = $wgChurnWhere1;
                }
                $this->sql = $wgHGMSQLChurn . $hgOp . $wgHGMSQLChurnOrder;
        }

        $this->fetch();
    }

    // Load data from the HGM SQLite database
    public function _fetch_by_options() {
        global $dbh;
        global $wgHGMRelease;
        global $wgHGMMin_Value;
        global  $wgHGMLatest;

        $dbfile = __DIR__ . '/churndb.sql';
        $dbh = new PDO('sqlite:' . $dbfile );
        $data = array();
        $maxrows = 10;
        $rowcnt = 0;
        $stmt = $dbh->prepare($this->sql) ;
        if (!$stmt) {
            $this->error = "COWS:". $this->sql;
            var_dump( $dbh->errorInfo());
            return;
        }
        
        if ($this->type == 'churn') {
            if ( $wgHGMLatest ) {
                $stmt->bindParam(":release_name1", $wgHGMRelease, PDO::PARAM_STR);
                $stmt->bindParam(":release_name2", $wgHGMRelease, PDO::PARAM_STR);
                $stmt->bindParam(":min_change", $wgHGMMin_Value, PDO::PARAM_INT);
            } else {
                $stmt->bindParam(":release_name", $wgHGMRelease, PDO::PARAM_STR);
                $stmt->bindParam(":min_change", $wgHGMMin_Value, PDO::PARAM_INT);
            }
        }
        #echo $this->sql;
        $stmt->execute();
        $this->data = $stmt->fetchAll(PDO::FETCH_ASSOC) ;
        return;
    }
}
