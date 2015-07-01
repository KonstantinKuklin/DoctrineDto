<?php
/**
 * @author KonstantinKuklin <konstantin.kuklin@gmail.com>
 */
namespace KonstantinKuklin\DoctrineDto\Configuration;

interface MapInterface
{
    /**
     * @param string $fromPath
     * @param string $toPath
     */
    public function addMapElement($fromPath, $toPath);

    /**
     * @param MapGeneratorInterface $mapGeneratorInterface
     */
    public function addMapGeneratorElement(MapGeneratorInterface $mapGeneratorInterface);

    /**
     * @param string $classPath
     *
     * @return string
     */
    public function getPath($classPath);

    /**
     * @return MapInterface
     */
    public function getFlippedMap();
}