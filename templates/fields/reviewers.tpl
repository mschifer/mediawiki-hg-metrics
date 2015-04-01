<?php 
    $personList = explode(',', $row['reviewers']);
    // Wrapper
    require(dirname(__FILE__) . '/personlist.tpl');
?>

