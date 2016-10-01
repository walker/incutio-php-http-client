<?php

/* Version 0.9, 6th April 2003 - Simon Willison ( http://simon.incutio.com/ )
    Further Information: https://github.com/walker/Incutio-PHP-HTTP-Client
    Historical Details: http://scripts.incutio.com/httpclient/
    Incutio Ltd - www.incutio.com

* Version 0.9d, 4th, April 2012 Walker Hamilton (http://walkerhamilton.com)
    - Added PUT & DELETE request types, reused postdata
    - Added in proper header setting for some of the items that were being set automatically or were only being set on POST
*/

class HttpClient {

    /*

    HttpClient is a client class for the HTTP protocol.

    It can be used to interact with another web server from within a PHP script.

    As well as retrieving information from a server, HttpClient can interact
    with a server via POST or GET. It can therefore be used as part of any
    script that needs to communicate with an application running on another site.

    It supports basic authentication, and persistent cookies (and thus sessions).

    */

    // * Request vars:

    protected $host, $port, $path;
    protected $scheme;
    protected $method;
    protected $postdata = '';
    protected $cookies = array();
    protected $referer;
    protected $accept = 'text/xml,application/xml,application/xhtml+xml,text/html,text/plain,image/png,image/jpeg,image/gif,*/*';
    protected $accept_encoding = 'gzip';
    protected $accept_language = 'en-us';
    protected $user_agent = 'Incutio HttpClient v0.9d';
    protected $request_headers = array();

    // * Options:

    protected $timeout = 20;
    protected $use_gzip = true;
    protected $persist_cookies = true;
    protected $persist_referers = false;
    protected $debug = false;
    protected $handle_redirects = true;
    protected $max_redirects = 5;
    protected $headers_only = false;
    protected $strict_redirects = false;

    // * Basic authorization variables:

    protected $username, $password;

    // * Response vars:

    protected $status;
    protected $headers = array();
    protected $content = '';
    protected $errormsg;

    // * Tracker variables:

    protected $redirect_count = 0;

    // --- Constructor / destructor:

    public function __construct($host, $port=80) {
        /*
        
        $host: the web server host (for example, 'scripts.incutio.com')
        $port: optional port number
        
        */

        $bits   = parse_url($host);
        if(isset($bits['scheme']) && isset($bits['host'])) {
            $host   = $bits['host'];
            $scheme = isset($bits['scheme']) ? $bits['scheme'] : 'http';
            $port   = isset($bits['port']) ? $bits['port'] : 80;
            $path   = isset($bits['path']) ? $bits['path'] : '/';
            
            if (isset($bits['query']))
                $path .= '?'.$bits['query'];
        }
        $this->host = $host;
        $this->port = $port;
        if(isset($bits['scheme']) && isset($bits['host'])) {
            $this->setScheme($scheme);
            $this->setPath($path);
            $this->setMethod("GET");
        }
    }

    public function __destruct() {
        foreach ($this as $index => $value) unset($this->$index);
    }

    public function __toString() {
        return $this->getContent();
    }

    // --- Query execution methods:

    public function get($path, $data = null, $headers=array()) {

        /*

        Executes a GET request for the specified path.

        Returns true on success and false on failure. If false, an error
        message describing the problem encountered can be accessed
        using the getError() method.

        $data: optional - if specified, appends it to a query string as part of
               the get request. $data can be an array of key value pairs, in
               which case a matching query string will be constructed.

        */

        $this->orig_path = $this->path;
        if(!empty($this->path))
            $this->path .= $path;
        else
            $this->path = $path;
        $this->method = 'GET';
        if ($data) $this->path .= '?'.http_build_query($data);
        $this->setRequestHeaders($headers);
        $result = $this->doRequest();
        $this->path = $this->orig_path;

        $this->postdata = null;
        return $result;
    }

    public function post($path, $data, $headers=array()) {

        /*

        Executes a POST request to the specified path, sending the information
        specified in $data.

        Returns true on success or false on failure. If false, an error
        message describing the problem encountered can be accessed
        using the getError() method.

        $data: optional - an array of key value pairs, in which case a matching
               post request will be constructed.

        */

        $orig_path = $this->path;
        if(!empty($this->path))
            $this->path .= $path;
        else
            $this->path = $path;
        $this->method = 'POST';
        $this->setRequestHeaders($headers);
        $this->buildQuery($data);
        $result = $this->doRequest();
        $this->path = $orig_path;

        $this->postdata = null;
        return $result;
    }

    public function put($path, $data, $headers=array()) {

        /*

        Executes a PUT request to the specified path, sending the information
        specified in $data.

        Returns true on success or false on failure. If false, an error
        message describing the problem encountered can be accessed
        using the getError() method.

        $data: optional - an array of key value pairs, in which case a matching
               post request will be constructed.

        */

        $orig_path = $this->path;
        if(!empty($this->path))
            $this->path .= $path;
        else
            $this->path = $path;
        $this->method = 'PUT';
        $this->setRequestHeaders($headers);
        $this->buildQuery($data);
        $result = $this->doRequest();
        $this->path = $orig_path;

        $this->postdata = null;
        return $result;
    }

    public function delete($path, $data, $headers=array()) {

        /*

        Executes a DELETE request to the specified path, sending the information
        specified in $data.

        Returns true on success or false on failure. If false, an error
        message describing the problem encountered can be accessed
        using the getError() method.

        */
        
        $orig_path = $this->path;
        if(!empty($this->path))
            $this->path .= $path;
        else
            $this->path = $path;
        $this->method = 'DEL'.'ETE';
        $this->setRequestHeaders($headers);
        $this->buildQuery($data);
        $result = $this->doRequest();
        $this->path = $orig_path;

        $this->postdata = null;
        return $result;
    }

    public function buildQuery($data) {
        if(is_string($data)) {
            $this->postdata = $data;
            return true;
        } else if(is_object($data) || is_array($data)){
            $this->postdata = http_build_query($data);
            return true;
        } else {
            trigger_error("HttpClient::postdata : '".gettype($data)."' is not valid post data.", E_USER_ERROR);
            return false;
        }
    }

    public function ok() {
        // Use this after get() or post() to check the status of the last request.
        // Returns true if the status was 200 OK - otherwise returns false.
        return ($this->status == 200);
    }

    // --- Response accessors:

    public function getContent() {
        // Returns the content of the HTTP response. This is usually an HTML document.
        return $this->content;
    }

    public function getStatus() {
        // Returns the status code of the response - 200 means OK, 404 means file not found, etc.
        return $this->status;
    }

    public function getHeaders() {
        // Returns the HTTP headers returned by the server as an associative array.
        return $this->headers;
    }

    public function getHeader($header) {
        // Returns the specified response header, or false if it does not exist.
        $header = strtolower($header);
        if (isset($this->headers[$header])) {
            return $this->headers[$header];
        } else {
            return false;
        }
    }

    public function getError() {
        // Returns a string describing the most recent error.
        return $this->errormsg;
    }

    public function getCookies($host = null) {

        /*

        Returns an array of cookies set by the server, for the current host,
        or (optionally) for a different host.

        May return null, if no cookies have been set.

        $host: optional - specifies a different host for which to retrieve
               current cookies. Defaults to using the current host.

        */

        return @$this->cookies[$host ? $host : $this->host];

    }

    public function getRequestURL() {
        // Returns the full URL that has been requested.
        $url = $this->scheme.'://'.$this->host;
        if ($this->port != 80 && $this->scheme != 'https' || ($this->port != 443 && $this->scheme == 'https')) {        
            $url .= ':'.$this->port;
        }
        $url .= $this->path;
        return $url;
    }

    public function getPath() {
        // Returns the requested path (not a complete URL).
        return $this->path;
    }

    // --- Configuration methods:

    public function setReferer($string)
    {
        $this->referer = $string;
    }

    public function setUserAgent($string) {
        // Sets the user agent string to be used in the request.
        // Default is "Incutio HttpClient v$version".
        $this->user_agent = $string;
    }

    public function setAuthorization($username, $password) {
        // Sets the HTTP authorization username and password to be used in requests.
        // Warning: don't forget to unset this in subsequent requests to other servers!
        $this->username = $username;
        $this->password = $password;
    }

    public function setCookies($array, $replace = false) {

        /*

        Adds/overwrites or replace cookies to be sent in the request.

        $array: an associative array containing name-value pairs.

        $replace: optional, defaults to false - if true, erases all existing
                  cookies, otherwise adds new (and overwrites existing) cookies.

        */

        if ($replace || !is_array(@$this->cookies[$this->host]))
            $this->cookies[$this->host] = array();

        $this->cookies[$this->host] = ( $array + $this->cookies[$this->host] );

    }

    public function useGzip($boolean) {
        // Specify if the client should request gzip encoded content from the server -
        // this saves bandwidth, but can increase processor time. Enabled by default.
        $this->use_gzip = $boolean;
    }

    public function setPersistCookies($boolean) {

        /*

        Specify if the client should persist cookies between requests.
        Enabled by default.

        Note: This currently ignores the cookie path (and time) completely.
        Time is not important, but path could possibly lead to security problems.

        */

        $this->persist_cookies = $boolean;

    }

    public function setPersistReferers($boolean) {
        // Specify if the client should use the URL of the previous request as the
        // referral of a subsequent request. Enabled by default.
        $this->persist_referers = $boolean;
    }

    public function setHandleRedirects($boolean) {
        // Specify if the client should automatically follow redirected requests.
        // Enabled by default.
        $this->handle_redirects = $boolean;
    }

    public function setStrictRedirects($boolean) {
        // Specify if the client should check for a 301, 302, or 307 status code before handling a redirect.
        // Disabled default.
        $this->strict_redirects = $boolean;
    }

    public function setMaxRedirects($num) {
        // Set the maximum number of redirects allowed before the client
        // gives up (mainly to prevent infinite loops). Defaults to 5 redirects.
        $this->max_redirects = $num;
    }

    public function setHeadersOnly($boolean) {
        // If enabled, the client will only retrieve the headers from a page.
        // This could be useful for implementing things like link checkers.
        // Disabled by default.
        $this->headers_only = $boolean;
    }

    public function setRequestHeaders($array) {
        foreach($array as $key => $value) {
            $this->request_headers[$key] = $value;
        }
    }

    public function setDebug($boolean) {
        // Enables debugging messages in HTML output from the client.
        // Disabled by default.
        $this->debug = $boolean;
    }

    public function setPath($path) {
        // Manually set the path (not usually needed).
        $this->path = $path;
    }

    public function setScheme($scheme) {
        // Manually set the path (not usually needed).
        switch($scheme) {
            case 'https':
                $this->scheme = $scheme;
                $this->port = 443;
                break;
            case 'http':
            default:
                $this->scheme = 'http';
        }
    }

    public function setMethod($method) {
        // Manually set the request method (not usually needed).
        if (!in_array($method, array("GET","POST","PUT","DEL"."ETE"))) trigger_error("HttpClient::setMethod() : '$method' is not a valid method", E_USER_ERROR);
        $this->method = $method;
    }

    // --- Static utility methods:

    public static function quickGet($url, $data = null) {

        /*

        Static shortcut method to quickly create and configure a new
        instance of this class, and perform a GET query.

        Thanks to string-magic, you can use the return value from this
        method directly in an echo statement, if you like.

        */

        $client = self::create($url);
        $client->get($client->getPath(), $data);
        return $client;

    }

    public static function quickPost($url, $data) {

        // Similar to [HttpClient::quickGet()], but performs a POST query.

        $client = self::create($url);
        $client->post($client->getPath(), $data);
        return $client;

    }

    static function create($url) {

        /*

        Static shortcut method to create and configure a new instance
        of this class. Query is not automatically performed, but the
        class is preconfigured so you can call doRequest() after
        setting whatever other parameters you might need.

        (If you don't need to set any other parameters, you most likely
        want one of the quickGet() or quickPost() methods instead.)

        */

        return new HttpClient($url);

    }

    // --- Internal helper methods:

    protected function debug($msg, $object = false) {

        /*

        Displays an internal message for debugging and troubleshooting,
        if debugging is enabled.

        Use [HttpClient::setDebug()] to enable debugging.

        */

        if ($this->debug) {

            echo '<div style="border: 1px solid red; padding: 0.5em; margin: 0.5em;"><strong>HttpClient Debug:</strong> ' . $msg;

            if ($object)
                echo '<pre>' . htmlspecialchars(print_r($object,true)) . '</pre>';

            echo '</div>';

        }

    }

    public function doRequest() {

        // Performs the actual HTTP request, returning true on success, false on error.
        // (You do not usually need to call this manually)
        
        if($this->scheme=='https') {
            $host = 'ssl://'.$this->host;
            $this->port = 443;
        } else {
            $host = $this->host;
        }
        
        if($this->debug) {
            echo '<h2>Host</h2>';
            echo '<pre>';
            var_dump($host);
            echo '</pre><br />';
            if(!empty($this->port)) {
                echo '<h2>Port Used</h2>';
                echo '<pre>';
                var_dump($this->port);
                echo '</pre><br />';
            }
        }
        if (!$fp = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout)) {
            // * Set error message:
            switch($errno) {
                case -3:
                    $this->errormsg = 'Socket creation failed (-3)';
                case -4:
                    $this->errormsg = 'DNS lookup failure (-4)';
                case -5:
                    $this->errormsg = 'Connection refused or timed out (-5)';
                default:
                    $this->errormsg = 'Connection failed ('.$errno.')';
                    $this->errormsg .= ' '.$errstr;
                    $this->debug($this->errormsg);
            }
            return false;
        }

        socket_set_timeout($fp, $this->timeout);

        $request = $this->buildRequest();
        if($this->debug) {
            echo '<h2>Built Request</h2>';
            echo '<pre>';
            var_dump($request);
            echo '</pre><br />';
        }
        $this->debug('Request', $request);
        fwrite($fp, $request);

        // * Reset all the variables that should not persist between requests:

        $this->request_headers = array();
        $this->headers = array();
        $this->content = '';
        $this->errormsg = '';

        // * Set a couple of flags:

        $inHeaders = true;
        $atStart = true;

        // * Now start reading back the response:

        while (!feof($fp)) {

            $line = fgets($fp, 4096);

            if ($atStart) {

                // * Deal with first line of returned data:

                $atStart = false;

                if (!preg_match('/HTTP\/(\\d\\.\\d)\\s*(\\d+)\\s*(.*)/', $line, $m)) {
                    $this->errormsg = "Status code line invalid: ".htmlentities($line);
                    $this->debug($this->errormsg);
                    return false;
                }

                $http_version = $m[1]; // * not used
                $this->status = $m[2];
                $status_string = $m[3]; // * not used

                $this->debug(trim($line));

                continue;

            }

            if ($inHeaders) {

                if (trim($line) == '') {
                    $inHeaders = false;
                    $this->debug('Received Headers', $this->headers);
                    if ($this->headers_only) {
                        break; // * Skip the rest of the input
                    }
                    continue;
                }

                if (!preg_match('/([^:]+):\\s*(.*)/', $line, $m)) {
                    // * Skip to the next header:
                    continue;
                }

                $key = strtolower(trim($m[1]));
                $val = trim($m[2]);

                // * Deal with the possibility of multiple headers of same name:

                if (isset($this->headers[$key])) {
                    if (is_array($this->headers[$key])) {
                        $this->headers[$key][] = $val;
                    } else {
                        $this->headers[$key] = array($this->headers[$key], $val);
                    }
                } else {
                    $this->headers[$key] = $val;
                }

                continue;

            }

            // * We're not in the headers, so append the line to the contents:

            $this->content .= $line;

        }

        fclose($fp);

        // * If data is compressed, uncompress it:

        if (isset($this->headers['content-encoding']) && $this->headers['content-encoding'] == 'gzip') {
            $this->debug('Content is gzip encoded, unzipping it');
            $this->content = substr($this->content, 10); // * See http://www.php.net/manual/en/function.gzencode.php
            $this->content = gzinflate($this->content);
        }
        $this->debug('Content', $this->content);

        // * If $persist_cookies, deal with any cookies:

        if ($this->persist_cookies && isset($this->headers['set-cookie'])) {

            $cookies = $this->headers['set-cookie'];

            if (!is_array($cookies))
                $cookies = array($cookies);

            if (!is_array(@$this->cookies[$this->host]))
                $this->cookies[$this->host] = array();

            foreach ($cookies as $cookie) {
                if (preg_match('/([^=]+)=([^;]+);/', $cookie, $m)) {
                    $this->cookies[$this->host][$m[1]] = $m[2];
                }
            }

        }

        // * If $persist_referers, set the referer ready for the next request:

        if ($this->persist_referers) {
            $this->debug('Persisting referer: '.$this->getRequestURL());
            $this->referer = $this->getRequestURL();
        }

        // * Finally, if handle_redirects and a redirect is sent, do that:
        $redirect_status_codes = array(301, 302, 307);
        if ($this->handle_redirects) {
            if(!$this->strict_redirects || in_array($this->status, $redirect_status_codes)) {
                if (++$this->redirect_count >= $this->max_redirects) {
                    $this->errormsg = 'Number of redirects exceeded maximum ('.$this->max_redirects.')';
                    $this->debug($this->errormsg);
                    $this->redirect_count = 0;
                    return false;
                }

                $location = isset($this->headers['location']) ? $this->headers['location'] : '';
                $location .= isset($this->headers['uri']) ? $this->headers['uri'] : '';
                if ($location) {
                    $this->debug("Following redirect to: $location" . (@$url['host'] ? ", host: ".$url['host'] : ''));
                    $url = parse_url($location);
                    if (@$url['scheme']) $this->scheme = $url['scheme'];
                    if (@$url['host']) $this->host = $url['host'];
                    return $this->get(($url['path']{0} == '/' ? '' : '/') . $url['path']);
                }
            }
        }

        return true;
    }

    protected function buildRequest() {

        // Constructs the headers of the HTTP request.

        $headers = array();
        $headers[] = "{$this->method} {$this->path} HTTP/1.0"; // * Using 1.1 leads to all manner of problems, such as "chunked" encoding
        $headers[] = "Host: {$this->host}";

        if ($this->referer)
            $headers[] = "Referer: {$this->referer}";

        // * Cookies:

        if (@$this->cookies[$this->host]) {
            $cookie = 'Cookie: ';
            foreach ($this->cookies[$this->host] as $key => $value) {
                $cookie .= "$key=$value; ";
            }
            $headers[] = $cookie;
        }

        // * Basic authentication:

        if ($this->username && $this->password)
            $headers[] = 'Authorization: BASIC '.base64_encode($this->username.':'.$this->password);

        // * If this is a POST, set the content type and length:


        if(!empty($this->request_headers)) {
            foreach($this->request_headers as $key => $val) {
                if($val===false) {
                    // do nothing
                } else {
                    $headers[] = $key.': '.$val;
                }
            }
        }



        if ($this->use_gzip && !isset($this->request_headers['Accept-encoding']))
            $headers[] = "Accept-encoding: {$this->accept_encoding}";

        // If it is a POST, add Content-Type.
        if (!isset($this->request_headers['Content-Type']) &&
            $this->method == 'POST') {
            $headers[] = "Content-Type: application/x-www-form-urlencoded";
        }

        if (!isset($this->request_headers['User-Agent']))
            $headers[] = "User-Agent: {$this->user_agent}";
        if (!isset($this->request_headers['Accept']))
            $headers[] = "Accept: {$this->accept}";
        if (!isset($this->request_headers['Accept-language']))
            $headers[] = "Accept-language: {$this->accept_language}";
        if ($this->postdata && !isset($this->request_headers['Content-Length'])) {
            $headers[] = 'Content-Length: '.strlen($this->postdata);
        }

        $request = implode("\r\n", $headers)."\r\n\r\n".$this->postdata;



        return $request;

    }

}
