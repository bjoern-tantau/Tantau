<?php
namespace Dryspell\Models\Backends;

use DateTime;
use Doctrine\DBAL\Connection;
use Dryspell\Models\BackendInterface;
use Dryspell\Models\ObjectInterface;
use Illuminate\Support\Str;
use PDO;
use ReflectionClass;

/**
 * Model backend using the Doctrine DBAL.
 *
 * @author Björn Tantau <bjoern@bjoern-tantau.de>
 */
class Doctrine implements BackendInterface
{

    /**
     * @var Connection
     */
    private $conn;

    /**
     * Initialise Backend
     *
     * @param Connection $conn
     */
    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Search data for the given object. Returns objects.
     *
     * @param ObjectInterface $object
     * @param int|string|array $term Integer or string searches for the objects id.
     * Array searches for the given property key with the given value.
     * @return iterable
     */
    public function find(ObjectInterface $object, $term = null): iterable
    {
        $query = $this->conn->createQueryBuilder();
        $query->select('*')
            ->from($this->getTableName($object));
        if (is_null($term)) {
            $term = [];
        }
        if (!is_array($term)) {
            $term = [$object->getIdProperty() => $term];
        }
        foreach ($term as $column => $value) {
            $query->andWhere($this->conn->quoteIdentifier($column) . ' LIKE ?');
        }
        $query->setParameters(array_values($term));
        $stmt = $query->execute();
        while ($row  = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $new_object = clone $object;
            foreach ($row as $key => $value) {
                $this->setProperty($new_object, $key, $value);
            }
            yield $new_object;
        }
    }

    /**
     * Save the data in the given object to a file or database.
     *
     * @param ObjectInterface $object
     * @return BackendInterface
     */
    public function save(ObjectInterface $object): BackendInterface
    {
        $table = $this->getTableName($object);
        $this->conn->transactional(function (Connection $conn) use ($table, $object) {
            $values = array_map([$this, 'getValueForDatabase'], $object->getValues());
            if ($id     = $object->{$object->getIdProperty()}) {
                $query = $conn->createQueryBuilder();
                $query->select($object->getIdProperty())
                    ->from($table)
                    ->where($conn->quoteIdentifier($object->getIdProperty()) . ' = :id')
                    ->setParameter('id', $id);
                if ($id == $query->execute()->fetchColumn()) {
                    $conn->update($table, $values,
                        [$object->getIdProperty() => $id]);
                    return;
                }
                throw new Exception('Object with id ' . $id . ' does not exist anymore.',
                        Exception::NOT_EXISTS);
            }

            $conn->insert($table,
                array_filter($values,
                    function ($value) {
                        return !is_null($value);
                    }));
            $id = $conn->lastInsertId();
            $this->setProperty($object, $object->getIdProperty(), $id);
        });

        return $this;
    }

    private function getTableName($class)
    {
        $reflect = new ReflectionClass($class);
        return Str::snake($reflect->getShortName());
    }

    private function setProperty(ObjectInterface $object, string $property, $value)
    {
        $object->setWeaklyTyped($property, $value);
        return $this;
    }

    private function getValueForDatabase($value)
    {
        if ($value instanceof ObjectInterface) {
            $value = $value->{$value->getIdProperty()};
        }
        if ($value instanceof DateTime) {
            $value = $value->format("Y-m-d H:i:s");
        }
        if (is_object($value)) {
            $value = strval($value);
        }
        if (is_array($value)) {
            $value = serialize($value);
        }
        return $value;
    }

    /**
     * Remove the data associated with the given object.
     *
     * @param ObjectInterface $object
     * @return Doctrine
     */
    public function delete(ObjectInterface $object): BackendInterface
    {
        $table = $this->getTableName($object);
        $this->conn->transactional(function (Connection $conn) use ($table, $object) {
            if ($id = $object->{$object->getIdProperty()}) {
                $query = $conn->createQueryBuilder();
                $query->delete($table)
                    ->where($conn->quoteIdentifier($object->getIdProperty()) . ' = :id')
                    ->setParameter('id', $id);
                $query->execute();
            }
        });
        return $this;
    }
}
