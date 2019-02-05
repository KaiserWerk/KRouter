# KRouter

### Installation & Usage

First, do a `composer require kaiserwerk/k-router dev-master` to fetch the latest version. Then, In your index or bootstrap file, add

```
$router = new KRouter();
$router->dispatch():
```

Then, you can create controller classes (don't forget to extend the Controller class and include the actual file) and 
add annotations like this:

```
/**
 * @Route("/route/[:value]/do", name="route_do")
 * @Method(["GET", "HEAD"])
 */
public function routeDoAction($params)
{
  $value = $params->value;
}
```
