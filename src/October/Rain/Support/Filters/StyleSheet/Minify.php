<?php namespace October\Rain\Support\Filters;

use Assetic\Asset\AssetInterface;
use Assetic\Filter\FilterInterface;

/**
 * Minify CSS Filter
 * Class used to compress stylesheet css files.
 *
 * @package october/combiner
 * @author Alexey Bobkov, Samuel Georges
 */
class StyleSheet_Minify implements FilterInterface
{

    public function filterLoad(AssetInterface $asset)
    {
    }

    public function filterDump(AssetInterface $asset)
    {
        $asset->setContent($this->minify($asset->getContent()));
    }

    /**
     * Minifies CSS
     * @var $css string CSS code to minify.
     * @return string Minified CSS.
     */
    protected function minify($css)
    {
        $css = preg_replace('#\s+#', ' ', $css);
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        $css = str_replace('; ', ';', $css);
        $css = str_replace(': ', ':', $css);
        $css = str_replace(' {', '{', $css);
        $css = str_replace('{ ', '{', $css);
        $css = str_replace(', ', ',', $css);
        $css = str_replace('} ', '}', $css);
        $css = str_replace(';}', '}', $css);

        return trim($css);
    }
}