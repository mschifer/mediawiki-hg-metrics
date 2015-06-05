 <?php

abstract class HGMOutput {

    public $response;
    public $cache;

    public function __construct($config, $options, $title='') {
        $this->title    = $title;
        $this->config   = $config;
        $this->error    = false;
        $this->response = new stdClass();

        // Make our query and possibly fetch the data
        $this->query = HGMQuery::create($config['type'], $options, $title);

        // Bubble up any query errors
        if( $this->query->error ) {
            $this->error = $this->query->error;
        }
        $e = new Exception;
        $opts = $this->query->options;
        if ($this->config['type']== "churn" ) {
            $tmp_title = '';
            if (array_key_exists('title', $this->query->options)) {
                $tmp_title .= $this->query->options['title'];
            }
            if (array_key_exists('release', $this->query->options)) {
                $tmp_title .= " For Release " . $this->query->options['release'];
            }
            if (array_key_exists('minimum_change', $this->query->options)) {
                $tmp_title .= " Where change rate is greater than " . $this->query->options['minimum_change'] . " over average";
            }
            $this->title = $tmp_title;
        }
        if ($this->config['type']== "history" ) {
            if (array_key_exists('title', $this->query->options)) {
                $this->title = $this->query->options['title'];
            } else {
                $this->title = "Default Title:" . $this->title;
            }
        }

    }

    protected function _render_error($error) {
        $this->template = dirname(__FILE__) . '/templates/error.tpl';
        ob_start(); // Start output buffering.
        require($this->template);
        return ob_get_clean();
    }

    public function render() {
        // Get our template path
        $this->template = dirname(__FILE__) . '/templates/' .
                          $this->config['type'] . '/' .
                          $this->config['display'] . '.tpl';

        // Make sure a template is there
        if( !file_exists($this->template) ) {
            $this->error = 'Invalid type ' .
                           '(' . htmlspecialchars($this->config['type']) . ')' .
                           ' and display ' .
                           '(' . htmlspecialchars($this->config['display']) . ')' .
                           ' combination';
        }

        // If there are any errors (either from the template path above or
        // elsewhere) output them
        if( $this->error ) {
            return $this->_render_error($this->error);
        }
        $this->setup_template_data();

        $response = $this->response;
        ob_start(); // Start output buffering.
        require($this->template);
        $results = ob_get_clean();
        return $results;

    }

    protected function _getCache()
    {
        if (!$this->cache) {
            $this->cache = HGM::getCache();
        }

        return $this->cache;
    }

    abstract protected function setup_template_data();

}

class HGMBugListing extends HGMOutput {

    protected function setup_template_data() {

        global $wgHGMDefaultFieldsChurn;
        global $wgHGMDefaultFieldsHistory;
        global $wgHGMDefaultFields;
        global $wgReportType;

        $this->response->files = $this->query->data;
        # Handle case of no data returned
        # Iterate over the default fields list 
        if ( sizeof($this->response->files) == 0) { 
            foreach ($wgHGMDefaultFields[$wgReportType] as $fld) {
                $blankData[$fld] = "";
            }
             
            $this->response->files = [ $blankData ];
        }
        $this->response->fields = array();


        // Set the field data for the templates
        if( isset($this->query->options['include_fields']) &&
            !empty($this->query->options['include_fields']) ) {
            // User specified some fields
            $tmp = @explode(',', $this->query->options['include_fields']);
            foreach( $tmp as $tmp_field ) {
                $field = trim($tmp_field);
                // Catch if the user specified the same field multiple times
                if( !empty($field) &&
                    !in_array($field, $this->response->fields) ) {
                    array_push($this->response->fields, $field);
                }
            }
        }else {
            // If the user didn't specify any fields in the query config use
            // default fields
            $this->response->fields = $wgHGMDefaultFields[$wgReportType];
        }
    }

}

class HGMList extends HGMBugListing {

}

class HGMTable extends HGMBugListing {

}


/* Graphing */

abstract class HGMGraph extends HGMOutput {

    protected function _get_size() {

        switch($this->config['size']) {

            // whitelist
            case 'smaller':
            case 'small':
            case 'medium':
            case 'large':
                return $this->config['size'];
                break;

            default:
                return 'large';
        }
    }

    public function setup_template_data() {
        include_once 'pchart/class/pDraw.class.php';
        include_once 'pchart/class/pImage.class.php';
        include_once 'pchart/class/pData.class.php';

        global $wgHGMChartUrl;

        $key = md5($this->query->id . $this->_get_size() . get_class($this));
        $cache = $this->_getCache();
        if($result = $cache->get($key)) {
            $image = $result;
            $this->response->image = $wgHGMChartUrl . '/' . $image;
        } else {
            $this->response->image = $wgHGMChartUrl . '/' . $this->generate_chart($key) . '.png';
        }
    }

}

class HGMPieGraph extends HGMGraph {

    public function generate_chart($chart_name)
    {
        include_once "pchart/class/pPie.class.php";

        global $wgHGMChartStorage;
        global $wgHGMFontStorage;

        // TODO: Make all this size stuff trivial for other
        // graph types to plug into
        switch($this->_get_size()) {
            case 'smaller':
                $imgX = 200;
                $imgY = 65;
                $radius = 30;
                $font = 6;
                break;

            case 'small':
                $imgX = 400;
                $imgY = 125;
                $radius = 60;
                $font = 7;
                break;

            case 'medium':
                $imgX = 500;
                $imgY = 245;
                $radius = 120;
                $font = 9;
                break;
            case 'large':
            default:
                $imgX = 1000;
                $imgY = 500;
                $radius = 240;
                $font = 12;
        }

        $padding = 5;

        $startX = ( isset($startX) ) ? $startX : $radius;
        $startY = ( isset($startY) ) ? $startY : $radius;

        $pData = new pData();

        $data['x-axis'] = array();
        $data['y-axis'] = array();
        $maxrows = 77;
        $current_row = 0;
        foreach ( $this->query->data as $row) {
            if ($current_row > $maxrows) {
               echo 'Max Rows Exceeded - No more colors to choose from<br/>';
                break;
            }
            $current_row += 1;
            if (isset($this->query->options['x_axis_field'])) {
                array_push($data['x-axis'], $row[$this->query->options['x_axis_field']]);
            } else {
                echo "PIE x_axis_field is NOT set";
                array_push($data['x-axis'], 0 );
            }
            if (isset($this->query->options['y_axis_field'])) {
                array_push($data['y-axis'], $row[$this->query->options['y_axis_field']]);
            } else {
                array_push($data['y-axis'], 0 );
                echo "PIE y_axis_field is NOT set";
            }
        }
        $pData = new pData();
        $pData->addPoints($data['y-axis'], 'Counts');
        $pData->setAxisName(0, 'Counts');
        $pData->addPoints($data['x-axis'], "Fields");
        $pData->setSerieDescription("Fields", "Fields");
        $pData->setAbscissa("Fields");


        $pImage = new pImage($imgX, $imgY, $pData);
        $pImage->setFontProperties(array('FontName' => $wgHGMFontStorage . '/verdana.ttf', 'FontSize' => $font));
        $pPieChart = new pPie($pImage, $pData);

        $pPieChart->draw2DPie($startX,
                              $startY,
                              array(
                                  "Radius" => $radius,
                                  "ValuePosition" => PIE_VALUE_INSIDE,
                                  "WriteValues"=>PIE_VALUE_NATURAL,
                                  "DrawLabels"=>FALSE,
                                  "LabelStacked"=>TRUE,
                                  "ValueR" => 0,
                                  "ValueG" => 0,
                                  "ValueB" => 0,
                                  "Border"=>TRUE));

        // Legend
        $pImage->setShadow(FALSE);
        $pPieChart->drawPieLegend(2*$radius + 2*$padding, $padding, array("Alpha"=>20));

        $pImage->render($wgHGMChartStorage . '/' . $chart_name . '.png');
        $cache = $this->_getCache();
        $cache->set($chart_name, $chart_name . '.png');
        return $chart_name;
    }
}

class HGMBarGraph extends HGMGraph {

    public function generate_chart($chart_name)
    {
        global $wgHGMChartStorage, $wgHGMFontStorage;
        $pData = new pData();
        $data['x-axis'] = array();
        $data['y-axis'] = array();
        $maxbars = 15;
        $barcount = 0;
        foreach ( $this->query->data as $row) {
            var_dump($row);
            $barcount += 1;
            if (isset($this->query->options['x_axis_field'])) {
                array_push($data['x-axis'], $row[$this->query->options['x_axis_field']]);
            } else {
                echo "BAR x_axis_field is NOT set";
                array_push($data['x-axis'], 0 );
            }
            if (isset($this->query->options['y_axis_field'])) {
                array_push($data['y-axis'], $row[$this->query->options['y_axis_field']]);
            } else {
                echo "BAR y_axis_field is NOT set";
                array_push($data['y-axis'], 0 );
            }
            if ($barcount > $maxbars) {
               break;
            }
        }

        $pData->addPoints($data['y-axis'], $this->query->options['y_axis_field']);
        $pData->setAxisName(0, $this->query->options['x_axis_field']);
        $pData->addPoints($data['x-axis'], $this->query->options['x_axis_field']);
        $pData->setSerieDescription($this->query->options['x_axis_field'], $this->query->options['x_axis_field']);
        $pData->setAbscissa($this->query->options['x_axis_field']);

        switch($this->config['size']) {
            case 'smaller':
            case 'small':
                $w = 400;
                $h = 200;
                $font = 10;
                break;
            case 'medium':
                $w = 600;
                $h = 300;
                $font = 12;
                break;
            case 'large':
            default:
                $w = 1200;
                $h = 600;
                $font = 16;
                break;
        }

        $pImage = new pImage($w ,$h , $pData);
        $pImage->setFontProperties(array('FontName' => $wgHGMFontStorage . '/verdana.ttf', 'FontSize' => $font));
        $pImage->setGraphArea(75, 30, $w - 20, $h - 20);
        $pImage->drawScale(array("CycleBackground"=>TRUE,'Factors'=>array(1),"DrawSubTicks"=>FALSE,"GridR"=>0,"GridG"=>0,"GridB"=>0,"GridAlpha"=>10, "Pos"=>SCALE_POS_TOPBOTTOM)); 

        $pImage->drawBarChart();
        $pImage->render($wgHGMChartStorage . '/' . $chart_name . '.png');
        $cache = $this->_getCache();
        $cache->set($chart_name, $chart_name . '.png');
        return $chart_name;
    }

}

class HGMLineGraph extends HGMGraph {
    public function generate_chart($chart_name)
    {
        global $wgHGMChartStorage, $wgHGMFontStorage;
        global $wgReportType;

        if ( (! isset($this->query->options['y_axis_field'])) or
        (! isset($this->query->options['x_axis_field'])) or
        (! isset($this->query->options['datapoints'])) ) {
            return $this->error = "requires fields x_axis_field, y_axis_field or datapoints to be set";
        }

        $releases = array();
        switch( $wgReportType) {
            case 'detail_history':
                $data = $this->detail_history_chart();
                break;
            case 'file_regression_history':
            case 'team_regression_history':
                $data = $this->regression_history_chart();
                break;
            case 'release_history':
            default:
                $data = $this->release_history_chart();
                break;
         }
        $pData = new pData();
        
        foreach (array_unique($data['x-axis'], SORT_REGULAR) as $rel) {
            if ( gettype($rel) != "array") {
                $releases[] = $rel;
            }  
        }
        $maxlines = 10;
        $linesadded = 0;
        foreach  (array_keys($data['y-axis']) as $linename ) {
            $linesadded += 1;
            $pData->addPoints($data['y-axis'][$linename],$linename);
            $pData->setSerieWeight($linename,2);
            if ($linesadded >= $maxlines) {
                break;
            } 
        }

        # X Axis Lables
        $pData->addPoints($releases,"Releases");
        $pData->setSerieDescription("Releases","Releases");
        $pData->setAbscissa("Releases");

        /* Create the pChart object */
        $l1 = 300;
        $l2 = 50;
        switch($this->config['size']) {
            case 'smaller':
            case 'small':
                $w = 700;
                $h = 230;
                $l1 = 150;
                $l2 = 10;
                $font = 6;
                break;
            case 'medium':
                $w = 900;
                $h = 365;
                $l1 = 300;
                $l2 = 20;
                $font = 9;
                break;
            case 'large':
            default:
                $w = 1100;
                $h = 600;
                $l1 = 400;
                $l2 = 20;
                $font = 11;
                break;
        }
        $pImage = new pImage($w,$h,$pData);

        /* Turn of Antialiasing */
        $pImage->Antialias = FALSE;

        /* Add a border to the picture */
        $pImage->drawRectangle(0,0,$w - 1, $h - 1,array("R"=>0,"G"=>0,"B"=>0));

        /* Write the chart title */
        $pImage->setFontProperties(array('FontName' => $wgHGMFontStorage . '/Forgotte.ttf', 'FontSize' => $font + 4));
        $pImage->drawText(150,35,$this->title,array("FontSize"=>20,"Align"=>TEXT_ALIGN_BOTTOMMIDDLE));

        /* Set the default font */
        $pImage->setFontProperties(array('FontName' => $wgHGMFontStorage . '/pf_arma_five.ttf', 'FontSize' => $font));

        /* Define the chart area */
        $pImage->setGraphArea(60,40,$w - 50,$h - 50);

        /* Draw the scale */
        $scaleSettings = array("XMargin"=>10,"YMargin"=>10,"Floating"=>TRUE,"GridR"=>200,"GridG"=>200,"GridB"=>200,"DrawSubTicks"=>TRUE,"CycleBackground"=>TRUE);
        $pImage->drawScale($scaleSettings);

        /* Turn on Antialiasing */
        $pImage->Antialias = TRUE;


        /* Draw the line chart */
        /* Render the picture (choose the best way) */
        $pImage->drawLineChart();
        $pImage->drawLegend($w - $l1,$l2,array("Style"=>LEGEND_NOBORDER,"Mode"=>LEGEND_VERTICAL));
        $pImage->render($wgHGMChartStorage . '/' . $chart_name . '.png');
        $cache = $this->_getCache();
        $cache->set($chart_name, $chart_name . '.png');
        return $chart_name;
    }

    public function release_history_chart()
    {

        $data['x-axis'] = array();
        $data['y-axis'] = array();
        $data['line-names'] = array();
        $data['datapoint-names'] = array();

        # Specify the lines to be charted
        # Defaults to the basic set of branches
        if (isset($this->query->options['y_axis_field'])) {
            if (is_array(($this->query->options['y_axis_field']))) {
                foreach ($this->query->options['y_axis_field'] as $item) {
                    array_push($data['line-names'], $item);
                }
            } else {
                array_push($data['line-names'],$this->query->options['y_axis_field']);
            }
        } else {
            $data['line-names'] = array('nightly','aurora','beta');
        }

        # Determine the data points to be charted
        if (isset($this->query->options['datapoints'])) {
            if (is_array(($this->query->options['datapoints']))) {
                foreach ($this->query->options['datapoints'] as $item) {
                    array_push($data['datapoint-names'], $item);
                }
            } else {
                array_push($data['datapoint-names'],$this->query->options['datapoints']);
            }
        } else {
            $data['datapoint-names'] = array('regression_count');
        }

        foreach ( $this->query->data as $row) {
            array_push($data['x-axis'], $row[$this->query->options['x_axis_field']]);
            foreach ( $data['line-names'] as $ln) {
                # Only add data point to the line if the release name contains the line name root
                if ( strripos($row['release_name'],$ln) !== FALSE ) {
                    foreach ( $data['datapoint-names'] as $dp ) {
                        $line = $ln . '_' . $dp;
                        if (! array_key_exists($line,$data['y-axis'] )) {
                            $data['y-axis'][$line] = array();
                        }
                        array_push($data['y-axis'][$line], $row[$dp]);
                    }
                }
            }
        }

        return $data;

    }
    public function detail_history_chart()
    {
        $data['x-axis'] = array();
        $data['y-axis'] = array();
        $data['line-names'] = array();
        $data['datapoint-names'] = array();

        # line names are going to be compoent names, author names etc.. drawn from the query
        # we don't want to have to work through the query multiple times to get it either.
        # Specify the lines to be charted
        # Defaults to the basic set of branches

        # Determine the data points to be charted
        if (isset($this->query->options['datapoints'])) {
            if (is_array(($this->query->options['datapoints']))) {
                foreach ($this->query->options['datapoints'] as $item) {
                    array_push($data['datapoint-names'], $item);
                }
            } else {
                array_push($data['datapoint-names'],$this->query->options['datapoints']);
            }
        } else {
            $data['datapoint-names'] = array('bug_count');
        }
        $components = array();
        foreach ( $this->query->data as $row) {
            array_push($components, $row[$this->query->options['y_axis_field'][0]]);
        }
        $data['line-names'] = array_unique($components, SORT_REGULAR);
        echo "<BR/>";

        foreach ( $this->query->data as $row) {
            array_push($data['x-axis'], $row[$this->query->options['x_axis_field']]);
            foreach ( $data['line-names'] as $ln) {
                # Only add data point to the line if the release name contains the line name root
                if ( strripos($row[$this->query->options['y_axis_field']],$ln) !== FALSE ) {
                    foreach ( $data['datapoint-names'] as $dp ) { 
                        $line = $ln . '_' . $dp;
                        if (! array_key_exists($line,$data['y-axis'] )) {
                            $data['y-axis'][$line] = array();
                        }       
                        array_push($data['y-axis'][$line], $row[$dp]);
                    }       
                }       
            }
        }


        return $data;

    }
    public function regression_history_chart()
    {
        $data['x-axis'] = array();
        $data['y-axis'] = array();
        $data['line-names'] = array();
        $data['datapoint-names'] = array();

        # line names are going to be compoent names, author names etc.. drawn from the query
        # we don't want to have to work through the query multiple times to get it either.
        # Specify the lines to be charted
        # Defaults to the basic set of branches

        # Determine the data points to be charted
        if (isset($this->query->options['datapoints'])) {
            if (is_array(($this->query->options['datapoints']))) {
                foreach ($this->query->options['datapoints'] as $item) {
                    array_push($data['datapoint-names'], $item);
                }
            } else {
                array_push($data['datapoint-names'],$this->query->options['datapoints']);
            }
        } else {
            $data['datapoint-names'] = array('regression_count');
        }
        $departments = array();
        foreach ( $this->query->data as $row) {
            array_push($departments, $row[$this->query->options['y_axis_field'][0]]);
        }
        $data['line-names'] = array_unique($departments, SORT_REGULAR);
        echo "<BR/>";

        foreach ( $this->query->data as $row) {
            array_push($data['x-axis'], $row[$this->query->options['x_axis_field']]);
            foreach ( $data['line-names'] as $ln) {
                # Only add data point to the line if the release name contains the line name root
                if ( strripos($row[$this->query->options['y_axis_field'][0]],$ln) !== FALSE ) {
                    foreach ( $data['datapoint-names'] as $dp ) { 
                        $line = $ln . '_' . $dp;
                        if (! array_key_exists($line,$data['y-axis'] )) {
                            $data['y-axis'][$line] = array();
                        }       
                        array_push($data['y-axis'][$line], $row[$dp]);
                    }       
                }       
            }
        }


        return $data;

    }
}


