# Бандл для получения сконфигурирированных экземпляров Guzzle. Поддержка middlewares и т.д.

Форк [пакета](https://github.com/csarrazi/CsaGuzzleBundle), доработано под личные нужды (добавлен адаптер для кэширования в Битриксе, и т.п).  


## Установка

1) `composer require proklung/symfony-guzzle-bundle`

## Использование


### Конфигурация

You may also enable the included logger, in order log outcoming requests:

```yml
csa_guzzle:
    logger: true
```

### Autowiring

If you rely on Symfony autowiring, you can choose to alias a specific service to the `GuzzleHttp\ClientInterface`
interface and `GuzzlHttp\Client` class.

```yml
csa_guzzle:
    profiler: '%kernel.debug%'
    logger: true
    clients:
        github_api:
            config:
                base_uri: 'https://api.github.com'
                headers:
                    Accept: application/vnd.github.v3+json
        jsonplaceholder:
            config:
                base_uri: 'https://jsonplaceholder.typicode.com'
                headers:
                    Accept: application/json
    default_client: github_api
```

Then, your github_api client will be automatically injected into your controller or service:

```php
<?php

namespace App\Controller;

use Twig\Environment;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;

class DefaultController
{
    private $twig;
    private $client;

    public function __construct(Environment $twig, Client $client)
    {
        $this->twig = $twig;
        $this->client = $client;
    }

    public function index()
    {
        $this->client->get('/users');

        return new Response($this->twig->render("base.html.twig"), 200, ['Content-Type' => 'text/html']);
    }
}
```

### Creating a service for your client


There are two ways for creating a service for your client:

* Using the semantic configuration (Beginners)
* Registering your own service (Advanced users)

### Creating a client using semantic configuration

Simply write the following code:

```yml
csa_guzzle:
    clients:
        github_api:
            config: # you can specify the options as in http://docs.guzzlephp.org/en/latest/quickstart.html#creating-a-client
                base_uri: https://api.github.com
                timeout: 2.0
                headers:
                    Accept: application/vnd.github.v3+json
```

The previous code will create a new service, called `csa_guzzle.client.github_api`, that you can use in your controller, or that you can inject in another service:

```php
<?php

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class MyController extends Controller
{
    public function indexAction()
    {
        $client = $this->get('csa_guzzle.client.github_api');
        // ...
    }
}
```

You may want to mark the service as [lazy](http://symfony.com/doc/current/components/dependency_injection/lazy_services.html).

```yml
csa_guzzle:
    clients:
        my_client:
            lazy: true
            # ...
```

If you override your client's class, you can also set the class for your client:

```yml
csa_guzzle:
    clients:
        my_client:
            class: AppBundle\Client
            # ...
```

Of course, you need to make sure that your client class' constructor has exactly the same signature as Guzzle's Client class.

### Registering your own service

To have a client supported by the bundle, simply tag it as such:

**YAML:**

```yml
acme.client:
    class: %acme.client.class%
    arguments: [{ base_uri: http://acme.com, timeout: 2.0} ]
```

### Creating new middleware

Creating a new Guzzle [middleware](http://guzzle.readthedocs.org/en/latest/handlers-and-middleware.html#middleware)
is as easy as creating a symfony service and using the `csa_guzzle.middleware` tag, giving it an alias and
(optionally) a priority:

```xml
<service
        id="acme.middleware"
        class="Closure">
    <factory class="My\Middleware" method="my_middleware" />
    <tag name="csa_guzzle.middleware" alias="my_middleware" priority="100" />
</service>
```

You can also define middleware as a class with the `__invoke` method like this:

```php
class Middleware
{
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $request = $request->withHeader('X-Test', 'I was here');

            return $handler($request, $options);
        };
    }
}
```

The service definition for such a class is then:

```yaml
My\Middleware:
    tags:
        - { name: csa_guzzle.middleware, alias: my_middleware, priority: 100 }
```

Middleware are automatically used by all your clients, if you are using the semantic configuration.
However, if you wish to, you can limit a client to a list of specific middleware:

```yml
csa_guzzle:
    # ...
    clients:
        # Prototype
        github_api:
            config:
                base_uri: https://api.github.com
                headers:
                    Accept: application/vnd.github.v3+json
            middleware: ['debug', 'my_middleware'] # Note the use of the alias defined earlier in the service definition.
```

You can also disable specific middleware, by prefixing the middleware name with a `!` character:

```yml
csa_guzzle:
    # ...
    clients:
        github_api:
            # ...
            middleware: ['!my_middleware']
```

You can either whitelist or blacklist middleware. Using both whitelisting and blacklisting will trigger an exception.

When registering your own clients with the bundle, you can explicitly list all
enabled middleware. The `middleware` attribute takes a space-delimited list of
middleware names. In that case only the specified middleware will be registered
for that client:

**YAML:**

```yml
acme.client:
    class: %acme.client.class%

    tags:
      - { name: csa_guzzle.client, middleware: 'my_middleware another_middleware'}
```

### BitrixCacheAdapter

Определяется сервис кэширования. Например, так:

```yaml
  bitrix.psr.cacher:
    class: WebArch\BitrixCache\AntiStampedeCacheAdapter
    arguments: ['/', 3600, 'cache/s1/psr-cache']

  bitrix.psr.cacher.adapter:
    class: Local\Bundles\GuzzleBundle\Middlewares\Cache\Adapter\PsrAdapter
    arguments: ['@bitrix.psr.cacher', 3600]
```

И в конфигурации бандла указывается:

```yaml
  cache:
    enabled: true
    adapter: bitrix.psr.cacher.adapter
```

### Available middleware

Currently, five middleware are available:

* the `logger` middleware
* the `cache` middleware
* the `mock` middleware


The `logger` middleware
-----------------------

The `logger` middleware's objective is to provide a simple tool for logging Guzzle requests.

Enabling request logging, you simply need to enable it in Symfony's configuration:

```yml
csa_guzzle:
    logger:
        enabled: true
```

Like the `debug` middleware, there's also a shorthand syntax to enable it:

```yml
csa_guzzle:
    logger: true
```

Using the advanced configuration, you may also configure your own logger, as long as it implements
the PSR-3 `LoggerInterface`:

```yml
csa_guzzle:
    logger:
        enabled: true
        service: my_logger_service
```

You can configure the log format using the syntax described in [guzzlehttp/guzzle's documentation](https://github.com/guzzle/guzzle/blob/master/src/MessageFormatter.php#L12).
You may also use of the three levels described in the formatter: `clf` (Apache log format), `debug`, or `short`:

```yml
csa_guzzle:
    logger:
        enabled: true
        format: debug
```

You could also change the level of logging, for `dev`, you likely want `debug`, for `prod`, you likely want `error`. You'll find more log levels in the [LogLevel of php-fig](https://github.com/php-fig/log/blob/master/Psr/Log/LogLevel.php).

```yml
csa_guzzle:
    logger:
        enabled: true
        level: debug
```

The `cache` middleware
----------------------

The `cache` middleware's objective is to provide a very simple cache, in order to cache Guzzle responses.

Even though only a [doctrine/cache](https://github.com/doctrine/cache) adapter is provided
(`Prokl\GuzzleBundle\GuzzleHttp\Cache\DoctrineAdapter`), the middleware is agnostic.
If you wish to use your own cache implementation with the `cache` middleware, you simply need
to implement `Prokl\GuzzleBundle\GuzzleHttp\Cache\StorageAdapterInterface`, and you're set!

This middleware can be configured with the following configuration:

```yml
csa_guzzle:
    cache:
        enabled: true
        adapter: my_storage_adapter
```

To use the doctrine cache adapter, you need to use the `Prokl\GuzzleBundle\GuzzleHttp\Cache\DoctrineAdapter`
class, in which you should inject your doctrine cache service. For example, using doctrine/cache's `FilesystemCache`:

```xml
<services>
    <service id="my_storage_adapter" class="Prokl\GuzzleBundle\GuzzleHttp\Cache\DoctrineAdapter">
        <argument type="service" id="my_cache_service" />
    </service>

    <service id="my_cache_service" class="Doctrine\Common\Cache\FilesystemCache">
        <argument>%kernel.cache_dir%/my_cache_folder</argument>
    </service>
</services>
```

The `mock` middleware
---------------------

When running tests, you often want to disable real HTTP requests to your (or an external) API.
The `mock` middleware can record those requests to replay them in tests.

The `mock` middleware can work in two modes:

* record, which saves your HTTP requests inside a directory in your filesystem
* replay, which uses your saved HTTP requests from the same directory

Of course, this middleware should only be used in the `test` environment (or `dev`, if you don't have
access to the remote server):

```yml
# config_test.yml
csa_guzzle:
    mock:
        storage_path: "%kernel.root_dir%/../features/fixtures/guzzle"
        mode: record
```

The generated files can then be committed in the VCS.

To use them, simply change the mode to `replay`:

```yml
# config_test.yml
csa_guzzle:
    mock:
        storage_path: "%kernel.root_dir%/../features/fixtures/guzzle"
        mode: replay
```

A few customizations can be done with the `mock` middleware. You can indeed blacklist:

* Request headers, so they are not used for generating the mock's filename.
* Response headers, so they are not saved in the mock file.

For this, you can simply configure your client as follows:

```yml
# config_test.yml
csa_guzzle:
    mock:
        # ...
        request_headers_blacklist: ['User-Agent', 'Host', 'X-Guzzle-Cache', 'X-Foo']
        response_headers_blacklist: ['X-Guzzle-Cache', 'X-Bar']
```

### Configuration reference

```yml
csa_guzzle:
    profiler:
        enabled:              false

        # The maximum size of the body which should be stored in the profiler (in bytes)
        max_body_size:        65536 # Example: 65536
    logger:
        enabled:              false
        service:              ~
        format:               '{hostname} {req_header_User-Agent} - [{date_common_log}] "{method} {target} HTTP/{version}" {code} {res_header_Content-Length}'
        level:                debug
    default_client:       ~
    cache:
        enabled:              false
        adapter:              ~
    clients:

        # Prototype
        name:
            class:                GuzzleHttp\Client
            lazy:                 false
            config:               ~
            middleware:           []
            alias:                null
    mock:
        enabled:              false
        storage_path:         ~ # Required
        mode:                 replay
        request_headers_blacklist: []
        response_headers_blacklist: []
```

To log request/response body you can use `{req_body}` and `{res_body}` respectively in `format` setting.

Full list of logs variables with description:

| Variable | 	Substitution |
| --- | --- |
| {request}	| Full HTTP request message | 
| {response}	| Full HTTP response message | 
| {ts}	 | Timestamp | 
| {host} |	Host of the request | 
| {method} |	Method of the request | 
| {url}	 | URL of the request | 
| {host} |	Host of the request | 
| {protocol} | 	Request protocol | 
| {version} | Protocol version | 
| {resource}|	Resource of the request (path + query + fragment) | 
| {port}	| Port of the request | 
| {hostname} | 	Hostname of the machine that sent the request | 
| {code} | Status code of the response (if available) | 
| {phrase} | Reason phrase of the response (if available) | 
| {curl_error} | Curl error message (if available) | 
| {curl_code} | Curl error code (if available) | 
| {curl_stderr} | Curl standard error (if available) | 
| {connect_time} | Time in seconds it took to establish the connection (if available) | 
| {total_time}	 | Total transaction time in seconds for last transfer (if available) | 
| {req_header_*} | Replace * with the lowercased name of a request header to add to the message | 
| {res_header_*} | Replace * with the lowercased name of a response header to add to the message | 
| {req_body} | Request body  | 
| {res_body} | Response body|


Reference [Guzzle Log Plugin Docs](http://guzzle3.readthedocs.io/plugins/log-plugin.html#log-plugin)

