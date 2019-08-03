<?php declare(strict_types = 1);

namespace Nameisis\Cache\Annotation;

use function array_flip;
use function array_intersect_key;
use function hash;
use function http_build_query;
use function is_array;
use function str_replace;

/**
 * @Annotation
 */
class Cache
{
    /**
     * @var string
     */
    private const ALGORITHM = 'sha256';

    public const GET = 'GET';
    public const POST = 'POST';
    public const USER = 'USER';
    public const MIXED = 'MIXED';

    /**
     * @var integer
     */
    public $expires;

    /**
     * @var null|string|array
     */
    public $data;

    /**
     * @var array
     */
    public $attributes = [];

    /**
     * @var string
     */
    public $strategy = self::MIXED;

    /**
     * @param string $prefix
     *
     * @return string
     */
    public function getKey(string $prefix = ''): string
    {
        $value = $this->getData();
        if (!is_array($value)) {
            $key = $value ?: '';
        } else {
            if (!empty($this->getAttributes())) {
                $flipped = array_flip($this->getAttributes());
                $value = array_intersect_key($value, $flipped);
            }

            $key = str_replace('=', '_', http_build_query($value, '', '_'));
        }

        return hash(self::ALGORITHM, $prefix.'_'.$key);
    }

    /**
     * @return null|array|string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param $data
     *
     * @return Cache
     */
    public function setData($data): Cache
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return null|string
     */
    public function getStrategy(): ?string
    {
        return $this->strategy;
    }

    /**
     * @param null|string $strategy
     *
     * @return Cache
     */
    public function setStrategy(?string $strategy): Cache
    {
        $this->strategy = $strategy;

        return $this;
    }

    /**
     * @return null|int
     */
    public function getExpires(): ?int
    {
        return $this->expires;
    }
}
