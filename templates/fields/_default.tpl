<?php
    echo "<span class='hgm-field-$field_name'>";
    if( is_array($row[$field_name] )) {
        echo htmlspecialchars(implode(', ', $row[$field_name]));
    }else{
        echo htmlspecialchars($row[$field_name]);
    }
    echo "</span>";
?>
