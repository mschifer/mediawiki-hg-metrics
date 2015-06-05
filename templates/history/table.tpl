<?php
    global $wgHGMJqueryTable;
    $extra_class = ($wgHGMJqueryTable) ? 'jquery ui-helper-reset' : '';
?>
<table class="hgm <?php echo $extra_class ?>">
    <caption class=hgm-title > <?php echo $this->title; ?></caption>
    <thead>
        <tr>
        <?php
            foreach( $response->fields as $field ) {
                echo "<th>";
                switch( $field ) {
                    case 'bug_count':
                        echo 'Total Bugs';
                        break;
                    case 'component':
                        echo 'Component';
                        break;
                    case 'release_number':
                        echo 'Release';
                        break;
                    case 'release_name':
                        echo 'Branch';
                        break;
                    case 'regression_rate':
                        echo 'Regressions Per<br/>Line';
                        break;
                    case 'msgs`':
                        echo 'Commit Messages';
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
                    echo "<td class='hgm-data-$field'>";

                    // Get our template path
                    $subtemplate = $base .
                        escapeshellcmd(
                            str_replace('..', 'DOTS', $field_name)
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

