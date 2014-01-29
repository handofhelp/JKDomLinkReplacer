<?php
/**
 *  This class is able to parse an html document and replace
 *  all links adding a extra GET parameter, like the example below
 *  we will add "mobileapp=1" to all tags with href and action= attributes
 *  @example
 *     $relreplacer = new JKDomLinkReplacer(array(
 *        'html'=>$html,
 *      'domains'=> array(
 *           'http://foobar.com',
 *          'http://example.com',
 *          'https://example.org'
 *          ),
 *      'append_get'=>'mobileapp=1',
 *      'allow_relative_urls'=>true,
 *  ));
 * $relreplacer->replaceAll();
 * echo $relreplacer->getHtml();
 * 
 * @license MIT|Public Domain
 * @see github.com/handofhelp
 *
 * Released both under Public Domain (use however you wish, or split apart) and MIT 
 * License below, thus meaning you may remove all comments if you wish. 
 * Thanks, Hand of Help
 * Below is the MIT License if you would prefer to use it under that
 *  
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 Hand of Help Mission handofhelp.com 
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
 
class JKDomLinkReplacer {
    private $opts = array();

    private $xpath = null;
    private $doc = null;
    private $html = '';
    private $last_elements_fetched = null;
 
    private $replace_attributes = array('href', 'action');
    private $replace_attributes_xpath = '//*[@href | @action]';

    public function getDomDoc(){
        return $this->doc;
    }

    public function __construct($opts = null){
         if (!is_array($opts)){ 
            $opts = array(); 
         }
         $this->opts = $opts;

         
         $this->opts['domains'] = (array) @$opts['domains'];
         $this->opts['append_get'] = (string) @$opts['append_get'];
         $this->opts['allow_relative_urls'] = (bool) @$opts['allow_relative_urls'];

         $html = @$opts['html'];
   
        //automatically load domdoc and parseand xpath 
        if (!empty($html)){
            $this->load_dom($html);
            $this->load_xpath($this->doc);
        }
    }

    public function load_dom($html){
        $this->html = $html;
        $this->doc = new DOMDocument();
        $this->doc->loadHTML($html);
        return $this->doc;
    }

    public function load_xpath($doc){
         $this->xpath = new DOMXpath($doc);
         return $this->xpath;
    }

    public function fetch_replacement_elements(){
        $this->last_elements_fetched =
             $this->xpath->query($this->replace_attributes_xpath);
        return $this->last_elements_fetched;
    }

    /**
     * Iterate through all found dom elements and
     * make replacements as specified in constructor
     */
    public function replaceAll($elements = null) {
        if (empty($elements)){
            $elements = $this->fetch_replacement_elements();
        }
        
        if (is_null($elements)){
            return 0;
        }

        $num_replacements = 0;
        foreach ($elements as $element) {
            //TODO: check nodeTag
            foreach($this->replace_attributes as $i => $attr){
                if ($element->hasAttribute($attr)){
                    $url = $element->getAttribute($attr);

                    //crazy where a <form action does not even use get string if form method is GET
                    //thats why we are inserting a hidden form element at the bottom.
                    if ($attr == 'action' && strtoupper($element->getAttribute('method')) == 'GET'){
                        //parse append_get option and figure out the correct way to modify the dom
                        parse_str ( $this->opts['append_get'], $appendGET );
                        foreach($appendGET as $key => $val){
                            $nw_input = $this->doc->createElement('input');
                            $nw_input->setAttribute('type', 'hidden');
                            $nw_input->setAttribute('name', $key);
                            $nw_input->setAttribute('value', $val);
                            $element->appendChild($nw_input);
                        }
                    }
                    else{
                        $newurl = $this->replaceUrl($url);
                        if ($newurl !== false && $newurl !== $url){
                           $element->setAttribute($attr, $newurl);
                           //1 replacement made on this element, done!
                           //one attribute was replace, <a href will never have <a src also,
                           //just like <form will never have <form href and action 
                           //go on to next element
                           $num_replacements++;
                           break; 
                        }
                    }
                }
            }//end iterating through all attributes needed to be replaced
        }//end iterating through all found elements
        return $num_replacements;
    }

    public function getHtml(){
        return $this->doc->saveHtml();
    }

    public function replaceUrl($url){
        $sp_http = strpos($url, 'http://');
        $sp_https = strpos($url, 'https://');
        $has_full_url = ($sp_http !== false || $sp_https !== false);

        if (!$this->opts['allow_relative_urls'] && !$has_full_url){
             //the url itself is invalid and doesnt contain http:// or https://
             return false;
        }


        //ensure only specific domains are replaced
        if ($has_full_url){
            $in_whitelist = false;
            foreach($this->opts['domains'] as $whitelisted_domain){
                if (stripos($url, $whitelisted_domain) === 0){
                    $in_whitelist = true;
                    break;
                }
            }
            if (!$in_whitelist){
                return false;
            }
        }

        if (strpos($url, '?') !== false){
            return $url .= '&'.$this->opts['append_get'];
        } else {
            return $url .= '?'.$this->opts['append_get'];
        }
        return $url;
    }
}
