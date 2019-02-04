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
     *
     * @throws ReflectionException
     */
    public function dispatch()
    {
        $url = $_SERVER['REQUEST_URI']; // this needs some rework since REQUEST_URI can be manipulated
        foreach ($this->getRoutes() as $route) {
            $pattern = '~' . preg_replace('~\[\:[a-z]+\]~', '[a-z0-9]+', str_replace('/', '\/', $route['url'])) . '~';
            if (preg_match($pattern, $url)) {
                $parameters = $this->getRouteParameters($route['url']);
                (new $route['class']())->{$route['method']}($parameters);
                break; // important! otherwise multiple routes might get matched
            }
        }
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
            
            #if (count($parts) !== count($parts2)) {
            #    die('not the same amount of elements!'); // continue;
            #}
            
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
     * @throws ReflectionException
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
                    $url = $this->parseDocBlock($item->getDocComment())['url'];
                    $routes[] = [
                        'url' => $url,
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
        return $routes;
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
        $_url = null;
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
                    $elements2 = explode(',', str_replace(')', '', trim($blockdocParts[1])));
                    $_url = str_replace('"', '', trim($elements2[0]));
                    $_name = str_replace('name=', '', str_replace('"', '', trim($elements2[1])));
                }
                if (trim($blockdocParts[0]) == 'Method') {
                    $element = str_replace(')', '', trim($blockdocParts[1]));
                    $_methods = \json_decode($element, true);
                    #var_dump($_methods);die;
                }
                
                $routes = [
                    'url'  => $_url,
                    'name' => $_name,
                    'httpMethods' => $_methods,
                ];
            }
        }
        
        return $routes;
    }
}