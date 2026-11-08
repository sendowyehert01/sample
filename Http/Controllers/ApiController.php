<?php

namespace Http\Controllers;

use Core\Response;

class ApiController
{
    public function index(): Response
    {
        return Response::json(['message' => 'CRUD API', 'resource' => '/samples']);
    }
}
