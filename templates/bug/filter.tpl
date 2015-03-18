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

            $all = count($response->bugs);
            $resolved = 0;
            $verified = 0;
            $filter_op    = $this->config['filter_op'];

            foreach( $response->bugs as $bug ) { 
                $matches = 0;
                // If the filter field is an array filter on number of enteries
                // otherwise do a direct compare

                if (is_array($bug[$this->config['filter_on']] )) {
                    $filter_value = intval($this->config['filter_value']);
                    $field_value  = count($bug[$this->config['filter_on']]);
 
                } else {
                    $filter_value = $this->config['filter_value'];
                    $field_value  = $bug[$this->config['filter_on']];

                }

                switch( $filter_op ) {
                    case 'gt':
                        if ( $field_value > $filter_value ) {
                            $matches = 1;
                        }
                        break;

                    case 'lt':
                        if ( $field_value < $filter_value ) {
                            $matches = 1;
                        }
                        break;
                    case 'ne':
                        if ( $field_value <> $filter_value ) {
                            $matches = 1;
                        }
                        break;
                    case 'eq':
                    default:
                        if ( $field_value == $filter_value ) {
                            $matches = 1;
                        }
                }
                if (!$matches) {
                    continue;
                }


                if($bug['status'] == 'RESOLVED') {
                    $resolved++;
                }
                if($bug['status'] == 'VERIFIED') {
                    $verified++;
                }

                echo "<tr class='hgm-status-${bug['status']}'>";
                foreach( $response->fields as $field ) {
                    echo "<td class='hgm-data-$field'>";

                    // Get our template path
                    $subtemplate = $base .
                        escapeshellcmd(
                            str_replace('..', 'DOTS', $field)
                        ) . '.tpl';

                    // Make sure a template is there
                    if( !file_exists($subtemplate) ) {
                        $subtemplate = $base . '_default.tpl';
                    }

                    // Print out the data
                    $data = $bug[$field];
                    require($subtemplate);

                    echo "</td>\n";
                }
                echo "</tr>\n";
            }
        ?>
    </tbody>
</table>

<strong>
<?php echo $all ?> Total;
<?php echo $all-$resolved-$verified ?> Open (<?php if ($all != 0) echo 100*(round(($all-$resolved-$verified)/$all, 4)); else echo 0 ?>%);
<?php echo $resolved ?> Resolved (<?php if ($all != 0) echo 100*(round(($resolved)/$all, 4)); else echo 0 ?>%);
<?php echo $verified ?> Verified (<?php if ($all != 0) echo 100*(round(($verified)/$all, 4)); else echo 0 ?>%);
</strong>
