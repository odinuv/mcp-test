# Keboola Symfony Service Skeleton
This is a simple Symfony service template repository. You can use it to quickly bootstrap a new service according to
our best practices.

See https://keboola.atlassian.net/wiki/spaces/TECH/pages/1603862628 for steps on how to create a new service from the skeleton.

## Quick setup
1. Create a new project using this template repository
2. Replace `symfony-skeleton` app name in `composer.json`, `config/services.yaml` etc.
3. Run `docker-compose run --rm dev composer install` to install Composer dependencies
4. Run `docker-compose up dev-server` to start a webserver

## Detailed setup steps
### Create a project repo
Start by creating a new project using the skeleton. The easiest way to do that is creating a new GitHub repo and selecting
`keboola/symfony-skeleton` as a template.

**NOTE**: See https://keboola.atlassian.net/wiki/spaces/TECH/pages/1603862628 for more instructions on how to properly setup repo
permissions.

### Setup the app
The repo provides working web service out of the box, but there are several places containing the application name you
should replace:

```json
# composer.json
{
"name": "keboola/symfony-skeleton", # <-- put the project name here
"type": "project",
...
```

```dotenv
# .env
APP_NAME=symfony-skeleton # <-- put the project name here
```

```php
# tests/Controller/IndexActionTest.php
...
class IndexActionTest extends WebTestCase
{
    private const APP_NAME = 'symfony-skeleton'; # <-- put the project name here
    private const APP_VERSION = 'DEV';

    public function testActionReturnsResponse(): void
...
```

```yaml
# docs/swagger.yaml
openapi: 3.0.0
info:
    title: Symfony Skeleton # <-- put the project name here
    version: '1.0.0'
...
```

### Install dependencies
```shell
docker-compose run --rm dev composer install
```

### Start the app
```shell
docker-compose up dev-server
``` 

Now the webserver should be running on `localhost:8080`. You can check the app is working by requesting a health-check:
```shell
$ curl localhost:8080/health-check                                                       
{"api":"symfony-skeleton","documentation":"http:\/\/localhost:8080\/docs\/swagger.yaml"}
```

## Using Docker
Project has Docker development environment setup, so you don't need to install anything on your local computer, except
the Docker & Docker Compose.

To run PHP scripts, use the `dev` service:
```shell
docker-compose run --rm dev composer install   # install dependencies using Composer 
docker-compose run --rm dev composer phpunit   # run Phpunit as a Composer script
docker-compose run --rm dev vendor/bin/phpunit # run Phpunit standalone
docker-compose run --rm dev bin/console        # run Symfony console commands
```

To run a webserver, hosting your app, use the `dev-server` service:
```shell
docker-compose up dev-server
```

To run local tests, use `ci` service. This will validate `composer` files and execute `phpcs`, `phpcs`, `phpstan` and `phpunit` tests.
```shell
docker-compose run --rm ci
```

## ENV & Configuration
For local development, we follow Symfony best practices as described in
[docs](https://symfony.com/doc/current/configuration.html#configuring-environment-variables-in-env-files)
and use `.env` file:
* `.env` is versioned, should contain sane defaults to run the service out of the box locally
* `.env.local` is not versioned, can be created to override any ENV variable locally
* `.env.test` is versioned, should contain anything extra is needed for `test` environment

But these are used for local development only and are not included in final Docker images, used to run the app in
production. Instead, we put an empty `.env.local.php` file into Docker, disabling the `.env` functionality and all
configuration must be provided using regular environment variables (like `-e` flag of Docker).

