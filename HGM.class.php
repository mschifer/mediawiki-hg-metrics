<?php
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

$dir = dirname(__FILE__);
require_once ($dir . '/HGMOutput.class.php');
require_once ($dir . '/cache/HGMCacheI.class.php');
require_once ($dir . '/cache/HGMCacheDummy.class.php');
require_once ($dir . '/cache/HGMCacheApc.class.php');
require_once ($dir . '/cache/HGMCacheMemcache.class.php');
require_once ($dir . '/cache/HGMCacheSql.class.php');

// Factory
class HGM {

    public static function create($config=array(), $opts=array(), $title='') {


        // Default configuration
        $theconfig = array(
            'type'    => 'bug',
            'display' => 'table',
        );

        // Overlay user's desired configuration
        foreach( $config as $key => $value ) {
            $theconfig[$key] = $value;
        }

        // Generate the proper object
        switch( $theconfig['display'] ) {
            case 'list':
                $b = new HGMList($theconfig, $opts, $title);
                break;

            case 'bar':
                $b = new HGMBarGraph($theconfig, $opts, $title);
                break;

            case 'vbar':
                $b = new HGMVerticalBarGraph($theconfig, $opts, $title);
                 break;

            case 'pie':
                $b = new HGMPieGraph($theconfig, $opts, $title);
                break;

            case 'inline':
                $b = new HGMInline($theconfig, $opts, $title);
                break;

            case 'table':
            default:
                $b = new HGMTable($theconfig, $opts, $title);
        }

        return $b;

    }

    /**
     * Return the HGMCacheI extended class in charge
     * for the cache backend in use.
     *
     * @param string $type
     *
     * @return string
    */
    public static function getCacheClass( $type ) {

        $suffix = 'dummy';

        if ( in_array( $type, array( 'mysql', 'postgresql', 'sqlite' ) ) ) {;
            $suffix = 'sql';
        } elseif ( in_array( $type, array( 'apc', 'memcache' ) ) ) {
            $suffix = $type;
        }

        return 'HGMCache' . ucwords( $suffix );
    }

    /**
     * Build and return a working cache, depending on config.
     *
     * @return HGMCacheI object
    */
    public static function getCache() {
        global $wgHGMCacheType;

        $object = self::getCacheClass( $wgHGMCacheType );

        return new $object();
    }
}

