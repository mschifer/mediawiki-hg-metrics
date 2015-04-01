<?php 
    $personList = explode(',', $row['approvers']);
    // Wrapper
    require(dirname(__FILE__) . '/personlist.tpl');
?>

