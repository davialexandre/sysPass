<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Repositories\Category;

use SP\Core\Exceptions\SPException;
use SP\DataModel\CategoryData;
use SP\DataModel\ItemSearchData;
use SP\Repositories\DuplicatedItemException;
use SP\Repositories\Repository;
use SP\Repositories\RepositoryItemInterface;
use SP\Repositories\RepositoryItemTrait;
use SP\Storage\Database\QueryData;
use SP\Storage\Database\QueryResult;

/**
 * Class CategoryRepository
 *
 * @package SP\Repositories\Category
 */
class CategoryRepository extends Repository implements RepositoryItemInterface
{
    use RepositoryItemTrait;

    /**
     * Creates an item
     *
     * @param CategoryData $itemData
     *
     * @return int
     * @throws SPException
     * @throws DuplicatedItemException
     */
    public function create($itemData)
    {
        if ($this->checkDuplicatedOnAdd($itemData)) {
            throw new DuplicatedItemException(__u('Categoría duplicada'), DuplicatedItemException::WARNING);
        }

        $queryData = new QueryData();
        $queryData->setQuery('INSERT INTO Category SET `name` = ?, description = ?, `hash` = ?');
        $queryData->setParams([
            $itemData->getName(),
            $itemData->getDescription(),
            $this->makeItemHash($itemData->getName(), $this->db->getDbHandler())
        ]);
        $queryData->setOnErrorMessage(__u('Error al crear la categoría'));

        return $this->db->doQuery($queryData)->getLastId();
    }

    /**
     * Checks whether the item is duplicated on adding
     *
     * @param CategoryData $itemData
     *
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function checkDuplicatedOnAdd($itemData)
    {
        $queryData = new QueryData();
        $queryData->setQuery('SELECT id FROM Category WHERE `hash` = ? OR `name` = ?');
        $queryData->setParams([
            $this->makeItemHash($itemData->getName(), $this->db->getDbHandler()),
            $itemData->getName()
        ]);

        return $this->db->doQuery($queryData)->getNumRows() > 0;
    }

    /**
     * Updates an item
     *
     * @param CategoryData $itemData
     *
     * @return int
     * @throws DuplicatedItemException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function update($itemData)
    {
        if ($this->checkDuplicatedOnUpdate($itemData)) {
            throw new DuplicatedItemException(__u('Nombre de categoría duplicado'), DuplicatedItemException::WARNING);
        }

        $query = /** @lang SQL */
            'UPDATE Category
              SET `name` = ?,
              description = ?,
              `hash` = ?
              WHERE id = ? LIMIT 1';

        $queryData = new QueryData();
        $queryData->setQuery($query);
        $queryData->setParams([
            $itemData->getName(),
            $itemData->getDescription(),
            $this->makeItemHash($itemData->getName(), $this->db->getDbHandler()),
            $itemData->getId()
        ]);
        $queryData->setOnErrorMessage(__u('Error al actualizar la categoría'));

        return $this->db->doQuery($queryData)->getAffectedNumRows();
    }

    /**
     * Checks whether the item is duplicated on updating
     *
     * @param CategoryData $itemData
     *
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function checkDuplicatedOnUpdate($itemData)
    {
        $queryData = new QueryData();
        $queryData->setQuery('SELECT id FROM Category WHERE (`hash` = ? OR `name` = ?) AND id <> ?');
        $queryData->setParams([
            $this->makeItemHash($itemData->getName(), $this->db->getDbHandler()),
            $itemData->getName(),
            $itemData->getId()
        ]);

        return $this->db->doQuery($queryData)->getNumRows() > 0;
    }

    /**
     * Returns the item for given id
     *
     * @param int $id
     *
     * @return CategoryData
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function getById($id)
    {
        $queryData = new QueryData();
        $queryData->setMapClassName(CategoryData::class);
        $queryData->setQuery('SELECT id, `name`, description FROM Category WHERE id = ? LIMIT 1');
        $queryData->addParam($id);

        return $this->db->doSelect($queryData)->getData();
    }

    /**
     * Returns the item for given id
     *
     * @param string $name
     *
     * @return CategoryData
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function getByName($name)
    {
        $queryData = new QueryData();
        $queryData->setMapClassName(CategoryData::class);
        $queryData->setQuery('SELECT id, `name`, description FROM Category WHERE `name` = ? OR `hash` = ? LIMIT 1');
        $queryData->setParams([
            $name,
            $this->makeItemHash($name, $this->db->getDbHandler())
        ]);

        return $this->db->doSelect($queryData)->getData();
    }

    /**
     * Returns all the items
     *
     * @return CategoryData[]
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function getAll()
    {
        $queryData = new QueryData();
        $queryData->setMapClassName(CategoryData::class);
        $queryData->setQuery('SELECT id, `name`, description, `hash` FROM Category ORDER BY `name`');

        return $this->db->doSelect($queryData)->getDataAsArray();
    }

    /**
     * Returns all the items for given ids
     *
     * @param array $ids
     *
     * @return array
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function getByIdBatch(array $ids)
    {
        if (empty($ids)) {
            return [];
        }

        $query = /** @lang SQL */
            'SELECT id, `name`, description FROM Category WHERE id IN (' . $this->getParamsFromArray($ids) . ')';

        $queryData = new QueryData();
        $queryData->setMapClassName(CategoryData::class);
        $queryData->setQuery($query);
        $queryData->setParams($ids);

        return $this->db->doSelect($queryData)->getDataAsArray();
    }

    /**
     * Deletes all the items for given ids
     *
     * @param array $ids
     *
     * @return int
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function deleteByIdBatch(array $ids)
    {
        $queryData = new QueryData();
        $queryData->setQuery('DELETE FROM Category WHERE id IN (' . $this->getParamsFromArray($ids) . ')');
        $queryData->setParams($ids);
        $queryData->setOnErrorMessage(__u('Error al eliminar la categorías'));

        return $this->db->doQuery($queryData)->getAffectedNumRows();
    }

    /**
     * Deletes an item
     *
     * @param $id
     *
     * @return int
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function delete($id)
    {
        $query = /** @lang SQL */
            'DELETE FROM Category WHERE id = ? LIMIT 1';

        $queryData = new QueryData();
        $queryData->setQuery($query);
        $queryData->addParam($id);
        $queryData->setOnErrorMessage(__u('Error al eliminar la categoría'));

        return $this->db->doQuery($queryData)->getAffectedNumRows();
    }

    /**
     * Checks whether the item is in use or not
     *
     * @param $id int
     *
     * @return void
     */
    public function checkInUse($id)
    {
        throw new \RuntimeException('Not implemented');
    }

    /**
     * Searches for items by a given filter
     *
     * @param ItemSearchData $itemSearchData
     *
     * @return QueryResult
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function search(ItemSearchData $itemSearchData)
    {
        $queryData = new QueryData();
        $queryData->setSelect('id, name, description');
        $queryData->setFrom('Category');
        $queryData->setOrder('name');

        if ($itemSearchData->getSeachString() !== '') {
            $queryData->setWhere('name LIKE ? OR description LIKE ?');

            $search = '%' . $itemSearchData->getSeachString() . '%';
            $queryData->addParam($search);
            $queryData->addParam($search);
        }

        $queryData->setLimit('?,?');
        $queryData->addParam($itemSearchData->getLimitStart());
        $queryData->addParam($itemSearchData->getLimitCount());

        return $this->db->doSelect($queryData, true);
    }
}