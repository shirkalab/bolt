<?php

namespace Bolt\Storage\Repository;

use Bolt\Storage\Entity\Entity;
use Bolt\Storage\Repository;

class FieldValueRepository extends Repository
{
    /**
     * @param int    $id
     * @param string $contentType
     * @param string $field
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryExistingFields($id, $contentType, $field)
    {
        $query = $this->createQueryBuilder('fv')
            ->select('fv.grouping', 'fv.id', 'fv.name')
            ->where('fv.content_id = :id')
            ->andWhere('fv.contenttype = :contenttype')
            ->andWhere('fv.name = :name')
            ->orderBy('fv.grouping', 'ASC')
            ->setParameters([
                'id'          => $id,
                'contenttype' => $contentType,
                'name'        => $field,
            ]);

        return $query;
    }

    /**
     * @param int    $id
     * @param string $contentType
     * @param string $field
     *
     * @return Entity[]
     */
    public function getExistingFields($id, $contentType, $field)
    {
        $query = $this->queryExistingFields($id, $contentType, $field);
        $results = $query->execute()->fetchAll();

        $fields = [];

        if (!$results) {
            return $fields;
        }

        foreach ($results as $result) {
            $fields[$result['grouping']][] = $result['id'];
        }

        return $fields;
    }
}
