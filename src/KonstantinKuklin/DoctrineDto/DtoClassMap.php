<?php
/**
 * @author KonstantinKuklin <konstantin.kuklin@gmail.com>
 */

namespace KonstantinKuklin\DoctrineDto;

use InvalidArgumentException;
use KonstantinKuklin\DoctrineDto\Configuration\ConfigurationClassMapInterface;
use KonstantinKuklin\DoctrineDto\Configuration\MapInterface;

final class DtoClassMap implements ConfigurationClassMapInterface
{
    /**
     * @var MapInterface
     */
    private static $mapDto;

    /**
     * @var MapInterface
     */
    private static $mapEntity;

    /**
     * @var bool
     */
    private static $initialized = false;

    /**
     * {@inheritdoc}
     */
    public static function setMap(MapInterface $mapDto, MapInterface $mapEntity)
    {
        if (!self::$initialized) {
            self::$mapDto = $mapDto;
            self::$mapEntity = $mapEntity;
            self::$initialized = true;
        } else {
            throw new InvalidArgumentException('ClassMap was already initialized.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getMapDto()
    {
        if (self::$initialized) {
            return self::$mapDto;
        }

        throw new InvalidArgumentException('ClassMap was not be initialized yet.');
    }

    /**
     * {@inheritdoc}
     */
    public static function getMapEntity()
    {
        if (self::$initialized) {
            return self::$mapEntity;
        }

        throw new InvalidArgumentException('ClassMap was not be initialized yet.');
    }

    /**
     * {@inheritdoc}
     */
    public static function isInitialized()
    {
        return self::$initialized;
    }
}