<?php
/**
 * ListService
 *
 * Copyright (C) 2018 Matthew Vita <matthewvita48@gmail.com>
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://opensource.org/licenses/gpl-license.php>;.
 *
 * @package OpenEMR
 * @author  Matthew Vita <matthewvita48@gmail.com>
 * @link    http://www.open-emr.org
 */

namespace OpenEMR\Services;

class ListService
{

  /**
   * Default constructor.
   */
    public function __construct()
    {
    }


    public function getAll($pid, $list_type)
    {
        $sql = "SELECT * FROM lists WHERE pid=? AND type=? ORDER BY date DESC";

        $statementResults = sqlStatement($sql, array($pid, $list_type));

        $results = array();
        while ($row = sqlFetchArray($statementResults)) {
            array_push($results, $row);
        }

        return $results;
    }

    public function getOne($pid, $list_type, $list_id)
    {
        $sql = "SELECT * FROM lists WHERE pid=? AND type=? AND id=? ORDER BY date DESC";

        return sqlQuery($sql, array($pid, $list_type, $list_id));
    }
}
