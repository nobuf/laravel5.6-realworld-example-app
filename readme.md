Reimplementing the backend part of [RealWorld](https://github.com/gothinkster/realworld) in Laravel. As of May 2018, 5.6 is the latest Laravel version and I'll use php7.2, so it will be different from [gothinkster/laravel-realworld-example-app](https://github.com/gothinkster/laravel-realworld-example-app) anyways.

Why Laravel? Just for fun ;)

In case someone's reading this and wondering where my subjective opinion comes from, I use php5 day-to-day at a 11-50 employees company for a consumer facing product and wanted to play around with Laravel which I have no experience.

## Development Environment

- Latest macOS + php7.2.5 + brew etc.
- VSCode, PhpStorm with Laravel related plugins
- iTerm/bash

## Postman

Here is [RealWorld API Spec](https://github.com/gothinkster/realworld/tree/master/api) and it comes with Postman Collection.

```shell
mkdir -p ~/Downloads/realworld-api
cd ~/Downloads/realworld-api
curl -O -L \
	https://raw.githubusercontent.com/gothinkster/realworld/master/api/Conduit.postman_collection.json
curl -O -L \
	https://raw.githubusercontent.com/gothinkster/realworld/master/api/Conduit.postman_environment.json
curl -O -L \
	https://raw.githubusercontent.com/gothinkster/realworld/master/api/Conduit.postman_integration_test_environment.json
```

Download [Postman](https://www.getpostman.com/) and sign up. Import the collection folder `~/Downloads/realworld-api`. Postman is new to me, so I needed to spend a bit of time to make myself familialize this tool.

Default `apiUrl` points to `https://conduit.productionready.io/api` which apparently returning valid responses.

## Setup Laravel

Following [the official instruction](https://laravel.com/docs/5.6):

```shell
composer create-project --prefer-dist laravel/laravel \
	laravel5.6-realworld-example-app
```

```shell
php artisan serve
```

Yay, Laravel is up and running locally. I see `Valet` is an alternative and it looks handy.

In a real world, I might choose Docker for the sake of mobility. For now, I don't need to worry about CI/CD environment, and I'm a solo developer on this project.

```shell
cd ~/projects
valet park
valet secure laravel5.6-realworld-example-app
open https://laravel5.6-realworld-example-app.test/
```

Sweet.

## Postman Again

When it comes to tasks that run again and again, command line tool would be preferable to me. Though with [Newman](https://www.npmjs.com/package/newman), I couldn't find any option to filter Item like `Login`. I'll stick with the GUI app.

Edit environment:

```diff
diff --git a/tests/Postman/Conduit.postman_environment.json b/tests/Postman/Conduit.postman_environment.json
index 212df12..dc4cc8a 100644
--- a/tests/Postman/Conduit.postman_environment.json
+++ b/tests/Postman/Conduit.postman_environment.json
@@ -5,7 +5,7 @@
     {
       "enabled": true,
       "key": "apiUrl",
-      "value": "https://conduit.productionready.io/api",
+      "value": "https://laravel5.6-realworld-example-app.test/api",
       "type": "text"
     },
     {
```

```shell
newman run tests/Postman/Conduit.postman_collection.json \
	--environment tests/Postman/Conduit.postman_environment.json
```

## Setup Database

```shell
brew install mysql
brew services start mysql
mysql -uroot
mysql> create database realworld ;
```

Edit `.env`.

## Authentication

```shell
ls -l database/migrations/
php artisan make:auth
php artisan migrate
open https://laravel5.6-realworld-example-app.test/register
```

Framework magic :)

OK, so `POST /login` is mapped to `App\Http\Controllers\Auth\LoginController`.

`routes/api.php` seems like the file for handling API requests, but PhpStorm with Laravel plugin nor VSCode doesn't recognize `Route::middleware` class and `app/Http/Middleware` directory doesn't include `Api` nor `Auth`. Time to google.

Well, I realize that there is a section called "[API Authentication](https://laravel.com/docs/5.6/passport)".

```shell
php artisan migrate
php artisan passport:install
```

In Postman app, `SSL certificate verification` must be unchecked.

```php
// routes/api.php
Route::middleware('guest:api')->post('/users/login', function (Request $request) {
    return 'hello';
});
```

At last. I wrote some code. With the above three lines of code, Postman gets something different than before.

```php
// routes/api.php
Route::middleware('guest:api')->post('/users/login', 'Api\UserController@login');

// app/Http/Controllers/Api/UserController.php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class UserController
{
    public function login(Request $request)
    {
        return 'hello again';
    }
}
```

`Auth/LoginController.php` uses `AuthenticatesUsers` trait.

```Php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use AuthenticatesUsers;

    protected function validateLogin(Request $request)
    {
        return 'verysecret' === $request->input('user.password');
    }

    protected function credentials(Request $request)
    {
        return $request->only('user.email', 'user.password');
    }
}
```

And,

```php
"message": "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'user' in 'where clause' (SQL: select * from `users` where `user` = nobu@realworld.test limit 1)",
"exception": "Illuminate\\Database\\QueryException",
```

:open_mouth:

`DatabaseUserProvider::retrieveByCredentials()` uses key as table column, so it should be:

```php
protected function credentials(Request $request)
{
    return [
        'email' => $request->input('user.email'),
        'password' => $request->input('user.password'),
    ];
}
```

Alright.

But how about JWT authentication? Also, I noticed that it's [`Token` instead of `Bearer`](https://github.com/gothinkster/realworld/issues/81).

This is kind of the time framework gets unfriendly. Once I get a question "Is X supported by the framework?", it's googling time. Not so fun compared to writing actual code.

I ended up using [`jwt-auth`](https://github.com/tymondesigns/jwt-auth).

It took me a while to activate `jwt-auth` because I didn't edit `config/auth.php` properly.

```php
// config/auth.php
'defaults' => [
    'guard' => 'web', // I forgot to replace 'web' to 'api'
...

// would return boolean, not token string
auth()->attempt($credentials)
```

And also, I figured not using `AuthenticatesUsers` trait and `passport` at all.

Again, it's `Authorization: Token {{token}}`. I found no document but a method for this very porpose: `AuthHeaders::setHeaderPrefix()`. Where to put it though? I copied `AbstractServiceProvider::registerTokenParser()` to `app/Providers/AuthServiceProvider.php` assuming it'd overwrite it. There would probably a better way.

One surprise I spent some time to solve was this error response:

```
{
    "message": "Unauthenticated."
}
```

The message doesn't tell the details. It was actually because the token was expired. I was using the same token on Postman for more than an hour.

```
Tymon\JWTAuth\Claims\Expiration
```

## Implementing End Points

Making `POST /users` and other end points.

```shell
php artisan make:migration modify_users_table --table=users
```

Realworld app's spec doesn't really specify the maximum length of `username`, so just leaving it `VARCHAR(255)`. Longer than the old Tweet 140 character limit.

Unlike Rails, `Migration` class apparently has only `up` and `down`. No `change`.

`user.email` seems not a convenient name. (I didn't know `$request->get('user')` at the moment I was writing code)

```Php
$this->validate($request, [
    'user.email' => 'required|string|email|max:255|unique:users',
    ...
```

This would raise a column not found error.

```php
$this->validate($request, [
    'user.email' => 'required|string|email|max:255|unique:users,email',
    ...
```

This should work. [Hint from StackOverFlow](https://stackoverflow.com/q/22405762/297679).

See `git log`.

All Postman tests under Auth folder should work in a specific order: Register, Login, Login and Remember Token, Current User, Update User.

## IDE Helper

For PhpStorm, [this package](https://github.com/barryvdh/laravel-ide-helper) helps to reduce warnings.

```shell
composer require --dev barryvdh/laravel-ide-helper
php artisan ide-helper:generate
php artisan ide-helper:models
php artisan ide-helper:meta
```

## Reading gothinkster/laravel-realworld-example-app

Things I learned:

- https://laravel.com/docs/5.6/controllers#dependency-injection-and-controllers
- https://laravel.com/docs/5.6/validation#form-request-validation

```php
public function update(UpdateUser $request)
{
    $user = auth()->user();

    if ($request->has('user')) {
        $user->update($request->get('user'));
    }
    ...
```

Aha.

```php
$reflection = new \ReflectionMethod(Foo::class, '__construct');
var_dump($reflection->getParameters()[0]->getClass()->name);

class Foo
{
    public function __construct(Bar $t)
    {
    }
}
```

Illuminate/Container uses `Reflection` to do automatic dependency injection.

```php
// Kernel
'auth.api' => \App\Http\Middleware\AuthenticateWithJWT::class,

// Controller
$this->middleware('auth.api');

// AuthenticateWithJWT which extends Tymon\JWTAuth\Middleware\BaseMiddleware
$user = $this->auth->parseToken('token')->authenticate()
```

They use `"tymon/jwt-auth": "0.5.*"` and I was using `1.0` which does not take any argument for `parseToken()`. Anyways, I'm still not sure where is the proper place to call `(new AuthHeaders())->setHeaderPrefix('token')` :thinking:

## Thoughts

- Laravel has form-based authentication boilerplate, it was quick to become look like ready.
- I like `valet` and running `phpunit` locally (though I didn't write any actual tests). 100 ms via PhpStorm `Ctrl-R`!
- PhpStorm doesn't recognize some variable types and that's because Laravel does use `__call()` and other conventions. With IDE Helper, "Go to Declaration" seems working for the most part.
- I must admit that I love the look and feel of [their documents](https://laravel.com/docs/5.6).
- I should have written Feature tests rather than relying on Postman.