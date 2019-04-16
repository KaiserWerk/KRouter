<?php

class KRouter
{
    /**
     * @var string
     */
    private $baseClass = 'Controller';
    
    /**
     * Router constructor.
     *
     * @param string|null $baseClass
     */
    public function __construct($baseClass = null)
    {
        if ($baseClass != null) {
            $this->baseClass = $baseClass;
        }
    }
    
    /**
     * Does the actual routing/matching stuff
     */
    public function dispatch()
    {
        $url = $_SERVER['REQUEST_URI']; // this needs some rework since REQUEST_URI can be manipulated
    
        $routes = $this->getRoutes();
        #echo '<pre>';var_dump($routes);die;
        foreach ($routes as $route) {
            if ($route['url'] == $url) {
                if (!in_array($_SERVER['REQUEST_METHOD'], $route['httpMethods']) ||
                    substr_count($route['pattern'], '/') != substr_count($url, '/')) {
                    continue;
                }
                #die('URL: ' . $url . ', Pattern: ' . $route['url']);
                (new $route['class']())->{$route['method']}();
                die;
            }
        }
        foreach ($routes as $route) {
            if ($route['url'] == '/') {
                continue;
            }
            if (preg_match($route['pattern'], $url)) {
                if (!in_array($_SERVER['REQUEST_METHOD'], $route['httpMethods']) ||
                    substr_count($route['pattern'], '/') != substr_count($url, '/')) {
                    continue;
                }
                #die('URL: ' . $url . ', Pattern: ' . $route['pattern']);
                $parameters = $this->getRouteParameters($route['url']);
                (new $route['class']())->{$route['method']}($parameters);
                die;
            }
        }
        
        if (class_exists('ErrorController')) {
            $controller = new ErrorController();
            if (method_exists($controller, 'error404Action')) {
                $controller->error404Action();
            }
        } else {
            http_response_code(400);
            header("HTTP/1.0 400 KRouter: Bad Request");
            die("KRouter Error 400: Bad Request!");
        }
        die;
        
        #http_response_code(400);
        #header("HTTP/1.0 400 KRouter: Bad Request");
        #die("Error 404: Bad Request!");
        
        /*foreach ($this->getRoutes() as $route) {
            if (!in_array($_SERVER['REQUEST_METHOD'], $route['httpMethods'])) {
                http_response_code(404);
                header("HTTP/1.0 404 KRouter: Not Found");
                die("Error 404: Resource not found!");
            }
            
            if (substr_count($route['pattern'], '/') != substr_count($url, '/')) {
                http_response_code(400);
                header("HTTP/1.0 400 KRouter: Bad Request");
                die("Error 404: Bad Request!");
            }
            $parameters = $this->getRouteParameters($route['url']);
            if ($route['pattern'] == $url) {
                (new $route['class']())->{$route['method']}($parameters);
                die;
            }
            
            if (preg_match($route['pattern'], $url)) {
                (new $route['class']())->{$route['method']}($parameters);
                die;
            }
        }*/
    }
    
    /**
     * Returns the named parameters of a given route
     *
     * @param $definedRoute
     *
     * @return object
     */
    private function getRouteParameters($definedRoute)
    {
        $namedParameters = null;
        if (strpos($definedRoute, '[:') !== false) {
            $url = $_SERVER['REQUEST_URI'];
            $namedParameters = [];
            $parts = explode('/', $definedRoute);
            unset($parts[0]);
            $parts = array_values($parts);
            
            $parts2 = explode('/', $url);
            unset($parts2[0]);
            $parts2 = array_values($parts2);
            
            $count = count($parts);
            for ($i = 0; $i < $count; ++ $i) {
                $varName = null;
                if (preg_match('~^\[\:[a-z0-9]+\]$~', $parts[$i])) {
                    $varName = str_replace('[:', '', str_replace(']', '', $parts[$i]));
                    #$varName = preg_replace('~^\[\:[a-z0-9]+\]$~', '${1}', $parts[$i]);
                    $namedParameters[$varName] = $parts2[$i];
                }
            }
        }
        return (object)$namedParameters;
    }
    
    /**
     * Return all defined routes in Controller annotations
     *
     * @return array
     */
    public function getRoutes()
    {
        if (!class_exists($this->baseClass)) {
            die('The base class '.$this->baseClass.' is not defined.');
        }
        $aClasses    = get_declared_classes();
        $aChildrenOf = [];
        $routes      = [];
        try {
            $parentClass = new \ReflectionClass($this->baseClass);
        } catch (\ReflectionException $e) {
            die($e->getMessage());
        }
        foreach ($aClasses AS $class) {
            try {
                $rcCurClass = new \ReflectionClass($class);
            } catch (\ReflectionException $e) {
                die($e->getMessage());
            }
            if (strpos($rcCurClass->getName(), $this->baseClass) === false) {
                continue;
            }
            if ($rcCurClass->isSubclassOf($parentClass)) {
                $aChildrenOf[] = $rcCurClass;
                $methodList    = $rcCurClass->getMethods();
                unset($methodList[count($methodList) - 1]);
                foreach ($methodList as $item) {
                    $pattern = $this->parseDocBlock($item->getDocComment())['pattern'];
                    $routes[] = [
                        #'url' => ($pattern=='/') ? '' : $pattern,
                        'url' => $pattern,
                        'pattern' => '~' . preg_replace('~\[\:[a-z0-9]+\]~', '[a-z0-9-_.]+$', str_replace('/', '\/', $pattern)) . '~',
                        'name'   => $this->parseDocBlock($item->getDocComment())['name'],
                        'method' => $item->getName(),
                        'class'  => $rcCurClass->getName(),
                        'httpMethods'  => $this->parseDocBlock($item->getDocComment())['httpMethods'],
                    ];
                }
            }
        }
        if (empty($routes)) {
            die('There are no defined routes. Start by creating a controller class extending the Controller class.');
        }
        
        uasort($routes, function($a, $b){
            return substr_count($b['pattern'], '/') <=> substr_count($a['pattern'], '/') && strlen($b['pattern']) < strlen($a['pattern']);
        });
        
        return array_reverse(array_values($routes));
    }
    
    /**
     * Parses a DocBlock for @Route and @Method annotations and returns the corresponding values.
     *
     * @param $text
     *
     * @return array
     */
    public function parseDocBlock($text)
    {
        $routes = [];
        $_pattern = null;
        $_name = null;
        $_methods = null;
        $parts = preg_split("/\r\n|\n|\r/", $text);
        
        unset($parts[0]);
        $parts = array_values($parts);
        unset($parts[count($parts) - 1]);
        $parts = array_values($parts);
        
        foreach ($parts as $item) {
            if (substr($item, 0, 2) != '* @') {
                $item = str_replace('* @', '', $item);
                
                $blockdocParts = explode('(', $item);
                
                if (trim($blockdocParts[0]) == 'Route') {
                    if (strpos(trim($blockdocParts[1]), ',') !== false) {
                        $elements2 = explode(',', str_replace(')', '', trim($blockdocParts[1])));
                        $_pattern  = str_replace('"', '', trim($elements2[0]));
                        $_name     = str_replace('name=', '', str_replace('"', '', trim($elements2[1])));
                    } else {
                        $elements2 = str_replace(')', '', trim($blockdocParts[0]));
                        $_pattern = str_replace('"', '', $elements2);
                        $_name = null;
                    }
                }
                if (trim($blockdocParts[0]) == 'Method') {
                    $element = str_replace(')', '', trim($blockdocParts[1]));
                    $_methods = \json_decode($element, true);
                    #var_dump($_methods);die;
                }
                
                $routes = [
                    'pattern'  => $_pattern,
                    'name' => $_name,
                    'httpMethods' => $_methods,
                ];
            }
        }
        
        return $routes;
    }
}