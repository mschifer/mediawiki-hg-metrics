<?php 
    global $wgHGMURL;

    echo "<a href='$wgHGMURL/show_bug.cgi?id=" .
             urlencode($bug['id']) ."'>";
    echo     htmlspecialchars($bug['url']);
    echo  "</a>";
?>
