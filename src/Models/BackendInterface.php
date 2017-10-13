<?php

namespace Dryspell\Models;

/**
 * Backend for actually saving and retrieving
 * data to/from a file or database.
 *
 * @author Björn Tantau <bjoern@bjoern-tantau.de>
 */
interface BackendInterface
{

    /**
     * Create migration lines to get from current state
     * of the databse (or file) to the required state.
     *
     * @param ObjectInterface $object
     * @return array
     */
    public function createMigration(ObjectInterface $object): array;

    /**
     * Save the data in the given object to a file or database.
     *
     * @param ObjectInterface $object
     * @return BackendInterface
     */
    public function save(ObjectInterface $object): self;

    /**
     * Search data for the given object. Returns arrays.
     *
     * @param ObjectInterface $object
     * @param int|string|array $term Integer or string searches for the objects id.
     * Array searches for the given property key with the given value.
     * @return iterable
     */
    public function find(ObjectInterface $object, $term): iterable;
}