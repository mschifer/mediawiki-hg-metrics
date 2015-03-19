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

        global $wgHGMDefaultFields;
        #$this->response->files = json_encode($this->query->data);
        $this->response->files = $this->query->data;
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
            $this->response->fields = $wgHGMDefaultFields;
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
        $pData->addPoints($this->query->data['data'], 'Counts');
        $pData->setAxisName(0, 'Bugs');
        $pData->addPoints($this->query->data['x_labels'], "Bugs");
        $pData->setSerieDescription("Bugs", "Bugs");
        $pData->setAbscissa("Bugs");

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
        $pData->addPoints($this->query->data['data'], 'Counts');
        $pData->setAxisName(0, 'Bugs');
        $pData->addPoints($this->query->data['x_labels'], "Bugs");
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
