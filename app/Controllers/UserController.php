<?php

declare(strict_types=1);

namespace App\Controllers;

use Conduit\Http\Request;
use Conduit\Http\JsonResponse;

class UserController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        return JsonResponse::success([
            'message' => 'UserController@index'
        ]);
    }
    
    /**
     * Store a newly created resource.
     */
    public function store(Request $request): JsonResponse
    {
        return JsonResponse::created([
            'message' => 'UserController@store'
        ]);
    }
    
    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        return JsonResponse::success([
            'id' => $id,
            'message' => 'UserController@show'
        ]);
    }
    
    /**
     * Update the specified resource.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        return JsonResponse::success([
            'id' => $id,
            'message' => 'UserController@update'
        ]);
    }
    
    /**
     * Remove the specified resource.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        return JsonResponse::noContent();
    }
}
