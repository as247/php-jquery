<?php
namespace PhpJquery;
use PhpJquery\Dom\AbstractNode;
use PhpJquery\Dom\HtmlNode;
use PhpJquery\Parser\TinyParser;
use PhpOffice\PhpSpreadsheet\Helper\Html;

/**
 * Class Content
 *
 * @package PhpJquery
 */
class Content
{
    /**
     * The charset we would like the output to be in.
     *
     * @var string
     */
    protected $defaultCharset = 'UTF-8';
    /**
     * The content string.
     *
     * @var string
     */
    protected $content;

    /**
     * A persistent option object to be used for all options in the
     * parsing of the file.
     *
     * @var Options
     */
    protected $options;
    /**
     * @var AbstractNode
     */
    protected $root;

    protected $parsed=false;
    /**
     * Content constructor.
     *
     * @param $content
     */
    public function __construct($content,$options){
        $this->options=$options;
        $this->parser=new TinyParser($options);
        $this->content=$this->clean($content);
        $this->parse();
    }

    /**
     * @param $selector
     * @param null $nth
     * @return HtmlNode|HtmlNode[]|AbstractNode|AbstractNode[]|Dom\Collection
     */
    public function find($selector,$nth=null){
        return $this->root->find($selector,$nth);
    }
    public function parse(){
        if(!$this->parsed) {
            $this->root = $this->parser->parse($this->content);
            $this->detectCharset();
        }
        $this->parsed=true;
        return $this;
    }
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->parser,$name],$arguments);
    }
    /**
     * Cleans the html of any none-html information.
     *
     * @param string $str
     * @return string
     */
    protected function clean($str)
    {
        if ($this->options->get('cleanupInput') != true) {
            // skip entire cleanup step
            return $str;
        }
        // remove white space before closing tags
        $str = preg_replace("#'\s+>#", "'>", $str);
        $str = preg_replace('#"\s+>#', '">', $str);
        // clean out the \n\r
        $replace = ' ';
        if ($this->options->get('preserveLineBreaks')) {
            $replace = '&#10;';
        }
        $str = str_replace(["\r\n", "\r", "\n"], $replace, $str);

        // strip the doctype
        $str = preg_replace("#<!doctype(.*?)>#i", '', $str);

        // strip out comments
        $str = preg_replace("#<!--(.*?)-->#i", '', $str);

        // strip out cdata
        $str = preg_replace("#<!\[CDATA\[(.*?)\]\]>#i", '', $str);

        // strip out <script> tags
        if ($this->options->get('removeScripts') == true) {
            $str = preg_replace("#<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>#i", '', $str);
            $str = preg_replace("#<\s*script\s*>(.*?)<\s*/\s*script\s*>#i", '', $str);
        }

        // strip out <style> tags
        if ($this->options->get('removeStyles') == true) {
            $str = preg_replace("#<\s*style[^>]*[^/]>(.*?)<\s*/\s*style\s*>#i", '', $str);
            $str = preg_replace("#<\s*style\s*>(.*?)<\s*/\s*style\s*>#i", '', $str);
        }

        // strip out server side scripts
        $str = preg_replace("#(<\?)(.*?)(\?>)#i", '', $str);

        // strip smarty scripts
        $str = preg_replace("#(\{\w)(.*?)(\})#i", '', $str);

        return $str;
    }
    /**
     * Attempts to detect the charset that the html was sent in.
     *
     * @return bool
     */
    protected function detectCharset()
    {
        // set the default
        $encode = new Encode;
        $encode->from($this->defaultCharset);
        $encode->to($this->defaultCharset);

        if ( ! is_null($this->options->enforceEncoding)) {
            //  they want to enforce the given encoding
            $encode->from($this->options->enforceEncoding);
            $encode->to($this->options->enforceEncoding);

            return false;
        }

        $meta = $this->root->find('meta[http-equiv=Content-Type]', 0);
        if (is_null($meta)) {
            // could not find meta tag
            $this->root->propagateEncoding($encode);

            return false;
        }
        $content = $meta->content;
        if (empty($content)) {
            // could not find content
            $this->root->propagateEncoding($encode);

            return false;
        }
        $matches = [];
        if (preg_match('/charset=(.+)/', $content, $matches)) {
            $encode->from(trim($matches[1]));
            $this->root->propagateEncoding($encode);

            return true;
        }

        // no charset found
        $this->root->propagateEncoding($encode);

        return false;
    }
}
