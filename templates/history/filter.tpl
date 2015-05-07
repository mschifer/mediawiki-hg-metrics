<?php
    global $wgHGMJqueryTable;
    $extra_class = ($wgHGMJqueryTable) ? 'jquery ui-helper-reset' : '';
    $fop= $this->config['filter_op'];
    $foo= $this->config['filter_on'];
    $fov= $this->config['filter_value'];
?>
<table class="hgm <?php echo $extra_class ?>">

    <caption class=hgm-title > <?php print $this->config['type'] . " $this->title </br> Filter " . $this->config['filter_on'] . " " .$this->config['filter_op'] . " " .$this->config['filter_value']; ?> </captpion>
    <thead>
        <tr>
        <?php
            foreach( $response->fields as $field ) {
                echo "<th>";
                switch( $field ) {
                    case 'bugs':
                        echo 'Related Bugs';
                        break;
                    case 'stdev':
                        echo 'Standard Deviation';
                        break;
                    case 'mean':
                        echo 'Lifetime Mean';
                        break;
                    case 'percent_change':
                        echo 'Percent Change this Release';
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

            $filter_op    = $this->config['filter_op'];

            foreach( $response->files as $row ) { 
                $matches = 0;
                // If the filter field is an array filter on number of enteries
                // otherwise do a direct compare

                if (is_array($row[$this->config['filter_on']] )) {
                    $filter_value = intval($this->config['filter_value']);
                    $field_value  = count($row[$this->config['filter_on']]);
 
                } else {
                    $filter_value = $this->config['filter_value'];
                    $field_value  = $row[$this->config['filter_on']];

                }

                switch( $filter_op ) {
                    case 'notlike':
                        $matches = 1;
                        $notlist = explode(',', $filter_value);
                        foreach ($notlist as $value) {
                            if (strpos($field_value, $value) !== false) {
                                $matches = 0;
                            }
                        }
                        break;
                    case 'like':
                        $likelist = explode(',', $filter_value);
                        foreach ($likelist as $value) {
                            if (strpos($field_value, $value) !== false) {
                                $matches = 1;
                            }
                        }
                        #break;
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

                foreach( $response->fields as $field_name ) {
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

