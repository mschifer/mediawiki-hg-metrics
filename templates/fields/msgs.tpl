<?php 
    $msgList = explode(',', $row['msgs']);
    echo "<script type='text/javascript'> function commit_msgs_" . $row['file_id'] .  "() {var w = window.open('', '', 'width=400,height=400,resizeable,scrollbars');";
    foreach ( $msgList as $msg ) {
       if (strlen(trim($msg)) == 0) {
           continue;
       }
#	echo "w.document.write(\"" . chop(trim(htmlspecialchars($msg))) . "\");";
	echo "w.document.write(\"" . chop(trim($msg)) . "\");";
    }
    echo "w.document.close();";
    echo "}";
    echo "</script>";
    echo "<form>";
    echo "<input type=\"button\" value=\"" . $row["total_commits"] . " Commits\" onclick=\"commit_msgs_" . $row["file_id"] . "()\" >";
    echo "</form>";
   
?>

