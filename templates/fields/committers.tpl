<?php 
    $personList = explode(',', $row['committers']);
    // Wrapper
    require(dirname(__FILE__) . '/personlist.tpl');
?>

