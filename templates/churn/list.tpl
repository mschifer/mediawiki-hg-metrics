<ul>
    <?php
        $base = dirname(__FILE__) . '/../../templates/fields/';

        foreach( $response->files as $row ) {
            #echo "<li class='hgm-status-${row['status']}'>";
            $count = 0;
            foreach( $response->fields as $field_name ) {
                if( $count ) {
                    echo " - ";
                }
                echo "<span class='hgm-data-$field_name'>";

                // Get our template path
                $subtemplate = $base . 
                    escapeshellcmd(str_replace('..',
                        'DOTS',
                        $field_name
                    )
                ) . '.tpl';

                // Make sure a template is there
                if( !file_exists($subtemplate) ) {
                    $subtemplate = $base . '_default.tpl';
                }

                // Print out the data
                $data = $row[$field_name];
                require($subtemplate);

                echo "</span>";
                $count++;
            }
            echo "</li>\n";
        }
    ?>
</ul>
