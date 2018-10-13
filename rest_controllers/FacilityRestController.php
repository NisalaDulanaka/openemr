<?php
/**
 * FacilityRestController
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

namespace OpenEMR\RestControllers;

use OpenEMR\Services\FacilityService;

class FacilityRestController
{
    private $facilityService;

    public function __construct()
    {
        $this->facilityService = new FacilityService();
    }

    public function getOne($id)
    {
        $serviceResult = $this->facilityService->getById($id);

        if ($serviceResult) {
            http_response_code(200);
            return $serviceResult;
        }

        http_response_code(400);
    }

    public function getAll()
    {
        $serviceResult = $this->facilityService->getAll();

        if ($serviceResult) {
            http_response_code(200);
            return $serviceResult;
        }

        http_response_code(500);
    }

    public function post($data)
    {
        $validationResult = $this->facilityService->validate($data);

        if (!$validationResult->isValid()) {
            http_response_code(400);
            return $validationResult->getMessages();
        }

        $serviceResult = $this->facilityService->insert($data);

        if ($serviceResult) {
            http_response_code(201);
            return array('id' => $serviceResult);
        }

        http_response_code(400);
    }

    public function put($data)
    {
        $validationResult = $this->facilityService->validate($data);

        if (!$validationResult->isValid()) {
            http_response_code(400);
            return $validationResult->getMessages();
        }

        $serviceResult = $this->facilityService->update($data);

        if ($serviceResult) {
            http_response_code(200);
            return array('id' => $data['fid']);
        }

        http_response_code(400);
    }
}