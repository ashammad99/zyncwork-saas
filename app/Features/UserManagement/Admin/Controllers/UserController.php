<?php

namespace App\Features\UserManagement\Admin\Controllers;

use App\Core\BaseController;
use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Features\UserManagement\Admin\Services\UserService;
use App\Features\UserManagement\Admin\Requests\StoreUserRequest;
use App\Features\UserManagement\Admin\Requests\UpdateUserRequest;

class UserController extends BaseController
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Display a listing of the users.
     */
    public function index(Request $request)
    {
        $users = $this->userService->getPaginatedUsers($request->only(['search']));

        return Inertia::render('(portals)/admin/users/page', [
            'users' => $users,
            'departments' => Department::orderBy('name')->get(['id', 'name']),

            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request)
    {

        $this->userService->createUser($request->validated());

        return redirect()->back()->with('success', 'User created successfully.');
    }

    /**
     * Update the specified user details.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'You cannot modify your own core privileges from this page.');
        }

        $this->userService->updateUser($user, $request->validated());

        return redirect()->back()->with('success', 'User updated successfully.');
    }
}
