<?php
/**
 * @author KonstantinKuklin <konstantin.kuklin@gmail.com>
 */

namespace KonstantinKuklin\DoctrineDto\Configuration;


interface ConfigurationClassMapInterface
{
    /**
     * @param MapInterface $mapDto
     * @param MapInterface $mapEntity
     *
     * @return void
     */
    public static function setMap(MapInterface $mapDto, MapInterface $mapEntity);

    /**
     * @return MapInterface
     */
    public static function getMapDto();

    /**
     * @return MapInterface
     */
    public static function getMapEntity();

    /**
     * @return bool
     */
    public static function isInitialized();
}