<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArchiveIndexRequest;
use App\Services\ArchiveService;

class ArchiveController extends Controller
{
    public function index(ArchiveIndexRequest $request, ArchiveService $archives)
    {
        return $archives->index($request, institutionalRoute: true);
    }
}
