<?php 
    global $wgBugzillaURL;
    $bugList = explode(',', $row['bugs']);
    foreach ( $bugList as $bug ) {
       echo "<a href='$wgBugzillaURL/show_bug.cgi?id=" .
                urlencode($bug) ."'>";
       echo htmlspecialchars($bug);
       echo "</a> ";
    }
?>
