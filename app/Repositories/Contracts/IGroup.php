<?php

namespace App\Repositories\Contracts;

use Illuminate\Http\Request;

interface IGroup {
    public function getGroups($itemsPerPage = 0);
    public function getGroup($id);
    public function getUserGroups($userId, $itemsPerPage = 0);

    public function create(Array $data, $userId = "");
    public function update(Array $data, $groupId);
    public function delete($groupId);
}
