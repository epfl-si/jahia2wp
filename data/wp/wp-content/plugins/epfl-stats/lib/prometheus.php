<?PHP

    /* Exception */
    require_once __DIR__."/Prometheus/Exception/MetricNotFoundException.php";
    require_once __DIR__."/Prometheus/Exception/MetricsRegistrationException.php";
    require_once __DIR__."/Prometheus/Exception/MetricNotFoundException.php";

    /* Storage */
    require_once __DIR__."/Prometheus/Storage/Adapter.php";
    require_once __DIR__."/Prometheus/Storage/APC.php";

    /* Base */
    require_once __DIR__."/Prometheus/Collector.php";
    require_once __DIR__."/Prometheus/CollectorRegistry.php";
    require_once __DIR__."/Prometheus/Counter.php";
    require_once __DIR__."/Prometheus/Gauge.php";
    require_once __DIR__."/Prometheus/Histogram.php";
    require_once __DIR__."/Prometheus/MetricFamilySamples.php";
    require_once __DIR__."/Prometheus/PushGateway.php";
    require_once __DIR__."/Prometheus/RenderTextFormat.php";
    require_once __DIR__."/Prometheus/Sample.php";


/*
 * Custom definition of class Prometheus\RenderTextFormat to be able to display results as we want
 */
class EPFLRenderTextFormat
{
    const MIME_TYPE = 'text/plain; version=0.0.4';

    /**
     * @param Prometheus\MetricFamilySamples[] $metrics
     * @param array $moveToEnd -> List of labels to not put between { } but to add at the end, after 'value'
     * @return string
     */
    public function render(array $metrics, array $moveToEnd)
    {

        usort($metrics, function(Prometheus\MetricFamilySamples $a, Prometheus\MetricFamilySamples $b)
        {
            return strcmp($a->getName(), $b->getName());
        });

        $lines = array();

        foreach ($metrics as $metric) {
            $lines[] = "# HELP " . $metric->getName() . " {$metric->getHelp()}";
            $lines[] = "# TYPE " . $metric->getName() . " {$metric->getType()}";
            foreach ($metric->getSamples() as $sample) {
                $lines[] = $this->renderSample($metric, $sample, $moveToEnd);
            }
        }
        return implode("\n", $lines) . "\n";
    }


    /**
     * @param Prometheus\MetricFamilySamples[] $metric
     * @param Prometheus\Sample[] $sample
     * @param array $moveToEnd -> List of labels to not put between { } but to add at the end, after 'value'
     * @return string
     */
    private function renderSample(Prometheus\MetricFamilySamples $metric, Prometheus\Sample $sample, array $moveToEnd)
    {
        $escapedLabels = array();

        $allLabels = $metric->getLabelNames();
        /* Removing labels we have to add at the end */
        $labelNames = array_diff($allLabels, $moveToEnd);

        if ($metric->hasLabelNames() || $sample->hasLabelNames()) {
            $labels = array_combine(array_merge($allLabels, $sample->getLabelNames()), $sample->getLabelValues());
            foreach ($labels as $labelName => $labelValue) {

                if(!in_array($labelName, $labelNames))continue;
                $escapedLabels[] = $labelName . '="' . $this->escapeLabelValue($labelValue) . '"';
            }
            $result = $sample->getName() . '{' . implode(',', $escapedLabels) . '}';
        }
        else
        {
            $result = $sample->getName();
        }

        $result .= ' '.$sample->getValue();

        /* Adding values we have to add at the end */
        foreach($moveToEnd as $endLabel)
        {
            $result .= ' '.$this->escapeLabelValue($labels[$endLabel]);
        }

        return $result;
    }

    private function escapeLabelValue($v)
    {
        $v = str_replace("\\", "\\\\", $v);
        $v = str_replace("\n", "\\n", $v);
        $v = str_replace("\"", "\\\"", $v);
        return $v;
    }

}