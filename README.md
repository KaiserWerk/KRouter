# KRouter

### Installation & Usage

First, do a `composer require kaiserwerk/k-router dev-master` to fetch the latest version. Then, In your index or bootstrap file, add

```
require_once __DIR__ . '/vendor/autoload.php';

...

$router = new KRouter();
$router->dispatch():
```

Then, you can create controller classes (don't forget to extend the Controller class and include the actual file) and 
add annotations like this:

```
class DefaultController extends Controller
{
    /**
     * @Route("/route/[:value]/do")
     * @Method(["GET", "HEAD"])
     */
    public function routeDoAction($params)
    {
      $value = $params->value;
    }
}
```
