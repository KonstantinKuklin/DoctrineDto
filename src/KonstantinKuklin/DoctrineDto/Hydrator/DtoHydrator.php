<?php
/**
 * @author KonstantinKuklin <konstantin.kuklin@gmail.com>
 */

namespace KonstantinKuklin\DoctrineDto\Hydrator;

use Doctrine\ORM\Internal\Hydration\AbstractHydrator;
use Doctrine\ORM\Internal\Hydration\HydrationException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Proxy\Proxy;
use Doctrine\ORM\Query;
use KonstantinKuklin\DoctrineDto\DtoClassMap;
use KonstantinKuklin\DoctrineDto\Configuration\EntityDtoMap;
use KonstantinKuklin\DoctrineDto\Configuration\MapInterface;
use PDO;

/**
 * Class DtoHydrator
 * Hydrate from Database to Data Transfer Objects
 */
final class DtoHydrator extends AbstractHydrator
{
    const INITIALIZED_PROPERTY = '_isInitialized';

    /*
     * Local ClassMetadata cache to avoid going to the EntityManager all the time.
     * This local cache is maintained between hydration runs and not cleared.
     */
    private $_ce = array();

    /* The following parts are reinitialized on every hydration run. */
    private $_identifierMap;
    private $_resultPointers;
    private $_idTemplate;
    private $_resultCounter;
    private $_rootAliases = array();
    private $identityMap = array();

    /**
     * @var MapInterface
     */
    private $dtoMap;

    private static $dtoClassMap = array();

    protected function prepare()
    {
        $this->_identifierMap =
        $this->_resultPointers =
        $this->_idTemplate = array();

        $this->_resultCounter = 0;
        $this->dtoMap = DtoClassMap::getMapDto();

        foreach ($this->_rsm->aliasMap as $dqlAlias => $className) {
            $this->_identifierMap[$dqlAlias] = array();
            $this->_idTemplate[$dqlAlias] = '';

            if (!isset($this->_ce[$className])) {
                $this->_ce[$className] = $this->_em->getClassMetadata($className);
            }

            // Remember which associations are "fetch joined", so that we know where to inject
            // collection stubs or proxies and where not.
            if (!isset($this->_rsm->relationMap[$dqlAlias])) {
                continue;
            }

            if (!isset($this->_rsm->aliasMap[$this->_rsm->parentAliasMap[$dqlAlias]])) {
                throw HydrationException::parentObjectOfRelationNotFound(
                    $dqlAlias,
                    $this->_rsm->parentAliasMap[$dqlAlias]
                );
            }

            $sourceClassName = $this->_rsm->aliasMap[$this->_rsm->parentAliasMap[$dqlAlias]];
            $sourceClass = $this->_getClassMetadata($sourceClassName);
            $assoc = $sourceClass->associationMappings[$this->_rsm->relationMap[$dqlAlias]];

            $this->_hints['fetched'][$this->_rsm->parentAliasMap[$dqlAlias]][$assoc['fieldName']] = true;

            if ($assoc['type'] === ClassMetadata::MANY_TO_MANY) {
                continue;
            }

            // Mark any non-collection opposite sides as fetched, too.
            if ($assoc['mappedBy']) {
                $this->_hints['fetched'][$dqlAlias][$assoc['mappedBy']] = true;

                continue;
            }

            // handle fetch-joined owning side bi-directional one-to-one associations
            if ($assoc['inversedBy']) {
                $class = $this->_ce[$className];
                $inverseAssoc = $class->associationMappings[$assoc['inversedBy']];

                if (!($inverseAssoc['type'] & ClassMetadata::TO_ONE)) {
                    continue;
                }

                $this->_hints['fetched'][$dqlAlias][$inverseAssoc['fieldName']] = true;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function cleanup()
    {
        parent::cleanup();
        $this->_identifierMap =
        $this->_initializedCollections =
        $this->_existingCollections =
        $this->_resultPointers = array();
    }

    /**
     * {@inheritdoc}
     */
    protected function hydrateAllData()
    {
        $result = array();
        $cache = array();

        while ($row = $this->_stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->_hydrateRow($row, $cache, $result);
        }

        return $result;
    }

    /**
     * Gets an entity instance.
     *
     * @param array  $data     The instance data.
     * @param string $dqlAlias The DQL alias of the entity's class.
     *
     * @return object The entity.
     */
    private function _getDto(array $data, $dqlAlias)
    {
        $classNameEntity = $this->_rsm->aliasMap[$dqlAlias];
        $className = $this->_getDtoPath($classNameEntity);

        // TODO cache here

        return $this->_createDto($className, $classNameEntity, $data, $this->_hints);
    }

    /**
     * @param string $classNameDto    The name of the dto class.
     * @param string $classNameEntity The name of the entity class.
     * @param array  $data            The data for the entity.
     * @param array  $hints           Any hints to account for during reconstitution/lookup of the entity.
     *
     * @return object
     */
    private function _createDto($classNameDto, $classNameEntity, array $data, &$hints = array())
    {
        $isInitializedPropertyPath = self::INITIALIZED_PROPERTY;
        $class = $this->_em->getClassMetadata($classNameEntity);

        if ($class->isIdentifierComposite) {
            $id = array();
            foreach ($class->identifier as $fieldName) {
                if (isset($class->associationMappings[$fieldName])) {
                    $id[$fieldName] = $data[$class->associationMappings[$fieldName]['joinColumns'][0]['name']];
                } else {
                    $id[$fieldName] = $data[$fieldName];
                }
            }
            $idHash = implode(' ', $id);
        } else {
            if (isset($class->associationMappings[$class->identifier[0]])) {
                $idHash = $data[$class->associationMappings[$class->identifier[0]]['joinColumns'][0]['name']];
            } else {
                $idHash = $data[$class->identifier[0]];
            }
        }

        // try to get object from Cache
        if (isset($this->identityMap[$class->rootEntityName][$idHash])) {
            $dto = $this->identityMap[$class->rootEntityName][$idHash];

            return $dto;
        } else {
            $dto = new $classNameDto();

            $this->identityMap[$class->rootEntityName][$idHash] = $dto;

            if (property_exists($classNameDto, $isInitializedPropertyPath)) {
                $dto->$isInitializedPropertyPath = true;
            }
        }

        foreach ($data as $field => $value) {
            if (isset($class->fieldMappings[$field])) {
                $dto->$field = $value;
            }
        }

        // Properly initialize any unfetched associations, if partial objects are not allowed.
        foreach ($class->associationMappings as $field => $assoc) {
            // Check if the association is not among the fetch-joined associations already.
            if (isset($hints['fetchAlias'], $hints['fetched'][$hints['fetchAlias']][$field])) {
                continue;
            }

            $targetClass = $this->_em->getClassMetadata($assoc['targetEntity']);

            if ($assoc['type'] & ClassMetadata::TO_ONE) {
                if ($assoc['isOwningSide']) {
                    $associatedId = array();
                    // TODO: Is this even computed right in all cases of composite keys?
                    foreach ($assoc['targetToSourceKeyColumns'] as $targetColumn => $srcColumn) {
                        $joinColumnValue = isset($data[$srcColumn]) ? $data[$srcColumn] : null;
                        if ($joinColumnValue !== null) {
                            if ($targetClass->containsForeignIdentifier) {
                                // understood TODO
                            } else {
                                $associatedId[$targetClass->fieldNames[$targetColumn]] = $joinColumnValue;
                            }
                        }
                    }
                    if (!$associatedId) {
                        // Foreign key is NULL
                        $dto->$field = null;
                    } else {
                        if (!isset($hints['fetchMode'][$class->name][$field])) {
                            $hints['fetchMode'][$class->name][$field] = $assoc['fetch'];
                        }

                        // Foreign key is set
                        // Check identity map first
                        // FIXME: Can break easily with composite keys if join column values are in
                        //        wrong order. The correct order is the one in ClassMetadata#identifier.
                        $relatedIdHash = implode(' ', $associatedId);

                        if ($targetClass->subClasses) {
                            // If it might be a subtype, it can not be lazy. There isn't even
                            // a way to solve this with deferred eager loading, which means putting
                            // an entity with subclasses at a *-to-one location is really bad! (performance-wise)
                            // understood TODO

                            // TODO need to understand and fix
                            $newValueDto = null;
                        } else {
                            // Deferred eager load only works for single identifier classes

                            if ($hints['fetchMode'][$class->name][$field] == ClassMetadata::FETCH_EAGER) {
                                $newValueEntityPath = $assoc['targetEntity'];
                                $newValueDtoPath = $this->_getDtoPath($newValueEntityPath);
                                $newValueDto = new $newValueDtoPath;
                                $identifierFieldNames = $targetClass->getIdentifierFieldNames();
                                $identifierFieldName = array_pop($identifierFieldNames);
                                $newValueDto->$identifierFieldName = $associatedId;
                            } else {
                                $newValueEntityPath = $assoc['targetEntity'];
                                $newValueDtoPath = $this->_getDtoPath($newValueEntityPath);
                                $newValueDto = new $newValueDtoPath;
                                $identifierFieldNames = $targetClass->getIdentifierFieldNames();

                                foreach ($identifierFieldNames as $identifierFieldName) {
                                    if (!isset($associatedId[$identifierFieldName])) {
                                        // foreign key was not loaded
                                        continue;
                                    }

                                    $newValueDto->$identifierFieldName = $associatedId[$identifierFieldName];
                                }
                            }
                            // PERF: Inlined & optimized code from UnitOfWork#registerManaged()
                            $this->identityMap[$targetClass->rootEntityName][$relatedIdHash] = $newValueDto;
                        }
                        $dto->$field = $newValueDto;

                        if ($assoc['inversedBy'] && $assoc['type'] & ClassMetadata::ONE_TO_ONE) {
                            $inverseAssoc = $targetClass->associationMappings[$assoc['inversedBy']];
                            $inverseFieldName = $inverseAssoc['fieldName'];
                            $newValueDto->$inverseFieldName = $dto;
                        }
                    }
                } else {
                    // Inverse side of x-to-one can never be lazy
                    //TODO understand what is it
                }
            }
        }

        return $dto;
    }

    /**
     * @param string $entityClassPath
     *
     * @return string
     */
    private function _getDtoPath($entityClassPath)
    {
        $entityClassPathNormalized = $entityClassPath = trim($entityClassPath, "\\");

        if (!isset(self::$dtoClassMap[$entityClassPathNormalized])) {
            self::$dtoClassMap[$entityClassPathNormalized] = $this->dtoMap->getPath($entityClassPathNormalized);
        }

        return self::$dtoClassMap[$entityClassPathNormalized];
    }

    /**
     * Gets a ClassMetadata instance from the local cache.
     * If the instance is not yet in the local cache, it is loaded into the
     * local cache.
     *
     * @param string $className The name of the class.
     *
     * @return ClassMetadata
     */
    private function _getClassMetadata($className)
    {
        if (!isset($this->_ce[$className])) {
            $this->_ce[$className] = $this->_em->getClassMetadata($className);
        }

        return $this->_ce[$className];
    }

    /**
     * Hydrates a single row in an SQL result set.
     *
     * @internal
     * First, the data of the row is split into chunks where each chunk contains data
     * that belongs to a particular component/class. Afterwards, all these chunks
     * are processed, one after the other. For each chunk of class data only one of the
     * following code paths is executed:
     *
     * Path A: The data chunk belongs to a joined/associated object and the association
     *         is collection-valued.
     * Path B: The data chunk belongs to a joined/associated object and the association
     *         is single-valued.
     * Path C: The data chunk belongs to a root result element/object that appears in the topmost
     *         level of the hydrated result. A typical example are the objects of the type
     *         specified by the FROM clause in a DQL query.
     *
     * @param array $data   The data of the row to process.
     * @param array $cache  The cache to use.
     * @param array $result The result array to fill.
     */
    protected function _hydrateRow(array $data, array &$cache, array &$result)
    {
        // Initialize
        $id = $this->_idTemplate; // initialize the id-memory
        $nonemptyComponents = array();
        // Split the row data into chunks of class data.
        $rowData = $this->gatherRowData($data, $cache, $id, $nonemptyComponents);

        // Extract scalar values. They're appended at the end.
        if (isset($rowData['scalars'])) {
//            $scalars = $rowData['scalars'];
            unset($rowData['scalars']);
            if (!$rowData) {
                ++$this->_resultCounter;
            }
        }

        $this->_hydrateChunks($result, $rowData, $nonemptyComponents, $id);


        // Append scalar values to mixed result sets
//        if (isset($scalars)) {
//            foreach ($scalars as $name => $value) {
//                $result[$this->_resultCounter - 1][$name] = $value;
//            }
//        }
    }

    /**
     * @param array $result
     * @param array $rowData
     * @param mixed $nonemptyComponents
     * @param int   $id
     */
    private function _hydrateChunks(array &$result, array $rowData, $nonemptyComponents, $id)
    {
        // hydrate the data chunks
        foreach ($rowData as $dqlAlias => $data) {
            $entityName = $this->_rsm->aliasMap[$dqlAlias];

            if (isset($this->_rsm->parentAliasMap[$dqlAlias])) {
                // It's a joined result

                $parentAlias = $this->_rsm->parentAliasMap[$dqlAlias];
                // we need the $path to save into the identifier map which entities were already
                // seen for this parent-child relationship
                $path = $parentAlias . '.' . $dqlAlias;

                // We have a RIGHT JOIN result here. Doctrine cannot hydrate RIGHT JOIN Object-Graphs
                if (!isset($nonemptyComponents[$parentAlias])) {
                    // TODO: Add special case code where we hydrate the right join objects into identity map at least
                    continue;
                }

                // Get a reference to the parent object to which the joined element belongs.
                if ($this->_rsm->isMixed && isset($this->_rootAliases[$parentAlias])) {
                    $first = reset($this->_resultPointers);
                    $parentObject = $first[key($first)];
                } else {
                    if (isset($this->_resultPointers[$parentAlias])) {
                        $parentObject = $this->_resultPointers[$parentAlias];
                    } else {
                        // Parent object of relation not found, so skip it.
                        continue;
                    }
                }

                $parentClass = $this->_ce[$this->_rsm->aliasMap[$parentAlias]];
                $oid = spl_object_hash($parentObject);
                $relationField = $this->_rsm->relationMap[$dqlAlias];
                $relation = $parentClass->associationMappings[$relationField];
                $reflField = $parentClass->reflFields[$relationField];

                // Check the type of the relation (many or single-valued)
                if (!($relation['type'] & ClassMetadata::TO_ONE)) {
                    $reflFieldValue = $parentObject->$relationField;
                    // PATH A: Collection-valued association
                    if (isset($nonemptyComponents[$dqlAlias])) {
                        $indexExists = isset($this->_identifierMap[$path][$id[$parentAlias]][$id[$dqlAlias]]);
                        $index = $indexExists ? $this->_identifierMap[$path][$id[$parentAlias]][$id[$dqlAlias]] : false;
                        $indexIsValid = $index !== false ? isset($reflFieldValue[$index]) : false;

                        if (!$indexExists || !$indexIsValid) {
                            $element = $this->_getDto($data, $dqlAlias);

                            $parentField = &$parentObject->$relationField;
                            $parentField[] = $element;

                            // Update result pointer
                            $this->_resultPointers[$dqlAlias] = $element;
                        } else {
                            // Update result pointer
                            $this->_resultPointers[$dqlAlias] = $reflFieldValue[$index];
                        }
                    }

                } else {
                    // PATH B: Single-valued association
                    $reflFieldValue = $reflField->getValue($parentObject);
                    if (!$reflFieldValue || isset($this->_hints[Query::HINT_REFRESH]) || ($reflFieldValue instanceof Proxy && !$reflFieldValue->__isInitialized__)) {
                        // we only need to take action if this value is null,
                        // we refresh the entity or its an unitialized proxy.
                        if (isset($nonemptyComponents[$dqlAlias])) {
                            $element = $this->_getDto($data, $dqlAlias);
                            $parentObject->$relationField = $element;
                            //$this->_uow->setOriginalEntityProperty($oid, $relationField, $element);
                            $targetClass = $this->_ce[$relation['targetEntity']];
                            if ($relation['isOwningSide']) {
                                //TODO: Just check hints['fetched'] here?
                                // If there is an inverse mapping on the target class its bidirectional
                                if ($relation['inversedBy']) {
                                    $inverseAssoc = $targetClass->associationMappings[$relation['inversedBy']];
                                    if ($inverseAssoc['type'] & ClassMetadata::TO_ONE) {
                                        $targetClass->reflFields[$inverseAssoc['fieldName']]->setValue(
                                            $element,
                                            $parentObject
                                        );

                                    }
                                } else {
                                    if ($parentClass === $targetClass && $relation['mappedBy']) {
                                        // Special case: bi-directional self-referencing one-one on the same class
                                        $targetClass->reflFields[$relationField]->setValue($element, $parentObject);
                                    }
                                }
                            } else {
                                // For sure bidirectional, as there is no inverse side in unidirectional mappings
                                $targetClass->reflFields[$relation['mappedBy']]->setValue($element, $parentObject);
                                $this->_uow->setOriginalEntityProperty(
                                    spl_object_hash($element),
                                    $relation['mappedBy'],
                                    $parentObject
                                );
                            }
                            // Update result pointer
                            $this->_resultPointers[$dqlAlias] = $element;
                        }
                    } else {
                        // Update result pointer
                        $this->_resultPointers[$dqlAlias] = $reflFieldValue;
                    }
                }
            } else {
                // PATH C: Its a root result element
                $this->_rootAliases[$dqlAlias] = true; // Mark as root alias

                // if this row has a NULL value for the root result id then make it a null result.
                if (!isset($nonemptyComponents[$dqlAlias])) {
                    if ($this->_rsm->isMixed) {
                        $result[] = array(0 => null);
                    } else {
                        $result[] = null;
                    }
                    ++$this->_resultCounter;
                    continue;
                }

                // check for existing result from the iterations before
                if (!isset($this->_identifierMap[$dqlAlias][$id[$dqlAlias]])) {
                    $element = $this->_getDto($rowData[$dqlAlias], $dqlAlias);
                    if (isset($this->_rsm->indexByMap[$dqlAlias])) {
                        $field = $this->_rsm->indexByMap[$dqlAlias];
                        $key = $this->_ce[$entityName]->reflFields[$field]->getValue($element);
                        if ($this->_rsm->isMixed) {
                            $element = array($key => $element);
                            $result[] = $element;
                            $this->_identifierMap[$dqlAlias][$id[$dqlAlias]] = $this->_resultCounter;
                            ++$this->_resultCounter;
                        } else {
                            $result[$key] = $element;
                            $this->_identifierMap[$dqlAlias][$id[$dqlAlias]] = $key;
                        }
                    } else {
                        $result[] = $element;
                        $this->_identifierMap[$dqlAlias][$id[$dqlAlias]] = $this->_resultCounter;
                        ++$this->_resultCounter;
                    }

                    // Update result pointer
                    $this->_resultPointers[$dqlAlias] = $element;

                } else {
                    // Update result pointer
                    $index = $this->_identifierMap[$dqlAlias][$id[$dqlAlias]];
                    $this->_resultPointers[$dqlAlias] = $result[$index];
                }
            }
        }
    }
}