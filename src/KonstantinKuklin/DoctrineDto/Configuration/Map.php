<?php
/**
 * @author KonstantinKuklin <konstantin.kuklin@gmail.com>
 */

namespace KonstantinKuklin\DoctrineDto\Configuration;

use InvalidArgumentException;

class Map implements MapInterface
{
    private $map = array();

    /**
     * @var MapGeneratorInterface[]
     */
    private $mapGeneratorList = array();

    /**
     * DtoEntityMap constructor.
     *
     * @param array $map
     */
    public function __construct(array $map = array())
    {
        $this->map = $map;
    }

    /**
     * {@inheritdoc}
     */
    public function addMapElement($fromPath, $toPath)
    {
        if (isset($this->map[$fromPath])) {
            throw new InvalidArgumentException('The map already exists for: ' . $fromPath);
        }

        $this->map[$fromPath] = $toPath;
    }

    /**
     * {@inheritdoc}
     */
    public function addMapGeneratorElement(MapGeneratorInterface $mapGeneratorInterface)
    {
        $hash = spl_object_hash($mapGeneratorInterface);
        $this->mapGeneratorList[$hash] = $mapGeneratorInterface;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath($classPath)
    {
        if (isset($this->map[$classPath])) {
            return $this->map[$classPath];
        }

        foreach ($this->mapGeneratorList as $mapGenerator) {
            $pathTo = $mapGenerator->getPath($classPath);
            if (!class_exists($pathTo)) {
                continue;
            }

            $this->map[$classPath] = $pathTo;

            return $this->map[$classPath];
        }

        throw new \UnexpectedValueException('No class to return for: ' . $classPath);
    }

    /**
     * {@inheritdoc}
     */
    public function getFlippedMap()
    {
        $flipedMap = array_flip($this->map);
        $class = new Map($flipedMap);

        return $class;
    }
}