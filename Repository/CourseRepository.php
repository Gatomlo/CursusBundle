<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CursusBundle\Repository;

use Claroline\CursusBundle\Entity\Cursus;
use Doctrine\ORM\EntityRepository;

class CourseRepository extends EntityRepository
{
    public function findAllCourses(
        $orderedBy = 'title',
        $order = 'ASC',
        $executeQuery = true
    )
    {
        $dql = "
            SELECT c
            FROM Claroline\CursusBundle\Entity\Course c
            ORDER BY c.{$orderedBy} {$order}
        ";
        $query = $this->_em->createQuery($dql);

        return $executeQuery ? $query->getResult() : $query;
    }

    public function findSearchedCourses(
        $search = '',
        $orderedBy = 'title',
        $order = 'ASC',
        $executeQuery = true
    )
    {
        $dql = "
            SELECT c
            FROM Claroline\CursusBundle\Entity\Course c
            WHERE UPPER(c.title) LIKE :search
            OR UPPER(c.code) LIKE :search
            OR UPPER(c.description) LIKE :search
            ORDER BY c.{$orderedBy} {$order}
        ";
        $query = $this->_em->createQuery($dql);
        $upperSearch = strtoupper($search);
        $query->setParameter('search', "%{$upperSearch}%");

        return $executeQuery ? $query->getResult() : $query;
    }

    public function findUnmappedCoursesByCursus(
        Cursus $cursus,
        $orderedBy = 'title',
        $order = 'ASC',
        $executeQuery = true
    )
    {
        $dql = "
            SELECT c
            FROM Claroline\CursusBundle\Entity\Course c
            WHERE NOT EXISTS (
                SELECT cc
                FROM Claroline\CursusBundle\Entity\Cursus cc
                WHERE cc.course = c
                AND cc.parent = :cursus
            )
            ORDER BY c.{$orderedBy} {$order}
        ";
        $query = $this->_em->createQuery($dql);
        $query->setParameter('cursus', $cursus);

        return $executeQuery ? $query->getResult() : $query;
    }

    public function findUnmappedSearchedCoursesByCursus(
        Cursus $cursus,
        $search = '',
        $orderedBy = 'title',
        $order = 'ASC',
        $executeQuery = true
    )
    {
        $dql = "
            SELECT c
            FROM Claroline\CursusBundle\Entity\Course c
            WHERE (
                UPPER(c.title) LIKE :search
                OR UPPER(c.code) LIKE :search
            )
            AND NOT EXISTS (
                SELECT cc
                FROM Claroline\CursusBundle\Entity\Cursus cc
                WHERE cc.course = c
                AND cc.parent = :cursus
            )
            ORDER BY c.{$orderedBy} {$order}
        ";
        $query = $this->_em->createQuery($dql);
        $query->setParameter('cursus', $cursus);
        $upperSearch = strtoupper($search);
        $query->setParameter('search', "%{$upperSearch}%");

        return $executeQuery ? $query->getResult() : $query;
    }
}
