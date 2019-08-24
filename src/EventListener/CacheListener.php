<?php declare(strict_types = 1);

namespace Nameisis\Cache\EventListener;

use Doctrine\Common\Annotations\Reader;
use JsonSerializable;
use Nameisis\Cache\Annotation\Cache;
use Nameisis\Cache\DependencyInjection\NameisisCacheExtension;
use Nameisis\Cache\NameisisCache;
use Nameisis\Cache\Provider\CacheAdapter;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Vairogs\Utils\Exception\VairogsException;
use function class_exists;
use function explode;
use function get_class;
use function in_array;
use function is_array;
use function method_exists;
use function sprintf;
use const false;
use const null;
use const true;

class CacheListener implements EventSubscriberInterface
{
    /**
     * @var string
     */
    private const ROUTE = '_route';

    /**
     * @var ChainAdapter
     */
    protected $client;

    /**
     * @var Reader
     */
    protected $reader;

    /**
     * @var bool
     */
    protected $enabled;

    /**
     * @var null|TokenStorageInterface
     */
    protected $storage;

    /**
     * @param Reader $reader
     * @param ContainerInterface $container
     * @param null|TokenStorageInterface $storage
     * @param CacheAdapter[] ...$adapters
     *
     * @throws VairogsException
     */
    public function __construct(Reader $reader, ContainerInterface $container, ?TokenStorageInterface $storage, ...$adapters)
    {
        $this->enabled = $container->getParameter(sprintf('%s.enabled', NameisisCacheExtension::EXTENSION));
        if ($this->enabled) {
            $this->reader = $reader;
            $this->storage = $storage;
            $this->client = new ChainAdapter($this->createPool($adapters));
            $this->client->prune();
        }
    }

    /**
     * @param CacheAdapter[] $adapters
     *
     * @return CacheItemPoolInterface[]
     * @throws VairogsException
     */
    private function createPool(array $adapters = []): array
    {
        $pool = [];

        foreach ($adapters as $adapter) {
            if (null === $adapter) {
                continue;
            }

            if (!$adapter instanceof CacheAdapter) {
                throw new VairogsException(sprintf('Adapter %s must implement %s', get_class($adapter), CacheAdapter::class));
            }
            /** @var CacheAdapter $provider */
            $pool[] = $adapter->getCacheItemPool();
        }

        if ([] === $pool) {
            throw new VairogsException(sprintf('At least one provider must be provided in order to use %s', Cache::class));
        }

        return $pool;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                'onKernelController',
                -100,
            ],
            KernelEvents::RESPONSE => 'onKernelResponse',
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    /**
     * @param ControllerEvent $event
     *
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws VairogsException
     */
    public function onKernelController(ControllerEvent $event): void
    {
        if (!$this->check($event)) {
            return;
        }

        if ($annotation = $this->getAnnotation($event)) {
            $annotation->setData($this->getAttributes($event));
            /* @var $annotation Cache */
            $response = $this->getCache($annotation->getKey($event->getRequest()->get(self::ROUTE)));
            if (null !== $response) {
                $event->setController(static function () use ($response) {
                    return $response;
                });
            }
        }
    }

    /**
     * @param KernelEvent $event
     *
     * @return bool
     */
    private function check(KernelEvent $event): bool
    {
        if (!$this->enabled || !$this->client || !$event->isMasterRequest()) {
            return false;
        }

        if (method_exists($event, 'getResponse') && $event->getResponse() && !$event->getResponse()->isSuccessful()) {
            return false;
        }

        if (empty($controller = $this->getController($event)) || !class_exists($controller[0])) {
            return false;
        }

        return true;
    }

    /**
     * @param KernelEvent $event
     *
     * @return array
     */
    private function getController(KernelEvent $event): array
    {
        if (is_array($controller = explode('::', $event->getRequest()->get('_controller'), 2)) && isset($controller[1])) {
            return $controller;
        }

        return [];
    }

    /**
     * @param KernelEvent $event
     *
     * @return null|object
     * @throws ReflectionException
     */
    private function getAnnotation(KernelEvent $event): ?object
    {
        $controller = $this->getController($event);
        $controllerClass = new ReflectionClass(reset($controller));

        if ($method = $controllerClass->getMethod(end($controller))) {
            return $this->reader->getMethodAnnotation($method, Cache::class);
        }

        return null;
    }

    /**
     * @param KernelEvent $event
     *
     * @return array
     * @throws ReflectionException
     * @throws VairogsException
     */
    private function getAttributes(KernelEvent $event): array
    {
        $input = [];
        if ($annotation = $this->getAnnotation($event)) {
            $request = $event->getRequest();

            $user = null;
            if (null !== $this->storage && $this->storage->getToken() && $object = $this->storage->getToken()->getUser()) {
                if (is_array($object)) {
                    $user = $object;
                } elseif ($object instanceof JsonSerializable) {
                    $user = $object->jsonSerialize();
                } elseif (method_exists($object, 'toArray')) {
                    $user = $object->toArray();
                } elseif (method_exists($object, '__toArray')) {
                    $user = $object->__toArray();
                }
            }

            switch ($annotation->getStrategy()) {
                case Cache::GET:
                    $input = $request->attributes->get('_route_params') + $request->query->all();
                    break;
                case Cache::POST:
                    $input = $request->request->all();
                    break;
                case Cache::USER:
                    if (null !== $user) {
                        $input = $user;
                    }
                    break;
                case Cache::MIXED:
                    $input = [
                        Cache::GET => $request->attributes->get('_route_params') + $request->query->all(),
                        Cache::POST => $request->request->all(),
                    ];
                    if (null !== $user) {
                        $input[Cache::USER] = $user;
                    }
                    break;
                case Cache::ALL:
                    $input = $request->attributes->get('_route_params') + $request->query->all() + $request->request->all();
                    if (null !== $user) {
                        $input += $user;
                    }
                    break;
                default:
                    throw new VairogsException(sprintf('Unknown strategy: %s', $annotation->getStrategy()));
            }
        }

        return $input;
    }

    /**
     * @param $key
     *
     * @return null|mixed
     * @throws InvalidArgumentException
     */
    private function getCache($key)
    {
        $cache = $this->client->getItem($key);
        if ($cache->isHit()) {
            return $cache->get();
        }

        return null;
    }

    /**
     * @param RequestEvent $event
     *
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws VairogsException
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->check($event)) {
            return;
        }

        $invalidate = $event->getRequest()->headers->get(NameisisCache::CACHE_HEADER);
        if (null !== $invalidate && in_array($invalidate, [
                NameisisCache::INVALIDATE_CACHE,
                NameisisCache::SKIP_CACHE,
            ], true) && $annotation = $this->getAnnotation($event)) {
            $annotation->setData($this->getAttributes($event));
            $key = $annotation->getKey($event->getRequest()->get(self::ROUTE));
            $this->client->deleteItem($key);
        }
    }

    /**
     * @param ResponseEvent $event
     *
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws VairogsException
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->check($event)) {
            return;
        }

        if ($annotation = $this->getAnnotation($event)) {
            $annotation->setData($this->getAttributes($event));
            $key = $annotation->getKey($event->getRequest()->get(self::ROUTE));
            $cache = $this->getCache($key);
            $skip = NameisisCache::SKIP_CACHE === $event->getRequest()->headers->get(NameisisCache::CACHE_HEADER);
            if (null === $cache && !$skip) {
                $this->setCache($key, $event->getResponse(), $annotation->getExpires());
            }
        }
    }

    /**
     * @param string $key
     * @param $value
     * @param null|int $expires
     *
     * @throws InvalidArgumentException
     */
    private function setCache(string $key, $value, ?int $expires): void
    {
        $cache = $this->client->getItem($key);
        $cache->set($value);
        $cache->expiresAfter($expires);
        $this->client->save($cache);
    }
}
