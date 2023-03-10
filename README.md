# Service Container in Laravel
The Laravel service container is a powerful tool for managing class dependencies and performing dependency injection. Dependency injection essentially means this: class dependencies are "injected" into the class via the constructor or, in some cases, "setter" methods.

```php
<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Repositories\UserRepository;
use App\Models\User;

class  UserController  extends  Controller
{
	protected  $users;
	public  function  __construct(UserRepository  $users)
	{
		$this->users  =  $users;
	}

	public  function  show($id)
	{
		$user  =  $this->users->find($id);
		return  view('user.profile',  ['user'  =>  $user]);
	}
}
```

In this example, the  `UserController`  needs to retrieve users from a data source. So, we will  **inject**  a service that is able to retrieve users. In this context, our  `UserRepository`  most likely uses  [Eloquent](https://laravel.com/docs/9.x/eloquent)  to retrieve user information from the database. However, since the repository is injected, we are able to easily swap it out with another implementation. We are also able to easily "mock", or create a dummy implementation of the  `UserRepository`  when testing our application.

A deep understanding of the Laravel service container is essential to building a powerful, large application, as well as for contributing to the Laravel core itself.

### [Zero Configuration Resolution](https://laravel.com/docs/9.x/container#zero-configuration-resolution)

If a class has no dependencies or only depends on other concrete classes (not interfaces), the container does not need to be instructed on how to resolve that class. For example, you may place the following code in your  `routes/web.php`  file:

```php
<?php
class  Service
{
}
Route::get('/', function  (Service  $service) {
	die(get_class($service));
});
```

In this example, hitting your application's  `/`  route will automatically resolve the  `Service`  class and inject it into your route's handler.

Thankfully, many of the classes you will be writing when building a Laravel application automatically receive their dependencies via the container, including  [controllers](https://laravel.com/docs/9.x/controllers),  [event listeners](https://laravel.com/docs/9.x/events),  [middleware](https://laravel.com/docs/9.x/middleware), and more.


### [When To Use The Container](https://laravel.com/docs/9.x/container#when-to-use-the-container)

You will often type-hint dependencies on routes, controllers, event listeners, and elsewhere without ever manually interacting with the container. For example, you might type-hint the  `Illuminate\Http\Request`  object on your route definition so that you can easily access the current request. Even though we never have to interact with the container to write this code, it is managing the injection of these dependencies behind the scenes:

```php
use Illuminate\Http\Request;
Route::get('/', function  (Request  $request) {
	// ...
});
```

In many cases, thanks to automatic dependency injection and  [facades](https://laravel.com/docs/9.x/facades), you can build Laravel applications without  **ever**  manually binding or resolving anything from the container.  **So, when would you ever manually interact with the container?**  Let's examine two situations.

First, if you write a class that implements an interface and you wish to type-hint that interface on a route or class constructor, you must  tell the container how to resolve that interface. Secondly, if you are  [writing a Laravel package](https://laravel.com/docs/9.x/packages)  that you plan to share with other Laravel developers, you may need to bind your package's services into the container.

## [1. Binding](https://laravel.com/docs/9.x/container#binding)

### [Binding Basics](https://laravel.com/docs/9.x/container#binding-basics)

#### [Simple Bindings](https://laravel.com/docs/9.x/container#simple-bindings)

Almost all of your service container bindings will be registered within  [service providers](https://laravel.com/docs/9.x/providers), so most of these examples will demonstrate using the container in that context.

Within a service provider, you always have access to the container via the  `$this->app`  property. We can register a binding using the  `bind`  method, passing the class or interface name that we wish to register along with a closure that returns an instance of the class:

```php
use App\Billing\PaymentGateway;

$this->app()->bind(PaymentGateway::class, function($app){
	return new PaymentGateway('USD');
});
```

Note that we receive the container itself as an argument to the resolver. We can then use the container to resolve sub-dependencies of the object we are building.

You may do so via the  `App`  [facade](https://laravel.com/docs/9.x/facades):

```
App::bind(PaymentGateway::class, function($app){
	return new PaymentGateway('USD');
});
```


> There is no need to bind classes into the container if they do not depend on any interfaces. The container does not need to be instructed on how to build these objects, since it can automatically resolve these objects using reflection.

#### [Binding A Singleton](https://laravel.com/docs/9.x/container#binding-a-singleton)

The  `singleton`  method binds a class or interface into the container that should only be resolved one time. Once a singleton binding is resolved, the same object instance will be returned on subsequent calls into the container:

```php
// Interface
interface PaymentGatewayContract
{
	public function charge($amount);
	public function setDiscount($amount);
}

// Concrete class
class BankPaymentGateway implements PaymentGatewayContract
{
	private $discount;
	public function setDiscount($amount)
	{
		$this->discount = $amount;
	}
}

// Provider
app()->bind(PaymentGatewayContract::class, function($app){
	return new BankPaymentGateway('USD');
});

// Controller
public function store(OrderDetails $orderDetails, PaymentGatewayContract $paymentGatewayContract)
{
	$orderDetails->all();
	dd($paymentGatewayContract->charge(2500));
}

// Order details class
public function __construct(PaymentGatewayContract $paymentGateway)
{
	$this->paymentGateway = $paymentGateway;
}
public function all()
{
	$this->paymentGateway->setDiscount(500);
	return [
		'name' => 'Ameer',
		'address' => 'Mohakhali, Dhaka'
	];
}
```
If you bind this above method in the service provider and also called a dependency injection into the controller and another dependency in OrderDetails class that should be created by same instance then you get error. Because you have created two different instance at a time and expect to behave like a single instance in the Controller and OrderDetails class.

In this situation you have to use singleton in the provider. A singleton is a class that allows only a single instance of itself to be created and gives access to that created instance.


#### [Binding Scoped Singletons](https://laravel.com/docs/9.x/container#binding-scoped)

The  `scoped`  method binds a class or interface into the container that should only be resolved one time within a given Laravel request / job lifecycle. While this method is similar to the  `singleton`  method, instances registered using the  `scoped`  method will be flushed whenever the Laravel application starts a new "lifecycle", such as when a  [Laravel Octane](https://laravel.com/docs/9.x/octane)  worker processes a new request or when a Laravel  [queue worker](https://laravel.com/docs/9.x/queues)  processes a new job:

```php
$this->app->scoped(Transistor::class, function  (Application  $app) {
	return  new  Transistor($app->make(PodcastParser::class));
});
```

### [2. Binding Interfaces To Implementations](https://laravel.com/docs/9.x/container#binding-interfaces-to-implementations)

A very powerful feature of the service container is its ability to bind an interface to a given implementation. For example, in the above example we create an interface like `PaymentGatewayContract` and binding it into the service provider.


## [Resolving](https://laravel.com/docs/9.x/container#resolving)

### [The  `make`  Method](https://laravel.com/docs/9.x/container#the-make-method)

You may use the  `make`  method to resolve a class instance from the container. The  `make`  method accepts the name of the class or interface you wish to resolve:

```php
$transistor = $this->app->make(Transistor::class);
```

If some of your class' dependencies are not resolvable via the container, you may inject them by passing them as an associative array into the  `makeWith`  method. For example, we may manually pass the  `$id`  constructor argument required by the  `Transistor`  service:

```php
$transistor = $this->app->makeWith(Transistor::class, ['id'  =>  1]);
```

If you are outside of a service provider in a location of your code that does not have access to the  `$app`  variable, you may use the  `App`  [facade](https://laravel.com/docs/9.x/facades)  or the  `app`  [helper](https://laravel.com/docs/9.x/helpers#method-app)  to resolve a class instance from the container:

```php
$transistor = App::make(Transistor::class);
```
