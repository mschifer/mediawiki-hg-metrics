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

        $this->response->files = $this->query->data;
        # Handle case of no data returned
        # Iterate over the default fields list 
        if ( sizeof($this->response->files) == 0) { 
            switch( $this->config['type']) {
                case 'history':
                    foreach ($wgHGMDefaultFieldsHistory as $fld) {
                        $blankData[$fld] = "";
                    }
                case 'churn':
                default:
                    foreach ($wgHGMDefaultFieldsChurn as $fld) {
                        $blankData[$fld] = "";
                    }
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
            switch( $this->config['type']) {
                case 'history':
                    $this->response->fields = $wgHGMDefaultFieldsHistory;
                    break;
                case 'churn':
                default:
                    $this->response->fields = $wgHGMDefaultFieldsChurn;
                    break;
            }
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
            case 'small':
                $imgX = 200;
                $imgY = 65;
                $radius = 30;
                $font = 6;
                break;

            case 'medium':
                $imgX = 400;
                $imgY = 125;
                $radius = 60;
                $font = 7;
                break;

            case 'large':
            default:
                $imgX = 500;
                $imgY = 245;
                $radius = 120;
                $font = 9;
        }

        $padding = 5;

        $startX = ( isset($startX) ) ? $startX : $radius;
        $startY = ( isset($startY) ) ? $startY : $radius;

        $pData = new pData();

        $data['x-axis'] = array();
        $data['y-axis'] = array();
        echo "MOO\n<br/>";
        echo "MOO\n<br/>";
        echo "MOO\n<br/>";
        echo "MOO\n<br/>";
        echo "MOO\n<br/>";
        echo "MOO\n<br/>";
        echo "MOO\n<br/>";
        var_dump($this->query->options);
        var_dump($this->query->data);
        foreach ( $this->query->data as $row) {
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
            #foreach ( array_keys($row) as $field_name ) {
            #    array_push($data['x-axis'], $field_name);
            #    array_push($data['y-axis'], $row[$field_name]);
            #}
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

        foreach ( $this->query->data as $row) {
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
            #foreach ( array_keys($row) as $field_name ) {
            #    array_push($data['x-axis'], $field_name);
            #    array_push($data['y-axis'], $row[$field_name]);
            #}
        }

        $pData->addPoints($data['y-axis'], 'Counts');
        $pData->setAxisName(0, 'Bugs');
        $pData->addPoints($data['x-axis'], "Bugs");
        $pData->setSerieDescription("Bugs", "Bugs");
        $pData->setAbscissa("Bugs");

        $pImage = new pImage(600,300, $pData);
        $pImage->setFontProperties(array('FontName' => $wgHGMFontStorage . '/verdana.ttf', 'FontSize' => 6));
        $pImage->setGraphArea(75, 30, 580, 280);
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

        $pData = new pData();
        $data['x-axis'] = array();
        $data['y-axis'] = array();
        $data['line-names'] = array('nightly','aurora','beta');
        foreach ($data['line-names'] as $branch) {
            $data['x-axis'][$branch] = array();
            $data['y-axis'][$branch] = array();
        }
        if ( (! isset($this->query->options['y_axis_field'])) or
        (! isset($this->query->options['x_axis_field'])) ) {
            return $this->error = "requires fields x_axis_field and y_axis_field to be set";
        }
        foreach ( $this->query->data as $row) {
            array_push($data['x-axis'], $row[$this->query->options['x_axis_field']]);
            foreach ($data['line-names'] as $branch) {
                if ( strripos($row['release_name'],$branch ) !== FALSE ) {
                    array_push($data['y-axis'][$branch], $row[$this->query->options['y_axis_field']]);
                    break;
                }
            }
        }
        $releases = array_unique($data['x-axis']);
        foreach  ($data['line-names'] as $branch) {
            $pData->addPoints($data['y-axis'][$branch],$branch);
            $pData->setSerieWeight($branch,2);
        }
        $pData->addPoints($releases,"Releases");
        $pData->setSerieDescription("Releases","Releases");
        $pData->setAbscissa("Releases");


       /* Create and populate the pData object */
        #$pData->addPoints(array(-4,VOID,VOID,12,8,3),"Probe 1");
        #$pData->addPoints(array(3,12,15,8,5,-5),"Probe 2");
        #$pData->addPoints(array(2,7,5,18,19,22),"Probe 3");
        #$pData->setSerieTicks("Probe 2",4);
        #$pData->setSerieWeight("Probe 3",2);
        #$pData->setAxisName(0,"Temperatures");
        #$pData->addPoints(array("Jan","Feb","Mar","Apr","May","Jun"),"Labels");
        #$pData->setSerieDescription("Labels","Months");
        #$pData->setAbscissa("Labels");


        /* Create the pChart object */
        $pImage = new pImage(700,230,$pData);

        /* Turn of Antialiasing */
        $pImage->Antialias = FALSE;

        /* Add a border to the picture */
        $pImage->drawRectangle(0,0,699,229,array("R"=>0,"G"=>0,"B"=>0));

        /* Write the chart title */
        $pImage->setFontProperties(array('FontName' => $wgHGMFontStorage . '/Forgotte.ttf', 'FontSize' => 11));
        $pImage->drawText(150,35,"Firefox Regression Rate",array("FontSize"=>20,"Align"=>TEXT_ALIGN_BOTTOMMIDDLE));

        /* Set the default font */
        $pImage->setFontProperties(array('FontName' => $wgHGMFontStorage . '/pf_arma_five.ttf', 'FontSize' => 6));

        /* Define the chart area */
        $pImage->setGraphArea(60,40,650,200);

        /* Draw the scale */
        $scaleSettings = array("XMargin"=>10,"YMargin"=>10,"Floating"=>TRUE,"GridR"=>200,"GridG"=>200,"GridB"=>200,"DrawSubTicks"=>TRUE,"CycleBackground"=>TRUE);
        $pImage->drawScale($scaleSettings);

        /* Turn on Antialiasing */
        $pImage->Antialias = TRUE;


        /* Draw the line chart */
        /* Render the picture (choose the best way) */
        $pImage->drawLineChart();
        $pImage->drawLegend(540,20,array("Style"=>LEGEND_NOBORDER,"Mode"=>LEGEND_HORIZONTAL));
        $pImage->render($wgHGMChartStorage . '/' . $chart_name . '.png');
        $cache = $this->_getCache();
        $cache->set($chart_name, $chart_name . '.png');
        return $chart_name;
    }

}


