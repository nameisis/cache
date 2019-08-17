# nameisis/cache

Original author asked me if I would like to take this package over as he doesn't have time to work on it and I said yes.
---


[![Latest Stable Version](https://poser.pugx.org/nameisis/cache/v/stable?format=flat-square)](https://packagist.org/packages/nameisis/cache)
[![Latest Unstable Version](https://poser.pugx.org/nameisis/cache/v/unstable?format=flat-square)](https://packagist.org/packages/nameisis/cache)
[![License](https://poser.pugx.org/nameisis/cache/license?format=flat-square)](https://packagist.org/packages/nameisis/cache)
[![Total Downloads](https://poser.pugx.org/nameisis/cache/downloads?format=flat-square)](https://packagist.org/packages/nameisis/cache)  

## I made Medium article about this repository when it was created by original author, check it out [here][2]!

Nameisis cache is annotation based controller response cache for Symfony framework.
It generates route specific key from GET and POST parameters and saves it in provided cache clients.  

Currently supported clients are Predis and DoctrineOrm.  
***
```yaml
# config/packages/nameisis_cache.yaml
nameisis_cache:
    enabled: FALSE|true

services:
    Nameisis\Cache\EventListener\CacheListener:
        arguments:
            - '@annotation_reader'
            - '@service_container'
            - null                                      #ToKenStorageInterface
            - '@snc_redis.session'                      #provider_1
            - '@doctrine.orm.default_entity_manager'    #provider_2
            - ...                                       #provider_n
        tags:
            -   name: kernel.event_subscriber
```
**enabled** parameter by default is set to false, which means that @Cache annotation won't work without setting it to true. 

2.0 version of this bundle have provider configuration set up through service instead of parameter. 

In the example above, two providers are provided:   
Predis client from snc/redis-bundle => snc_redis.session and Doctrine entity manager => doctrine.orm.default_entity_manager.  

If **enabled** parameter is set as true, at least one valid provider must be provided.

Nameisis\Cache uses Symfony\Cache [ChainAdapter][1]
```text
When an item is not found in the first adapter but is found in the next ones, this adapter ensures that the fetched item is saved to all the adapters where it was previously missing.
```
***
```php
use Nameisis\Cache\Annotation\Cache;
use Symfony\Component\Routing\Annotation\Route;

@Route("/{id}", name = "default", defaults = {"id" = 1}, methods = {"GET", "POST"})
@Cache(
    expires = 3600,
    attributes = {"id"}
)
```
By default @Cache annotation doesn't need any parameters.  
Without **attributes** parameter, cache key for route is made from all of GET and POST parameters.  
Without **expires** parameter, cache is saved for unlimited ttl which means that it will be valid until deleted.  

If the same parameter exist in multiple strategies, value of **GET** parameter will be used.   

This bundle supports 5 different strategies for saving cache:
* GET  
* POST
* USER
* ALL
* MIXED

*GET* and *POST* are pretty obvious, cache are made from GET or POST parameters.  
*USER* strategy has to have TokenStorageInterface service provided in defined service. This strategy allows saving cache for 
any parameter that authorized user have, for example, id or email. In order to use this strategy, TokenStorage user class must implement 
``Vairogs\\Utils\\Interfaces\\Arrayable``.  
*ALL* strategy just mixes all other strategies, so you don't have to list all needed strategies like GET, POST, USER, but just use one 
overall strategy.  
*MIXED* strategy is almost the same as ALL strategy, but instead of making one array of all strategies, MIXED can be assigned by specific 
strategy parameters.  

@Cache annotation takes two optional parameters: expires and attributes.  
**expires** sets maximum ttl for given cache.   
In the example above cache will be saved for 3600 seconds and after 3600 seconds it will be invalidated.  
If the **attributes** parameter is set and given attribute(s) exist in GET or POST parameters, 
only given parameters will be used in cache key. 
Only valid attributes will be used in creation of key. If none of given attributes exist, key will be made without any parameters.
***
```yaml
NameisisCache: invalidate
```
In order to invalidate and delete the cache for endpoint, you must call this endpoint with specific header.
For this you need to set **NameisisCache** header with **invalidate** value.  
Passing invalidate value to header deletes existing cache and writes a new one.  

If in **NameisisCache** header you pass value **skip** instead, cache is invalidated but new cache is not created.  

[1]: https://symfony.com/doc/current/components/cache/adapters/chain_adapter.html
[2]: https://medium.com/@k0d3r1s/phpsed-cache-423d0fefa68
