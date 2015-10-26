<?php

namespace AffiliateLinkProcessor;

/**
 *
 * Config is a nested array.  Top level array is the domain of the affiliate host.  
 * Inside the array element for the affiliate host is data associated with that host.
 * 
 */

class Processor {
    private $config;
    public function __construct($__config) {
        $config = $__config;
    }
    
    /**
     * The processXyxxyURL functions take the signature ($url, $urlParts) and produce a new $url string.
     * These functions are to determine if they can/should modify the $url before doing so, and if
     * they should not then simply return $url.
     * These functions are to expect :-  $urlParts = parse_url($url);
     */
    
    public function process($url) {
        $urlParts = parse_url($url);
        // For certain kinds of URL's do not process
        if (!$urlParts
          || empty($urlParts['host'])
          || !empty($urlParts['fragment'])
          || ($urlParts['scheme'] !== 'http' && $urlParts['scheme'] !== 'https')) {
            return $url;
        }
        if ($this->domainEndsWith($urlParts['host'], 'amazon.com')) return $this->processAmazonCOMURL($url, $urlParts);
        if ($this->isRakutenDomain($urlParts['host'])) return $this->processRakutenURL($url, $urlParts);
        if ($this->domainEndsWith($urlParts['host'], 'zazzle.com')) return $this->processZazzleURL($url, $urlParts);
        
        // Didn't match anything
        return $url;
    }
    
    /**
     * According to the FAQ on amazon.com's affiliate program, you can add tag=tracking-code to any
     * amazon.{com,.co.uk,.etc} URL.
     * 
     * This will modify the $urlParts['query'] string if appropriate
     */
    private function processAnyAmazonProperty(&$urlParts, $trackingCode) {
        if (empty($urlParts['query'])) {
            $urlParts['query'] = 'tag='. $trackingCode;
        } else if (strpos($urlParts['query'], '&tag=') === FALSE) {
            $urlParts['query'] = $urlParts['query'] .'&tag='.  $trackingCode;
        }
    }
    
    /**
     * $config['amazon.com'] => array(
     *    'tracking-code' => 'blah-de-blah-20'
     * )
     */
    private function processAmazonCOMURL($url, $urlParts) {
        if (array_key_exists($this->config, 'amazon.com') && !empty($this->config['amazon.com'])) {
            $this->processAnyAmazonProperty($urlParts, $this->config['amazon.com']['tracking-code']);
            $newhref = $this->unparse_url($urlParts);
            return $newhref;
        } else {
            return $url;
        }
    }
    
    /**
     * $config['RAKUTEN'] => array(
     *     'affiliate-code' => 'foobar',
     *     'programs' => array(
     *          'domain1.com' => array(
     *                  'mid' => 'mid-string'
     *          ),
     *          'domain2.com' => array(
     *                  'mid' => 'mid-string'
     *          ),
     *          ...
     *     )
     * )
     */
     
    private function isRakutenDomain($host) {
        if (!array_key_exists($this->config, 'RAKUTEN')
         || empty($this->config['RAKUTEN'])) {
            return FALSE;
        } else {
            $progs = array_keys($this->config['RAKUTEN']['programs']);
            foreach ($progs as $prog) {
                if ($this->domainEndsWith($host, $prog)) return TRUE;
            }
            return FALSE;
        }
    }
    
    private function processRakutenURL($url, $urlParts) {
        // Use the same check as in isRakutenDomain
        if (!array_key_exists($this->config, 'RAKUTEN')
         || empty($this->config['RAKUTEN'])) {
            return $url;
        }
        
        foreach ($this->config['RAKUTEN']['programs'] as $prog => $progdata) {
            if ($this->domainEndsWith($urlParts['host'], $prog)) {
                $mid = $progdata['mid'];
                $affID = $this->config['RAKUTEN']['affiliate-code'];
                $urlenc = urlencode($url);
                if ($this->domainEndsWith($urlParts['host'], "rakuten.com")) {
                    $afflinkbase = "http://affiliate.rakuten.com/";
                } else if ($this->domainEndsWith($urlParts['host'], "walmart.com")) {
                    $afflinkbase = "http://linksynergy.walmart.com/";
                } else {
                    $afflinkbase = "http://click.linksynergy.com/";
                }
                return "{$$afflinkbase}/deeplink?id={$affID}&mid={$mid}&murl={$urlenc}";
            }
        }
        return $url;
    }
    
    /**
     * $this->config['zazzle.com'] => array(
     *     'affliateID' => 'code'
     * )
     */
    private function processZazzleURL($url, $urlParts) {
        if (array_key_exists($this->config, 'zazzle.com') && !empty($this->config['zazzle.com'])) {
            if (empty($urlParts['query'])) {
                $urlParts['query'] = 'rf='. $this->config['zazzle.com']['affiliateID'];
            } else {
                $urlParts['query'] = $urlParts['query'] .'&rf='. $this->config['zazzle.com']['affiliateID'];
            }
            $newhref = $this->unparse_url($urlParts);
            return $newhref;
        } else {
            return $url;
        }
        
    }
    
    private function unparse_url($parsed_url) { 
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : ''; 
        $host     = isset($parsed_url['host']) ? $parsed_url['host'] : ''; 
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : ''; 
        $user     = isset($parsed_url['user']) ? $parsed_url['user'] : ''; 
        $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : ''; 
        $pass     = ($user || $pass) ? "$pass@" : ''; 
        $path     = isset($parsed_url['path']) ? $parsed_url['path'] : ''; 
        $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : ''; 
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : ''; 
        return "$scheme$user$pass$host$port$path$query$fragment"; 
    } 
    
    function domainEndsWith($haystack, $needle) {
        // search forward starting from end minus needle length characters
        return $needle === ""
          || (
              ($temp = strlen($haystack) - strlen($needle)) >= 0 
            && stripos($haystack, $needle, $temp) !== FALSE
          );
    }
}
