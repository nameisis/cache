<?php declare(strict_types = 1);

namespace Nameisis\Cache\EventListener;

use Doctrine\Common\Annotations\Reader;
use Nameisis\Utils\Utils\Request;
use Nameisis\Cache\Annotation\Cache;
use Nameisis\Cache\NameisisCache;
use Nameisis\Utils\Cache\Adapter\CacheAdapter;
use Nameisis\Utils\Utils\Pool;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Vairogs\Utils\Exception\VairogsException;
use function class_exists;
use function in_array;
use function method_exists;
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
     * @var bool
     */
    protected $enabled;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @param Reader $reader
     * @param bool $enabled
     * @param null|TokenStorageInterface $storage
     * @param CacheAdapter[] ...$adapters
     *
     * @throws VairogsException
     */
    public function __construct(Reader $reader, bool $enabled, ?TokenStorageInterface $storage, ...$adapters)
    {
        $this->enabled = $enabled;
        if ($this->enabled) {
            $this->client = new ChainAdapter(Pool::createPoolFor(Cache::class, $adapters));
            $this->client->prune();
            $this->request = new Request($reader, $storage);
        }
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

        if ($annotation = $this->request->getAnnotation($event, Cache::class)) {
            $annotation->setData($this->request->getAttributes($event, Cache::class));
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

        if (empty($controller = $this->request->getController($event)) || !class_exists($controller[0])) {
            return false;
        }

        return true;
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
            ], true) && $annotation = $this->request->getAnnotation($event, Cache::class)) {
            $annotation->setData($this->request->getAttributes($event, Cache::class));
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

        if ($annotation = $this->request->getAnnotation($event, Cache::class)) {
            $annotation->setData($this->request->getAttributes($event, Cache::class));
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
