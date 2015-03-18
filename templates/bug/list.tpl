<ul>
    <?php
        $base = dirname(__FILE__) . '/../../templates/fields/';

        foreach( $response->bugs as $bug ) {
            echo "<li class='hgm-status-${bug['status']}'>";
            $count = 0;
            foreach( $response->fields as $field ) {
                if( $count ) {
                    echo " - ";
                }
                echo "<span class='hgm-data-$field'>";

                // Get our template path
                $subtemplate = $base . 
                    escapeshellcmd(str_replace('..',
                        'DOTS',
                        $field
                    )
                ) . '.tpl';

                // Make sure a template is there
                if( !file_exists($subtemplate) ) {
                    $subtemplate = $base . '_default.tpl';
                }

                // Print out the data
                $data = $bug[$field];
                require($subtemplate);

                echo "</span>";
                $count++;
            }
            echo "</li>\n";
        }
    ?>
</ul>
