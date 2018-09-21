<?php
namespace PhpJquery;

use PhpJquery\Dom\AbstractNode;
use PhpJquery\Dom\Collection;
use PhpJquery\Dom\HtmlNode;
use PhpJquery\Dom\TextNode;
use PhpJquery\Exceptions\NotLoadedException;
use PhpJquery\Exceptions\StrictException;
use PhpJquery\Encode;

/**
 * Class Dom
 *
 * @package PhpJquery
 */
class Dom
{
    /**
     * The raw version of the document string.
     *
     * @var string
     */
    protected $raw;

    /**
     * The document string.
     *
     * @var Content
     */
    protected $document = null;
    /**
     * A global options array to be used by all load calls.
     *
     * @var array
     */
    protected $globalOptions = [];

    /**
     * A persistent option object to be used for all options in the
     * parsing of the file.
     *
     * @var Options
     */
    protected $options;

    /**
     * Attempts to load the dom from any resource, string, file, or URL.
     *
     * @param string $str
     * @param array $options
     * @return $this
     */
    public function load($str, $options = [])
    {
        // check if it's a file
        if (strpos($str, "\n") === false && is_file($str)) {
            return $this->loadFromFile($str, $options);
        }
        // check if it's a url
        if (preg_match("/^https?:\/\//i", $str)) {
            return $this->loadFromUrl($str, $options);
        }

        return $this->loadStr($str, $options);
    }

    /**
     * Loads the dom from a document file/url
     *
     * @param string $file
     * @param array $options
     * @return $this
     */
    public function loadFromFile($file, $options = [])
    {
        return $this->loadStr(file_get_contents($file), $options);
    }

    /**
     * Use a curl interface implementation to attempt to load
     * the content from a url.
     *
     * @param string $url
     * @param array $options
     * @return $this
     */
    public function loadFromUrl($url, $options = [])
    {
        $content=file_get_contents($url);
        return $this->loadStr($content, $options);
    }

    /**
     * Parsers the html of the given string. Used for load(), loadFromFile(),
     * and loadFromUrl().
     *
     * @param string $str
     * @param array $option
     * @return $this
     */
    public function loadStr($str, $option)
    {
        $this->options = new Options;
        $this->options->setOptions($this->globalOptions)
                      ->setOptions($option);

        $this->raw     = $str;
        $this->document = new Content($str,$this->options);
        return $this;
    }

    /**
     * Sets a global options array to be used by all load calls.
     *
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options)
    {
        $this->globalOptions = $options;

        return $this;
    }

    /**
     * Find elements by css selector on the root node.
     *
     * @param string $selector
     * @param int $nth
     * @return array|Collection|HtmlNode
     */
    public function find($selector, $nth = null)
    {
        $this->isLoaded();
        return $this->document->find($selector, $nth);
    }
    public function document(){
        return $this->document;
    }

    public static function make($str,$options=[]){
        return (new static())->load($str,$options);
    }
    /**
     * Checks if the load methods have been called.
     *
     * @throws NotLoadedException
     */
    protected function isLoaded()
    {
        if (is_null($this->document)) {
            throw new NotLoadedException('Content is not loaded!');
        }
    }

}
