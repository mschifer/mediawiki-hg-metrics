<?php
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.
class HGMCacheApc implements HGMCacheI
{
    
    public function set($key, $value, $ttl = 300) {
        return apc_store($key, $value, $ttl);
    }
    
    public function get($key) {
        return apc_fetch($key);
    }
    
    public function expire($key) {
        return apc_delete($key);
    }

    public static function setup($updater) {
        return;
    }
}
