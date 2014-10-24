<?php
/**
 * class for parsing google search results
 * @license GNU LGPL Ver 3.0
 * @package google-alias
 * @author celend
 * @date 14-10-15
 */
if(!defined('QUOTE'))
    exit('Access Denied!');
class Google_search {

    private $paras = array();      //parameters
    private $headers = "";
    private $content = "";          //html content
    private $start = 0;             //results offset
    private $num = 10;
    private $url = 'https://www.google.com/search?';

    public $paras2 = array();
    public $key_word = '';
    public $ress = array();         //original results
    public $res_num = '';            //results total
    public $time = '';               //search time
    public $results = array();
    public $errno = 0;

    function __construct($key){
        $this->key_word = $key;
        $this->paras2[$GLOBALS['OPTIONS']['GET_Q']] = $key;
        $this->paras['hl'] = 'zh-CN';
        $this->paras['num'] = $this->num = $GLOBALS['OPTIONS']['NUM'];
        if($GLOBALS['OPTIONS']['SAFE_SEARCH'])
            $this->paras['safe'] = 'strict';
    }
    //load the html data
    public function load(){
        global $headers;
        $this->paras['q'] = $this->key_word;
        $this->paras[$GLOBALS['OPTIONS']['GET_Q']] = $this->key_word;
        $p = $this->arr2url($this->paras);
        $ch = curl_init($this->url.$p);
        curl_setopt_array($ch, $headers);
        $this->content = curl_exec($ch);
        $this->errno = curl_errno($ch);
        if($this->errno)
            return FALSE;
        if(HAVE_GZIP && $GLOBALS['OPTIONS']['ENABLE_GZIP'])
                $this->content = zlib_decode($this->content);
        $this->remove_css_and_js();
        preg_match('`<div id="resultStats"[^>]*>[^\d]*([\d,]*)[^<]*<nobr>[^\d]*([\d\.]*)[^<]*</nobr></div>`m', $this->content, $re);
        $this->res_num = $re[1];
        $this->time = $re[2];
        return $this;
    }

    /**
     * url paras convert into array key-value pairs
     * @param string
     * @return array
     */
    public static function url2arr($str){
        if(!is_string($str))
            return FALSE;
        parse_str($str, $f);
        return $f;
    }
    /**
     * array convert into url
     * @param $paras_arr
     * @return bool|string
     */
    public static function arr2url($paras_arr){
        if(!is_array($paras_arr))
            return FALSE;
        $s = '';
        foreach($paras_arr as $k => $v){
            $s .= urlencode($k).'='.urlencode($v).'&';
        }
        $s = substr($s, 0, count($s) - 2);
        return $s;
    }
    private function add_parse($key, $value){
        $this->paras[$key] = $value;
        return $this;
    }
    public function get_page(){
        return floor($this->start / $this->num) + 1;
    }
    private function remove_css_and_js(){
        $this->content = str_replace("\n", '', $this->content);
        $this->content = preg_replace('`<script[^>]*>.*?</script>`', '', $this->content);
        $this->content = preg_replace('`<style[^>]*>.*?</style>`', '', $this->content);
        return $this;
    }

    /**
     * @return array
     */
    function get_results(){
        if($this->errno)
            return FALSE;
        $c = 0;
        $s = array();
        while(TRUE){
            $s1 = stripos($this->content, '<li class="g"', $c);
            $s2 = stripos($this->content, '<li class="g"', $s1 + 15);
            if(!$s2){
                $e = substr($this->content, $s1);
                $s3 = strripos($e, '</li>') + 5;
                $s[] = substr($this->content, $s1, $s3);
                break;
            }
            $e  = substr($this->content, $s1, $s2);
            $s3 = strripos($e, '</li>') + 5;
            $s[] = substr($this->content, $s1, $s3);
            $c = $s2;
        }
        for($i = 0; $i < count($s); $i++){
            $id_reg = '@<li[^>]+class="g"[^>]?(?:id="([^"]*)")?[^>]*>@s';
            preg_match($id_reg, $s[$i], $r);
            $id = isset($r[1]) ? $r[1] : '';
            $href_reg = '@<h3[^>]+class="r"><a href="([^"]*)"[^>]*>(.*?)</a>@s';
            preg_match($href_reg, $s[$i], $r);
            $href = isset($r[1]) ? $r[1] : '';
            $tle  = isset($r[2]) ? $r[2] : '';
            $disc_reg = '@<span[^>]+class="st"[^>]*>((?:<span[^>]+class="f">.*?</span>)?.*?)</span>@s';
            preg_match($disc_reg, $s[$i], $r);
            $disc = isset($r[1]) ? $r[1] : '';
            $site_reg = '@<cite[^>]+class="_Rm[^"]*"[^>]*>(.*?)</cite>@s';
            preg_match($site_reg, $s[$i], $r);
            $site = isset($r[1]) ? $r[1] : '';
            $this->results[] = array('id' => $id, 'url' => $href, 'title' => $tle, 'info' => $disc, 'site' => $site);
        }
        return $this->results;
    }
    public function get_url(){
        return $this->url;
    }
    public function get_content(){
        return $this->content;
    }

    /**
     * set the search results before some time.
     * @param char
     * @return bool
     */
    public function set_time_limit($str){
        if(!is_string($str))
            return FALSE;
        $str = strtolower($str);
        if(!isset($this->paras['tbs'])){
            $this->paras['tbs'] = '';
            
        }
        else
            $this->paras['tbs'] .= ',';
        switch($str){
            //just now
            case 's':
                $this->paras['tbs'] .= 'qdr:s';
                $this->paras2[$GLOBALS['OPTIONS']['GET_TIME']] = $str;
                break;
            //few minutes ago
            case 'n':
                $this->paras['tbs'] .= 'qdr:n';
                $this->paras2[$GLOBALS['OPTIONS']['GET_TIME']] = $str;
                break;
            //half of hour ago
            case 't':
                $this->paras['tbs'] .= 'qdr:n30';
                $this->paras2[$GLOBALS['OPTIONS']['GET_TIME']] = $str;
                break;
            //half of day ago
            case 'j':
                $this->paras['tbs'] .= 'qdr:h12';
                $this->paras2[$GLOBALS['OPTIONS']['GET_TIME']] = $str;
                break;
            //a hour ago
            case 'h':
                $this->paras['tbs'] .= 'qdr:h';
                $this->paras2[$GLOBALS['OPTIONS']['GET_TIME']] = $str;
                break;
            //a day ago
            case 'd':
                $this->paras['tbs'] .= 'qdr:d';
                $this->paras2[$GLOBALS['OPTIONS']['GET_TIME']] = $str;
                break;
            //a weekend ago
            case 'w':
                $this->paras['tbs'] .= 'qdr:w';
                $this->paras2[$GLOBALS['OPTIONS']['GET_TIME']] = $str;
                break;
            //a month ago
            case 'm':
                $this->paras['tbs'] .= 'qdr:m';
                $this->paras2[$GLOBALS['OPTIONS']['GET_TIME']] = $str;
                break;
            //a year ago
            case 'y':
                $this->paras['tbs'] .= 'qdr:y';
                $this->paras2[$GLOBALS['OPTIONS']['GET_TIME']] = $str;
                break;
            default:
                return False;
        }
        return $this;
    }

    /**
     * set page number
     * @param int
     * @return this
     */
    public function set_page($num){
        $num--;
        $this->start = $num * $this->num;
        $this->paras['start'] = $this->start;
        return $this;
    }
    public function get_full_url(){
        return './?'.$this->arr2url($this->paras2);
    }
    public function get_url_withpage($num){
        $this->paras2[$GLOBALS['OPTIONS']['GET_PAGE']] = $num;
        return $this->get_full_url();
    }
    public function get_url_withparas($k, $v){
        $tmp = $this->paras2;
        $this->paras2[$k] = $v;
        $url = $this->get_full_url();
        $this->paras2 = $tmp;
        return $url;
    }
    public function set_num($num){
        $num = (int) $num;
        $this->num = $num;
        $this->paras['num'] = $num;
        $this->paras2[$GLOBALS['OPTIONS']['GET_NUM']] = $num;
        return $this;
    }
    public function set_keywork($key){
        $this->paras2[$GLOBALS['OPTIONS']['GET_Q']] = $key;
        $this->key_word = $key;
        return $this;
    }
}