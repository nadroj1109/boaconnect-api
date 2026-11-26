<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserIndexRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserCollection;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected UserService $service
    ) {}

    public function index(UserIndexRequest $request)
    {
        $users = $this->service->paginate(

            $request->validated()

        );

        return $this->paginated(

            $users,

            new UserCollection($users),

            'Users retrieved successfully.'

        );
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->service->store(

            $request->validated()

        );

        return $this->success(

            new UserResource($user),

            'User created successfully.'

        );
    }

    public function show(User $user)
    {
        return $this->success(

            new UserResource(

                $this->service->show($user)

            )

        );
    }

    public function update(
        UpdateUserRequest $request,
        User $user
    ) {

        $user = $this->service->update(

            $user,

            $request->validated()

        );

        return $this->success(

            new UserResource($user),

            'User updated successfully.'

        );
    }

    public function destroy(User $user)
    {
        $this->service->destroy($user);

        return $this->success(

            [],

            'User deleted successfully.'

        );
    }

    public function toggleStatus(User $user)
    {
        return $this->success(

            new UserResource(

                $this->service->toggleStatus($user)

            ),

            'Status updated.'

        );
    }

    public function resetPassword(User $user)
    {
        return $this->success(

            new UserResource(

                $this->service->resetPassword($user)

            ),

            'Password has been reset.'

        );
    }
}