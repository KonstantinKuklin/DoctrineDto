<?php
/**
 * @author KonstantinKuklin <konstantin.kuklin@gmail.com>
 */

namespace KonstantinKuklin\DoctrineDto\Configuration;

interface MapGeneratorInterface
{
    /**
     * @param string $classPath
     *
     * @return string
     */
    public function getPath($classPath);
}