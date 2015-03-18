<?php
    global $wgHGMJqueryTable;
    $extra_class = ($wgHGMJqueryTable) ? 'jquery ui-helper-reset' : '';
?>
<table class="hgm <?php echo $extra_class ?>">
    <thead>
        <tr>
        <?php
            foreach( $response->fields as $field ) {
                echo "<th>";
                switch( $field ) {
                    case 'id':
                        echo 'ID';
                        break;
                    default:
                        echo htmlspecialchars(
                            ucfirst(
                                str_replace('_', ' ',
                                    preg_replace('/^cf_/', '', $field)
                                )
                            )
                        );
                }
                echo "</th>\n";
            }
        ?>
        </tr>
    </thead>
    <tbody>
        <?php
            $base = dirname(__FILE__) . '/../../templates/fields/';

            echo "<tr>";
            foreach ( $response->files as $row) {
              foreach ( $response->fields as $field_name ) {
              #foreach ( $row as $field) {
                    echo "<td class='hgm-data-$field'>";

                    // Get our template path
                    $subtemplate = $base .
                        escapeshellcmd(
                            str_replace('..', 'DOTS', $row[$field_name])
                        ) . '.tpl';

                    // Make sure a template is there
                    if( !file_exists($subtemplate) ) {
                        $subtemplate = $base . '_default.tpl';
                    }

                    // Print out the data
                    $data = $row[$field_name];
                    require($subtemplate);

                    echo "</td>\n";
               }
                echo "</tr>\n";
             }
        ?>
    </tbody>
</table>

