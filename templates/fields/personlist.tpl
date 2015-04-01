<?php 
    foreach ( $personList as $person ) {
       if (strlen(trim($person)) == 0) {
           continue;
       }
       echo htmlspecialchars($person) . ", ";
    }
?>
